<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Validation;
use Pmi\Audit\Log;
use Pmi\Service\WithdrawalService;
use Pmi\Evaluation\Evaluation;
use Pmi\Order\Order;

class AdminController extends AbstractController
{
    protected static $name = 'admin';
    const FIXED_ANGLE = 'fixed_angle';
    const SWINGING_BUCKET = 'swinging_bucket';

    protected static $routes = [
        ['home', '/'],
        ['sites', '/sites'],
        ['site', '/site/{siteId}', [
            'method' => 'GET|POST',
            'defaults' => ['siteId' => null]
        ]],
        ['withdrawalNotifications', '/notifications/withdrawal'],
        ['missingMeasurements', '/missing/measurements', ['method' => 'GET|POST']],
        ['missingOrders', '/missing/orders', ['method' => 'GET|POST']]
    ];

    public function homeAction(Application $app)
    {
        return $app['twig']->render('admin/index.html.twig');
    }

    public function sitesAction(Application $app)
    {
        $sites = $app['em']->getRepository('sites')->fetchBy([], ['name' => 'asc']);
        return $app['twig']->render('admin/sites/index.html.twig', ['sites' => $sites]);
    }

    public function siteAction($siteId, Application $app, Request $request)
    {
        if ($siteId) {
            $site = $app['em']->getRepository('sites')->fetchOneBy([
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
            $site = null;
        }

        $siteEditForm = $this->getSiteEditForm($app, $site);
        $siteEditForm->handleRequest($request);
        if ($siteEditForm->isSubmitted()) {
            if ($siteEditForm->isValid()) {
                if ($site) {
                    $duplicateGoogleGroup = $app['em']->getRepository('sites')->fetchBySql('google_group = ? and id != ?', [
                        $siteEditForm['google_group']->getData(),
                        $siteId
                    ]);
                } else {
                    $duplicateGoogleGroup = $app['em']->getRepository('sites')->fetchBySql('google_group = ?', [
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
        return $app['form.factory']->createBuilder(FormType::class, $site)
            ->add('name', Type\TextType::class, [
                'label' => 'Name',
                'required' => true,
                'constraints' => new Constraints\NotBlank()
            ])
            ->add('google_group', Type\TextType::class, [
                'label' => 'Google Group',
                'required' => true,
                'constraints' => new Constraints\NotBlank()
            ])
            ->add('mayolink_account', Type\TextType::class, [
                'label' => 'MayoLink Account',
                'required' => false
            ])
            ->add('organization', Type\TextType::class, [
                'label' => 'Organization',
                'required' => false
            ])
            ->add('type', Type\TextType::class, [
                'label' => 'Type',
                'required' => false
            ])
            ->add('awardee', Type\TextType::class, [
                'label' => 'Awardee',
                'required' => false
            ])
            ->add('email', Type\TextType::class, [
                'label' => 'Email address(es)',
                'required' => false,
                'constraints' => [
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
                ]
            ])
            ->add('centrifuge_type', Type\ChoiceType::class, [
                'label' => 'Centrifuge type',
                'required' => true,
                'choices' => [
                    '-- Select centrifuge type --' => null,
                    'Fixed Angle'=> self::FIXED_ANGLE,
                    'Swinging Bucket' => self::SWINGING_BUCKET
                ],
                'multiple' => false,
                'constraints' => new Constraints\NotBlank([
                    'message' => 'Please select centrifuge type'
                ])
            ])
            ->getForm();
    }

    public function withdrawalNotificationsAction(Application $app)
    {
        $withdrawal = new WithdrawalService($app);
        $notifications = $withdrawal->getWithdrawalNotifications();
        return $app['twig']->render('admin/notifications/withdrawal.html.twig', ['notifications' => $notifications]);
    }

    public function missingMeasurementsAction(Application $app, Request $request, $_route)
    {
        $missing = $app['em']->getRepository('evaluations')->fetchBySQL('finalized_ts is not null and rdr_id is null');
        $choices = [];
        foreach ($missing as $physicalMeasurements) {
            $choices[$physicalMeasurements['id']] = $physicalMeasurements['id'];
        }
        $form = $app['form.factory']->createBuilder(FormType::class)
            ->add('ids', Type\ChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
                'choices' => $choices,
                'choice_label' => function ($value, $key, $index) {
                    return ' ';
                }
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isValid()) {
            $ids = $form->get('ids')->getData();
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
                    $repository->update($evaluation['id'], ['rdr_id' => $rdrEvalId]);
                    $app->addFlashSuccess("#{$id} successfully sent to RDR");
                } else {
                    $app->addFlashError("#{$id} failed sending to RDR: " . $app['pmi.drc.participants']->getLastError());
                }
            }
            return $app->redirectToRoute($_route);
        }
        return $app['twig']->render('admin/missing/measurements.html.twig', [
            'missing' => $missing,
            'form' => $form->createView()
        ]);
    }

    public function missingOrdersAction(Application $app, Request $request, $_route)
    {
        $missing = $app['em']->getRepository('orders')->fetchBySQL('finalized_ts is not null and rdr_id is null');
        $choices = [];
        foreach ($missing as $orders) {
            $choices[$orders['id']] = $orders['id'];
        }
        $form = $app['form.factory']->createBuilder(FormType::class)
            ->add('ids', Type\ChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
                'choices' => $choices,
                'choice_label' => function ($value, $key, $index) {
                    return ' ';
                }
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isValid()) {
            $ids = $form->get('ids')->getData();
            $repository = $app['em']->getRepository('orders');
            foreach ($ids as $id) {
                $orderService = new Order();
                $order = $repository->fetchOneBy(['id' => $id]);
                if (!$order) {
                    continue;
                }
                $orderRdrObject = $orderService->getRdrObject($order, $app);
                if ($rdrId = $app['pmi.drc.participants']->createOrder($order['participant_id'], $orderRdrObject)) {
                    $repository->update($order['id'], ['rdr_id' => $rdrId]);
                    $app->addFlashSuccess("#{$id} successfully sent to RDR");
                } else {
                    $app->addFlashError("#{$id} failed sending to RDR: " . $app['pmi.drc.participants']->getLastError());
                }
            }
            return $app->redirectToRoute($_route);
        }
        return $app['twig']->render('admin/missing/orders.html.twig', [
            'missing' => $missing,
            'form' => $form->createView()
        ]);
    }
}
