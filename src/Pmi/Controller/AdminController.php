<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Validation;
use Pmi\Audit\Log;
use Pmi\Service\WithdrawalService;
use Pmi\Evaluation\Evaluation;
use Pmi\Order\Order;
use Pmi\Service\SiteSyncService;
use Pmi\Form\NoticeType;

class AdminController extends AbstractController
{
    protected static $name = 'admin';

    const FIXED_ANGLE = 'fixed_angle';
    const SWINGING_BUCKET = 'swinging_bucket';
    const FULL_DATA_ACCESS = 'full_data';
    const LIMITED_DATA_ACCESS = 'limited_data';
    const DOWNLOAD_DISABLED = 'disabled';

    protected static $routes = [
        ['home', '/'],
        ['sites', '/sites'],
        ['site', '/site/{siteId}', [
            'method' => 'GET|POST',
            'defaults' => ['siteId' => null]
        ]],
        ['siteSync', '/sites/sync', [
            'method' => 'GET|POST'
        ]],
        ['withdrawalNotifications', '/notifications/withdrawal'],
        ['missingMeasurements', '/missing/measurements', ['method' => 'GET|POST']],
        ['missingOrders', '/missing/orders', ['method' => 'GET|POST']],
        ['notices', '/notices'],
        ['notice', '/notice/{id}', [
            'method' => 'GET|POST',
            'defaults' => ['id' => null]
        ]],
        ['patientStatusRdrJson', '/patientstatus/{participantId}/organization/{organizationId}/rdr.json', ['method' => 'GET']],
        ['patientStatusHistoryRdrJson', '/patientstatus/{participantId}/organization/{organizationId}/history/rdr.json', ['method' => 'GET']],
        ['participants', '/testing/participants', ['method' => 'GET|POST']],
        ['participant', '/testing/participant/{id}', ['method' => 'GET']],
    ];

    public function homeAction(Application $app)
    {
        return $app['twig']->render('admin/index.html.twig');
    }

