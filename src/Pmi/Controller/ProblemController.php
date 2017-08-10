<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormError;
use Pmi\Audit\Log;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Validator\Constraints;
use Pmi\Service\ProblemService;

class ProblemController extends AbstractController
{
    const RELATED_BASELINE = 'related_baseline';
    const UNRELATED_BASELINE = 'unrelated_baseline';
    const OTHER = 'other';

    protected static $routes = [
        ['problemForm', '/participant/{participantId}/problem/{problemId}', [
            'method' => 'GET|POST',
            'defaults' => ['problemId' => null]
        ]],
        ['problemComment', '/participant/{participantId}/problem/{problemId}/comment', [
            'method' => 'POST'
        ]]
    ];

    protected $disabled = false;

    protected $constraint = false;

    protected $problemTypeOptions = ['Physical injury related to baseline appointment', 'Physical injury unrelated to baseline appointment', 'Other'];

    public function problemFormAction($participantId, $problemId, Application $app, Request $request)
    {
        if (!$app->isDVType()) {
            $app->abort(404);
        }
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        if (!$participant->status) {
            $app->abort(403);
        }
        if ($problemId) {
            $problem = $app['em']->getRepository('problems')->fetchOneBy([
                'id' => $problemId,
                'participant_id' => $participantId
            ]);
            if (!$problem) {
                $app->abort(404);;
            } else {
                $problem['problem_date'] = $problem['problem_date'] ? new \DateTime($problem['problem_date']) : NULL;
                $problem['provider_aware_date'] = $problem['provider_aware_date'] ? new \DateTime($problem['provider_aware_date']) : NULL;
                if (!empty($problem['finalized_ts'])) {
                    $this->disabled = true;
                }
            }
        } else {
            $problem = null;
        }
        if ($request->request->has('reportable_finalize')) {
            $this->constraint = true;
            $problemService = new ProblemService($app);
        }
        $problemForm = $this->getProblemForm($app, $problem);
        $problemForm->handleRequest($request);
        if ($problemForm->isSubmitted()) {
            if ($problemForm->isValid()) {
                $problemData = $problemForm->getData();
                $now = new \DateTime();
                $problemData['updated_ts'] = $now;
                if ($request->request->has('reportable_finalize') && (!$problem || empty($problem['finalized_ts']))) {
                    $problemData['finalized_user_id'] = $app->getUser()->getId();
                    $problemData['finalized_site'] = $app->getSiteId();
                    $problemData['finalized_ts'] = $now;                       
                }
                if ($problem) {
                    if (empty($problem['finalized_ts']) && $app['em']->getRepository('problems')->update($problemId, $problemData)) {
                        $app->log(Log::PROBLEM_EDIT, $problemId);
                        if ($request->request->has('reportable_finalize')) {
                            $app->addFlashNotice('Report finalized');
                            $problemService->sendProblemReportEmail($problemId);
                        } else {
                            $app->addFlashNotice('Report updated');
                        }                       
                    }
                } else {
                    $problemData['user_id'] = $app->getUser()->getId();
                    $problemData['site'] = $app->getSiteId();
                    $problemData['participant_id'] = $participantId;
                    $problemData['created_ts'] = $now;
                    if ($problemId = $app['em']->getRepository('problems')->insert($problemData)) {
                        $app->log(Log::PROBLEM_CREATE, $problemId); 
                        if ($request->request->has('reportable_finalize')) {
                            $app->addFlashNotice('Report finalized');
                            $problemService->sendProblemReportEmail($problemId);
                        } else {
                            $app->addFlashNotice('Report saved');
                        }                       
                    } else {
                        $app->addFlashError('Failed to create new report');
                    }
                }
                return $app->redirectToRoute('participant', [
                    'id' => $participantId
                ]);
            } else {
                if (count($problemForm->getErrors()) == 0) {
                    $problemForm->addError(new FormError('Please correct the errors below'));
                }
            }
        }
        if (!empty($problem['finalized_ts'])) {
            $problemCommentForm = $this->getProblemCommentForm($app);
            $problemCommentForm = $problemCommentForm->createView();
            $problemComments = $app['em']->getRepository('problem_comments')->fetchBy(
                ['problem_id' => $problemId],
                ['created_ts' => 'DESC']
            );
        } else {
            $problemCommentForm = null;
            $problemComments = null;
        }

        return $app['twig']->render('problem.html.twig', [
            'problem' => $problem,
            'participant' => $participant,
            'problemForm' => $problemForm->createView(),
            'problemCommentForm' => $problemCommentForm,
            'problemComments' => $problemComments
        ]);
    }

