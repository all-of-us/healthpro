<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormError;
use Pmi\Audit\Log;
use Pmi\Evaluation\Evaluation;

class EvaluationController extends AbstractController
{
    protected static $routes = [
        ['participantEval', '/participant/{participantId}/eval/{evalId}', [
            'method' => 'GET|POST',
            'defaults' => ['evalId' => null]
        ]]
    ];

    public function participantEvalAction($participantId, $evalId, Application $app, Request $request)
    {
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        if (!$participant->consentComplete) {
            $app->abort(403);
        }
        $evaluationService = new Evaluation();
        if ($evalId) {
            $evaluation = $app['em']->getRepository('evaluations')->fetchOneBy([
                'id' => $evalId,
                'participant_id' => $participantId
            ]);
            if (!$evaluation) {
                $app->abort(404);
            }
            $evaluationService->loadFromArray($evaluation);
        } else {
            $evaluation = null;
        }

        $evaluationForm = $evaluationService->getForm($app['form.factory']);
        $evaluationForm->handleRequest($request);
        if (!empty($evaluation['finalized_ts'])) {
            if ($evaluationForm->isValid() && $request->request->has('reopen')) {
                $app['em']->getRepository('evaluations')->update($evalId, [
                    'finalized_ts' => null
                ]);
                $app->addFlashNotice('Evaluation reopened');
                return $app->redirectToRoute('participantEval', [
                    'participantId' => $participant->id,
                    'evalId' => $evalId
                ]);
            }
        } else {
            if ($evaluationForm->isSubmitted()) {
                if ($evaluationForm->isValid()) {
                    $evaluationService->setData($evaluationForm->getData());
                    $dbArray = $evaluationService->toArray();
                    $dbArray['updated_ts'] = (new \DateTime())->format('Y-m-d H:i:s');
                    if ($request->request->has('finalize')) {
                        $dbArray['finalized_ts'] = (new \DateTime())->format('Y-m-d H:i:s');
                    }
                    if (!$evaluation) {
                        $dbArray['participant_id'] = $participant->id;
                        $dbArray['created_ts'] = $dbArray['updated_ts'];
                        if ($evalId = $app['em']->getRepository('evaluations')->insert($dbArray)) {
                            $app->log(Log::EVALUATION_CREATE, $evalId);
                            $app->addFlashNotice('Evaluation saved');
                            return $app->redirectToRoute('participantEval', [
                                'participantId' => $participant->id,
                                'evalId' => $evalId
                            ]);
                        } else {
                            $app->addFlashError('Failed to create new evaluation');
                        }
                    } else {
                        if ($app['em']->getRepository('evaluations')->update($evalId, $dbArray)) {
                            $app->log(Log::EVALUATION_EDIT, $evalId);
                            $app->addFlashNotice('Evaluation saved');
                            return $app->redirectToRoute('participantEval', [
                                'participantId' => $participant->id,
                                'evalId' => $evalId
                            ]);
                        } else {
                            $app->addFlashError('Failed to update evaluation');
                        }
                    }
                } else {
                    if (count($evaluationForm->getErrors()) == 0) {
                        $evaluationForm->addError(new FormError('Please correct the errors below'));
                    }
                }
            }
        }

        return $app['twig']->render('evaluation.html.twig', [
            'participant' => $participant,
            'evaluation' => $evaluation,
            'evaluationForm' => $evaluationForm->createView(),
            'schema' => $evaluationService->getSchema()
        ]);
    }
}