    public function siteSyncAction(Application $app, Request $request)
    {
        $siteSync = new SiteSyncService($app);
        $isProd = $app->isProd();
        $preview = $siteSync->dryRun($isProd);

        if (!$app->getConfig('sites_use_rdr')) {
            $formView = false;
        } else {
            $form = $app['form.factory']->createBuilder(FormType::class)->getForm();
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                if ($request->request->has('awardeeOrgSync')) {
                    $siteSync->syncAwardees();
                    $siteSync->syncOrganizations();
                } else {
                    $siteSync->sync($isProd);
                }
                $app->addFlashSuccess('Successfully synced');
                return $app->redirectToRoute('admin_sites');
            }
            $formView = $form->createView();
        }
        $canSync = !empty($preview['deleted']) || !empty($preview['modified']) || !empty($preview['created']);
        return $app['twig']->render('admin/sites/sync.html.twig', [
            'preview' => $preview,
            'form' => $formView,
            'canSync' => $canSync
        ]);
    }

    public function sitesAction(Application $app)
    {
        $sites = $app['em']->getRepository('sites')->fetchBy(['deleted' => 0], ['name' => 'asc']);
        return $app['twig']->render('admin/sites/index.html.twig', [
            'sites' => $sites,
            'sync' => $app->getConfig('sites_use_rdr')
        ]);
    }

    public function siteAction($siteId, Application $app, Request $request)
    {
        $syncEnabled = $app->getConfig('sites_use_rdr');
        if ($siteId) {
            $site = $app['em']->getRepository('sites')->fetchOneBy([
                'deleted' => 0,
                'id' => $siteId
            ]);
            if (!$site) {
                $app->abort(404);;
            }

            if ($request->request->has('delete')) {
                $app['em']->getRepository('sites')->delete($siteId);
                $app->log(Log::SITE_DELETE, $siteId);
                $app->addFlashNotice('Site removed');
                return $app->redirectToRoute('admin_sites');
            }
        } else {
            if ($syncEnabled) {
                // can't create new sites if syncing from rdr
                $app->abort(404);
            }
            $site = null;
        }

        $siteEditForm = $this->getSiteEditForm($app, $site);
        $siteEditForm->handleRequest($request);
        if ($siteEditForm->isSubmitted()) {
            if ($siteEditForm->isValid() && !$syncEnabled) {
                if ($site) {
                    $duplicateGoogleGroup = $app['em']->getRepository('sites')->fetchBySql('deleted = 0 and google_group = ? and id != ?', [
                        $siteEditForm['google_group']->getData(),
                        $siteId
                    ]);
                } else {
                    $duplicateGoogleGroup = $app['em']->getRepository('sites')->fetchBySql('deleted = 0 and google_group = ?', [
                        $siteEditForm['google_group']->getData()
                    ]);
                }
                if ($duplicateGoogleGroup) {
                    $siteEditForm['google_group']->addError(new FormError('This google group has already been used for another site.'));
                }
            }
            if ($siteEditForm->isValid()) {
                if ($site) {
                    if ($app['em']->getRepository('sites')->update($siteId, $siteEditForm->getData())) {
                        $app->log(Log::SITE_EDIT, $siteId);
                        $app->addFlashNotice('Site updated');
                    }
                } else {
                    if ($siteId = $app['em']->getRepository('sites')->insert($siteEditForm->getData())) {
                        $app->log(Log::SITE_ADD, $siteId);
                        $app->addFlashNotice('Site added');
                    }
                }
                return $app->redirectToRoute('admin_sites');
            } else {
                if (count($siteEditForm->getErrors()) == 0) {
                    $siteEditForm->addError(new FormError('Please correct the errors below'));
                }
            }
        }

        return $app['twig']->render('admin/sites/edit.html.twig', [
            'site' => $site,
            'siteForm' => $siteEditForm->createView()
        ]);
    }

    protected function getSiteEditForm(Application $app, $site = null)
    {
        if ($site && isset($site['id'])) {
            unset($site['id']);
        }
        $syncEnabled = $app->getConfig('sites_use_rdr');
        $builder = $app['form.factory']->createBuilder(FormType::class, $site);
        $disabled = $syncEnabled ? true : false;
        $isProd = $app->isProd();
        $builder
            ->add('name', Type\TextType::class, [
                'label' => 'Name',
                'required' => true,
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'disabled' => $disabled,
            ])
            ->add('status', Type\ChoiceType::class, [
                'label' => 'Status',
                'required' => true,
                'choices' => [
                    'Active'=> 1,
                    'Inactive' => 0
                ],
                'disabled' => $disabled
            ])
            ->add('google_group', Type\TextType::class, [
                'label' => 'Google Group',
                'required' => true,
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'disabled' => $disabled,
            ])
            ->add('organization', Type\TextType::class, [
                'label' => 'Awardee (formerly HPO ID)',
                'required' => false,
                'constraints' => new Constraints\Type('string'),
                'disabled' => $disabled,
            ])
            ->add('organization_id', Type\TextType::class, [
                'label' => 'Organization',
                'required' => false,
                'constraints' => new Constraints\Type('string'),
                'disabled' => $disabled,
            ])
            ->add('mayolink_account', Type\TextType::class, [
                'label' => 'MayoLINK Account',
                'required' => false,
                'constraints' => new Constraints\Type('string'),
                'disabled' => $disabled && $isProd,
            ])
            ->add('type', Type\TextType::class, [
                'label' => 'Type (e.g. HPO, DV)',
                'required' => false,
                'constraints' => new Constraints\Type('string'),
                'disabled' => $disabled,
            ])
            ->add('email', Type\TextType::class, [
                'label' => 'Email address(es)',
                'required' => false,
                'constraints' => [
                    new Constraints\Type('string'),
                    new Constraints\Length(['max' => 512]),
                    new Constraints\Callback(function($list, $context) {
                        $list = trim($list);
                        if (empty($list)) {
                            return;
                        }
                        $emails = explode(',', $list);
                        $validator = Validation::createValidator();
                        foreach ($emails as $email) {
                            $email = trim($email);
                            $errors = $validator->validate($email, new Constraints\Email());
                            if (count($errors) > 0) {
                                $context
                                    ->buildViolation('Must be a comma-separated list of valid email addresses')
                                    ->addViolation();
                                break;
                            }
                        }
                    })
                ],
                'disabled' => $disabled && $isProd,
            ])
            ->add('awardee', Type\TextType::class, [
                'label' => 'Program (e.g. STSI)',
                'required' => false,
                'constraints' => new Constraints\Type('string')
            ])
            ->add('centrifuge_type', Type\ChoiceType::class, [
                'label' => 'Centrifuge type',
                'required' => false,
                'choices' => [
                    '-- Select centrifuge type --' => null,
                    'Fixed Angle'=> self::FIXED_ANGLE,
                    'Swinging Bucket' => self::SWINGING_BUCKET
                ],
                'multiple' => false
            ])
            ->add('workqueue_download', Type\ChoiceType::class, [
                'label' => 'Work Queue Download',
                'required' => true,
                'choices' => [
                    'Full Data Access'=> self::FULL_DATA_ACCESS,
                    'Limited Data Access (No PII)' => self::LIMITED_DATA_ACCESS,
                    'Download Disabled' => self::DOWNLOAD_DISABLED
                ],
                'multiple' => false,
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ]
            ]);
        return $builder->getForm();
    }

    public function withdrawalNotificationsAction(Application $app)
    {
        $withdrawal = new WithdrawalService($app);
        $notifications = $withdrawal->getWithdrawalNotifications();
        return $app['twig']->render('admin/notifications/withdrawal.html.twig', ['notifications' => $notifications]);
    }

    public function missingMeasurementsAction(Application $app, Request $request, $_route)
    {
        $query = "
            SELECT e.*
            FROM evaluations e
            LEFT JOIN evaluations_history eh ON e.history_id = eh.id
            WHERE e.finalized_ts is not null
              AND e.rdr_id is null
              AND (eh.type != 'cancel'
              OR eh.type is null)
        ";
        $missing = $app['db']->fetchAll($query);
        $choices = [];
        foreach ($missing as $physicalMeasurements) {
            $choices[$physicalMeasurements['id']] = $physicalMeasurements['id'];
        }
        $form = $app['form.factory']->createBuilder(FormType::class)
            ->add('ids', Type\ChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
                'choices' => $choices,
                'choice_label' => false
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $ids = $form->get('ids')->getData();
            if (!empty($ids) && $form->isValid()) {
                $repository = $app['em']->getRepository('evaluations');
                foreach ($ids as $id) {
                    $evaluationService = new Evaluation();
                    $evaluation = $repository->fetchOneBy(['id' => $id]);
                    if (!$evaluation) {
                        continue;
                    }
                    $evaluationService->loadFromArray($evaluation, $app);
                    $parentRdrId = null;
                    if ($evaluation['parent_id']) {
                        $parentEvaluation = $repository->fetchOneBy(['id' => $evaluation['parent_id']]);
                        if ($parentEvaluation) {
                            $parentRdrId = $parentEvaluation['rdr_id'];
                        }
                    }
                    $fhir = $evaluationService->getFhir($evaluation['finalized_ts'], $parentRdrId);
                    if ($rdrEvalId = $app['pmi.drc.participants']->createEvaluation($evaluation['participant_id'], $fhir)) {
                        $repository->update($evaluation['id'], ['rdr_id' => $rdrEvalId, 'fhir_version' => \Pmi\Evaluation\Fhir::CURRENT_VERSION]);
                        $app->addFlashSuccess("#{$id} successfully sent to RDR");
                    } else {
                        $app->addFlashError("#{$id} failed sending to RDR: " . $app['pmi.drc.participants']->getLastError());
                    }
                }
                return $app->redirectToRoute($_route);
            } else {
                $app->addFlashError('Please select at least one physical measurements');
            }
        }
        return $app['twig']->render('admin/missing/measurements.html.twig', [
            'missing' => $missing,
            'form' => $form->createView()
        ]);
    }

    public function missingOrdersAction(Application $app, Request $request, $_route)
    {
        $query = "
            SELECT o.*
            FROM orders o
            LEFT JOIN orders_history oh ON o.history_id = oh.id
            WHERE o.finalized_ts is not null
              AND o.mayo_id is not null
              AND o.rdr_id is null
              AND (oh.type != 'cancel'
              OR oh.type is null)
        ";
        $missing = $app['db']->fetchAll($query);
        $choices = [];
        foreach ($missing as $orders) {
            $choices[$orders['id']] = $orders['id'];
        }
        $form = $app['form.factory']->createBuilder(FormType::class)
            ->add('ids', Type\ChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
                'choices' => $choices,
                'choice_label' => false
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $ids = $form->get('ids')->getData();
            if (!empty($ids) && $form->isValid()) {
                $repository = $app['em']->getRepository('orders');
                foreach ($ids as $id) {
                    $orderService = new Order($app);
                    $order = $repository->fetchOneBy(['id' => $id]);
                    if (!$order) {
                        continue;
                    }
                    $orderRdrObject = $orderService->getRdrObject($order);
                    if ($rdrId = $app['pmi.drc.participants']->createOrder($order['participant_id'], $orderRdrObject)) {
                        $repository->update($order['id'], ['rdr_id' => $rdrId]);
                        $app->addFlashSuccess("#{$id} successfully sent to RDR");
                    } else {
                        $app->addFlashError("#{$id} failed sending to RDR: " . $app['pmi.drc.participants']->getLastError());
                    }
                }
                return $app->redirectToRoute($_route);
            } else {
                $app->addFlashError('Please select at least one order');
            }
        }
        return $app['twig']->render('admin/missing/orders.html.twig', [
            'missing' => $missing,
            'form' => $form->createView()
        ]);
    }

    public function noticesAction(Application $app)
    {
        $notices = $app['em']->getRepository('notices')->fetchBy([], ['id' => 'asc']);
        return $app['twig']->render('admin/notices/index.html.twig', [
            'notices' => $notices
        ]);
    }

    public function noticeAction($id, Application $app, Request $request)
    {
        if ($id) {
            $notice = $app['em']->getRepository('notices')->fetchOneBy(['id' => $id]);
            if (!$notice) {
                $app->abort(404);;
            }
        } else {
            $notice = null;
        }

        $form = $app['form.factory']->createNamed(
            'form',
            NoticeType::class,
            $notice ?: ['status' => 1],
            ['timezone' => $app->getUserTimezone()]
        );

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                if ($notice === null) {
                    if ($id = $app['em']->getRepository('notices')->insert($form->getData())) {
                        $app->log(Log::NOTICE_ADD, $id);
                        $app->addFlashNotice('Notice added');
                    }
                } elseif ($request->request->has('delete')) {
                    if ($app['em']->getRepository('notices')->delete($id)) {
                        $app->log(Log::NOTICE_DELETE, $id);
                        $app->addFlashNotice('Notice removed');
                    }
                } else {
                    if ($app['em']->getRepository('notices')->update($id, $form->getData())) {
                        $app->log(Log::NOTICE_EDIT, $id);
                        $app->addFlashNotice('Notice updated');
                    }
                }
                return $app->redirectToRoute('admin_notices');
            } else {
                // Add a form-level error if there are none
                if (count($form->getErrors()) == 0) {
                    $form->addError(new FormError('Please correct the errors below'));
                }
            }
        }

        return $app['twig']->render('admin/notices/edit.html.twig', [
            'notice' => $notice,
            'form' => $form->createView()
        ]);
    }

    public function patientStatusRdrJsonAction($participantId, $organizationId, Application $app)
    {
        $object = $app['pmi.drc.participants']->getPatientStatus($participantId, $organizationId);
        return $app->jsonPrettyPrint($object);
    }

    public function patientStatusHistoryRdrJsonAction($participantId, $organizationId, Application $app)
    {
        $object = $app['pmi.drc.participants']->getPatientStatusHistory($participantId, $organizationId);
        return $app->jsonPrettyPrint($object);
    }

    public function participantsAction(Application $app, Request $request)
    {
        if ($app->isProd()) {
            $app->abort(404);
        }
        $idForm = $app['form.factory']->createNamedBuilder('id', FormType::class)
            ->add('participantId', Type\TextType::class, [
                'label' => 'Participant ID',
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'attr' => [
                    'placeholder' => 'P000000000'
                ]
            ])
            ->getForm();

        $idForm->handleRequest($request);

        if ($idForm->isValid()) {
            $id = $idForm->get('participantId')->getData();
            $participant = $app['pmi.drc.participants']->getById($id);
            if ($participant) {
                return $app->redirectToRoute('admin_participant', ['id' => $id]);
            }
            $app->addFlashError('Participant ID not found');
        }

        return $app['twig']->render('admin/testing/participants.html.twig', [
            'idForm' => $idForm->createView()
        ]);
    }

    public function participantAction($id, Application $app, Request $request)
    {
        if ($app->isProd()) {
            $app->abort(404);
        }
        $participant = $app['pmi.drc.participants']->getByIdRaw($id);
        if (!$participant) {
            $app->abort(404);
        }
        ksort($participant);
        return $app['twig']->render('admin/testing/participant.html.twig', [
            'participant' => $participant
        ]);
    }
}
