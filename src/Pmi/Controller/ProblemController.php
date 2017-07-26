<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormError;
use Pmi\Audit\Log;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Validator\Constraints;

class ProblemController extends AbstractController
{
    const RELATED_BASELINE = 'related_baseline';
    const UNRELATED_BASELINE = 'unrelated_baseline';
    const OTHER = 'other';

    protected static $routes = [
        ['problem', '/participant/{participantId}/problem/{problemId}', [
            'method' => 'GET|POST',
            'defaults' => ['problemId' => null]
        ]],
        ['problemComment', '/participant/{participantId}/problem/{problemId}/comment', [
            'method' => 'POST'
        ]]
    ];

    protected $disabled = false;

    public function problemAction($participantId, $problemId, Application $app, Request $request)
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
                $problem['problem_date'] = new \DateTime($problem['problem_date']);
                $problem['provider_aware_date'] = new \DateTime($problem['provider_aware_date']);
                if (!empty($problem['finalized_ts'])) {
                    $this->disabled = true;
                }
            }
        } else {
            $problem = null;
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
                        if ($request->request->has('reportable_finalize')) {
                            $app->addFlashNotice('Report finalized');
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
                        if ($request->request->has('reportable_finalize')) {
                            $app->addFlashNotice('Report finalized');
                        }else {
                            $app->addFlashNotice('Report saved');
                        }                       
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
        } else {
            $problemCommentForm = null;
        }

        return $app['twig']->render('problem.html.twig', [
            'problem' => $problem,
            'participant' => $participant,
            'problemForm' => $problemForm->createView(),
            'problemCommentForm' => $problemCommentForm
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
                if ($problemCommentId = $app['em']->getRepository('problem_comments')->insert($problemCommentData)) {
                    $app->addFlashNotice('Comment saved');
                    return $app->redirectToRoute('participant', [
                        'id' => $participantId
                    ]);

                }
            }
        }
    }

    public function getProblemForm(Application $app, $problem)
    {
        $constraintDateTime = new \DateTime('+5 minutes');
        $problemForm = $app['form.factory']->createBuilder(Type\FormType::class, $problem)
            ->add('problem_type', Type\ChoiceType::class, [
                'label' => 'Unanticipated problem type',
                'required' => true,
                'disabled' => $this->disabled,
                'choices' => [
                    'Physical injury related to baseline appointment' => self::RELATED_BASELINE,
                    'Physical injury unrelated to baseline appointment' => self::UNRELATED_BASELINE,
                    'Other' => self::OTHER
                ],
                'multiple' => false,
                'expanded' => true
            ])
            ->add('enrollment_site', Type\TextType::class, [
                'label' => 'Name of enrollment site where provider became aware of event',
                'required' => true,
                'disabled' => $this->disabled,
            ])
            ->add('staff_name', Type\TextType::class, [
                'label' => 'Name of staff recording the event',
                'required' => true,
                'disabled' => $this->disabled,
            ])
            ->add("problem_date", Type\DateTimeType::class, [
                'label' => 'Date of Event',
                'widget' => 'single_text',
                'format' => 'M/d/yyyy h:mm a',
                'required' => true,
                'disabled' => $this->disabled,
                'view_timezone' => $app->getUserTimezone(),
                'model_timezone' => 'UTC',
                'constraints' => [
                    new Constraints\LessThanOrEqual([
                        'value' => $constraintDateTime,
                        'message' => 'Timestamp cannot be in the future'
                    ])
                ]
            ])
            ->add("provider_aware_date", Type\DateTimeType::class, [
                'label' => 'Date provider became aware of event',
                'widget' => 'single_text',
                'format' => 'M/d/yyyy h:mm a',
                'required' => true,
                'disabled' => $this->disabled,
                'view_timezone' => $app->getUserTimezone(),
                'model_timezone' => 'UTC',
                'constraints' => [
                    new Constraints\LessThanOrEqual([
                        'value' => $constraintDateTime,
                        'message' => 'Timestamp cannot be in the future'
                    ])
                ]
            ])
            ->add('description', Type\TextareaType::class, [
                'label' => 'Description of event',
                'required' => true,
                'disabled' => $this->disabled,
            ])
            ->add('action_taken', Type\TextType::class, [
                'label' => 'Corrective Action taken',
                'required' => true,
                'disabled' => $this->disabled,
            ])
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