    public function problemCommentAction($participantId, $problemId, Application $app, Request $request)
    {
        if (!$app->isDVType()) {
            $app->abort(404);
        }
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        if (!$participant->status) {
            $app->abort(403);
        }
        if ($problemId) {
            $problem = $app['em']->getRepository('problems')->fetchOneBy([
                'id' => $problemId,
                'participant_id' => $participantId
            ]);
            if (!$problem && !$problem['finalized_ts']) {
                $app->abort(404);;
            }
        }
        $problemCommentForm = $this->getProblemCommentForm($app);
        $problemCommentForm->handleRequest($request);
        if ($problemCommentForm->isSubmitted()) {
            if ($problemCommentForm->isValid()) {
                $problemCommentData = $problemCommentForm->getData();
                $now = new \DateTime();
                $problemCommentData['problem_id'] = $problemId;
                $problemCommentData['user_id'] = $app->getUser()->getId();
                $problemCommentData['site'] = $app->getSiteId();
                $problemCommentData['created_ts'] = $now;
                if ($commentId = $app['em']->getRepository('problem_comments')->insert($problemCommentData)) {
                    $app->log(Log::PROBLEM_COMMENT_CREATE, $commentId);
                    $app->addFlashNotice('Comment saved');
                    $problemService = new ProblemService($app);
                    $problemService->sendProblemReportEmail($problemId);
                    return $app->redirectToRoute('participant', [
                        'id' => $participantId
                    ]);
                } else {
                   $app->addFlashError('Failed to create new comment'); 
                }
            }
        }
    }

    public function getProblemForm(Application $app, $problem)
    {
        $constraintDateTime = new \DateTime('+5 minutes');
        $problemTypeAttributes = [
            'label' => 'Unanticipated problem type',
            'required' => true,
            'disabled' => $this->disabled,
            'choices' => [
                $this->problemTypeOptions[0]=> self::RELATED_BASELINE,
                $this->problemTypeOptions[1] => self::UNRELATED_BASELINE,
                $this->problemTypeOptions[2] => self::OTHER
            ],
            'multiple' => false,
            'expanded' => true
        ];
        $siteAttributes = [
            'label' => 'Name of enrollment site where provider became aware of problem',
            'required' => false,
            'disabled' => $this->disabled
        ];
        $staffAttributes = [
            'label' => 'Name of staff recording the problem',
            'required' => false,
            'disabled' => $this->disabled
        ];
        $problemDateAttributes = [
            'label' => 'Date of problem',
            'widget' => 'single_text',
            'format' => 'M/d/yyyy h:mm a',
            'required' => false,
            'disabled' => $this->disabled,
            'view_timezone' => $app->getUserTimezone(),
            'model_timezone' => 'UTC',
            'constraints' => [
                new Constraints\LessThanOrEqual([
                    'value' => $constraintDateTime,
                    'message' => 'Timestamp cannot be in the future'
                ])
            ]
        ];
        $providerAwareDateAttributes = [
            'label' => 'Date provider became aware of problem',
            'widget' => 'single_text',
            'format' => 'M/d/yyyy h:mm a',
            'required' => false,
            'disabled' => $this->disabled,
            'view_timezone' => $app->getUserTimezone(),
            'model_timezone' => 'UTC',
            'constraints' => [
                new Constraints\LessThanOrEqual([
                    'value' => $constraintDateTime,
                    'message' => 'Timestamp cannot be in the future'
                ])
            ]
        ];
        $descriptionAttributes = [
            'label' => 'Description of problem',
            'required' => false,
            'disabled' => $this->disabled
        ];
        $actionTakenAttributes = [
            'label' => 'Description of corrective action taken',
            'required' => false,
            'disabled' => $this->disabled
        ];
        if ($this->constraint) {
            $siteAttributes['constraints'] = new Constraints\NotBlank();
            $staffAttributes['constraints'] = new Constraints\NotBlank();
            $problemDateAttributes['constraints'][] = new Constraints\NotBlank();
            $providerAwareDateAttributes['constraints'][] = new Constraints\NotBlank();
            $descriptionAttributes['constraints'] = new Constraints\NotBlank();
            $actionTakenAttributes['constraints'] = new Constraints\NotBlank();
        }
        $problemForm = $app['form.factory']->createBuilder(Type\FormType::class, $problem)
            ->add('problem_type', Type\ChoiceType::class, $problemTypeAttributes)
            ->add('enrollment_site', Type\TextType::class, $siteAttributes)
            ->add('staff_name', Type\TextType::class, $staffAttributes)
            ->add("problem_date", Type\DateTimeType::class, $problemDateAttributes)
            ->add("provider_aware_date", Type\DateTimeType::class, $providerAwareDateAttributes)
            ->add('description', Type\TextareaType::class, $descriptionAttributes)
            ->add('action_taken', Type\TextareaType::class, $actionTakenAttributes)
            ->getForm();
            return $problemForm;    
    }

    public function getProblemCommentForm(Application $app)
    {
        $problemCommentForm = $app['form.factory']->createBuilder(Type\FormType::class, null)
            ->add('staff_name', Type\TextType::class, [
                'label' => 'Staff Name',
                'required' => true
            ])
            ->add('comment', Type\TextareaType::class, [
                'label' => 'Comment',
                'required' => true
            ])
            ->getForm();
            return $problemCommentForm;    
    }

}
