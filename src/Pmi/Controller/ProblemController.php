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
    protected static $routes = [
        ['problem', '/participant/{participantId}/problems/{problemId}', [
            'method' => 'GET|POST',
            'defaults' => ['problemId' => null]
        ]]
    ];

    public function problemAction($participantId, $problemId, Application $app, Request $request)
    {
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        if (!$participant->status) {
            $app->abort(403);
        }
        if ($problemId) {
            $problem = $app['em']->getRepository('problems')->fetchOneBy([
                'id' => $problemId
            ]);
            if (!$problem) {
                $app->abort(404);;
            } else {
                $problem['problem_date'] = new \DateTime($problem['problem_date']);
                if ($problem['provider_aware_date']) {
                    $problem['provider_aware_date'] = new \DateTime($problem['provider_aware_date']);
                }
            }
        } else {
            $problem = null;
        }
        //echo '<pre>'; print_r($problem);exit;
        $problemForm = $this->getProblemForm($app, $problem);
        $problemForm->handleRequest($request);

        if ($problemForm->isValid()) {
            $problemData = $problemForm->getData();
            if ($problemData['report_type'] !== 'physical') {
                $problemData['physical_injury_type'] = null;
                $problemData['provider_aware_date'] = null;
            }
            $now = new \DateTime();
            $problemData['updated_ts'] = $now;
            if ($problem) {
                if ($app['em']->getRepository('problems')->update($problemId, $problemData)) {
                    $app->addFlashNotice('Report updated');
                }
            } else {
                $problemData['participant_id'] = $participantId;
                $problemData['created_ts'] = $now;
                if ($problemId = $app['em']->getRepository('problems')->insert($problemData)) {
                    $app->addFlashNotice('Report created');
                }
            }
            return $app->redirectToRoute('problem', [
                'participantId' => $participantId,
                'problemId' => $problemId
            ]);
        } else {
            if (count($problemForm->getErrors()) == 0) {
                $problemForm->addError(new FormError('Please correct the errors below'));
            }
        }

        return $app['twig']->render('problem.html.twig', [
            'problem' => $problem,
            'problemForm' => $problemForm->createView()
        ]);
    }

    public function getProblemForm(Application $app, $problem)
    {
        $constraintDateTime = new \DateTime('+5 minutes');
        $problemForm = $app['form.factory']->createBuilder(Type\FormType::class, $problem)
            ->add('report_type', Type\ChoiceType::class, [
                'label' => 'Report Type',
                'required' => true,
                'choices' => [
                    'Physical Injury' => 'physical',
                    'Indication of suicidal thoughts' => 'suicidal',
                    'Verbal and non-verbal indications individual may be victim of emotional or physical abuse' => 'verbal',
                    'Misconduct on part of participant that negatively impacts the center/clinic or its patrons' => 'misconduct'
                ],
                'multiple' => false,
                'expanded' => true
            ])
            ->add('physical_injury_type', Type\ChoiceType::class, [
                'label' => 'Physical Injury Type',
                'required' => true,
                'choices' => [
                    'Injury related to baseline appointment' => 'baseline_related',
                    'Injury Unrelated to baseline appointment' => 'baseline_unrelated'
                ],
                'multiple' => false,
                'expanded' => false
            ])
            ->add('investigator_name', Type\TextType::class, [
                'label' => 'Investigator Name',
                'required' => false
            ])
            ->add("problem_date", Type\DateTimeType::class, [
                'label' => 'Date of Injury',
                'widget' => 'single_text',
                'format' => 'M/d/yyyy h:mm a',
                'required' => true,
                'disabled' => false,
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
                'required' => false,
                'disabled' => false,
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
                'required' => false
            ])
            ->add('action_taken', Type\TextType::class, [
                'label' => 'Corrective Action taken',
                'required' => false
            ])
            ->add('follow_up', Type\TextType::class, [
                'label' => 'Follow up',
                'required' => false
            ])
            ->getForm();
            return $problemForm;    
    }
}
