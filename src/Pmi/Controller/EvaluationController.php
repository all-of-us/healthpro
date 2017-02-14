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
        ['evaluation', '/participant/{participantId}/measurements/{evalId}', [
            'method' => 'GET|POST',
            'defaults' => ['evalId' => null]
        ]],
        ['evaluationFhir', '/participant/{participantId}/measurements/{evalId}/fhir.json'],
        ['evaluationRdr', '/participant/{participantId}/measurements/{evalId}/rdr.json']
    ];

    /* For debugging generated FHIR bundle - only allowed in dev */
    public function evaluationFhirAction($participantId, $evalId, Application $app)
    {
        if (!$app->isLocal()) {
            $app->abort(404);
        }
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        $evaluationService = new Evaluation();
        $evaluation = $app['em']->getRepository('evaluations')->fetchOneBy([
            'id' => $evalId,
            'participant_id' => $participantId
        ]);
        if (!$evaluation) {
            $app->abort(404);
        }
        $evaluationService->loadFromArray($evaluation);
        if ($evaluation['finalized_ts']) {
            $date = $evaluation['finalized_ts'];
        } else {
            $date = new \DateTime();
        }
        return $app->json($evaluationService->getFhir($date));
    }

    /* For debugging evaluation object pushed to RDR - only allowed in dev */
    public function evaluationRdrAction($participantId, $evalId, Application $app)
    {
        if (!$app->isLocal()) {
            $app->abort(404);
        }
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        $evaluation = $app['em']->getRepository('evaluations')->fetchOneBy([
            'id' => $evalId,
            'participant_id' => $participantId
        ]);
        if (!$evaluation) {
            $app->abort(404);
        }
        if (!$evaluation['rdr_id']) {
            $app->abort(500, 'rdr_id is not set');
        }
        $rdrEvaluation = $app['pmi.drc.participants']->getEvaluation($participantId, $evaluation['rdr_id']);
        return $app->json($rdrEvaluation);
    }

    public function evaluationAction($participantId, $evalId, Application $app, Request $request)
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
                $app->addFlashNotice('Physical measurements reopened');
                return $app->redirectToRoute('evaluation', [
                    'participantId' => $participant->id,
                    'evalId' => $evalId
                ]);
            }
        } else {
            if ($evaluationForm->isSubmitted()) {
                if ($evaluationForm->isValid()) {
                    $evaluationService->setData($evaluationForm->getData());
                    $dbArray = $evaluationService->toArray();
                    $now = new \DateTime();
                    $dbArray['updated_ts'] = $now;
                    if ($request->request->has('finalize')) {
                        $errors = $evaluationService->getFinalizeErrors();
                        if (count($errors) === 0) {
                            $dbArray['finalized_ts'] = $now;
                            // Send final evaluation to RDR and store resulting id
                            $fhir = $evaluationService->getFhir($now);
                            if ($rdrEvalId = $app['pmi.drc.participants']->createEvaluation($participant->id, $fhir)) {
                                $dbArray['rdr_id'] = $rdrEvalId;
                            }
                        } else {
                            foreach ($errors as $field) {
                                if (is_array($field)) {
                                    list($field, $replicate) = $field;
                                    $evaluationForm->get($field)->get($replicate)->addError(new FormError('Please complete or add protocol modification.'));
                                } else {
                                    $evaluationForm->get($field)->addError(new FormError('Please complete or add protocol modification.'));
                                }
                            }
                            $evaluationForm->addError(new FormError('Physical measurements are incomplete and cannot be finalized. Please complete the missing values below or specify a protocol modification if applicable.'));
                        }
                    }
                    if (!$evaluation) {
                        $dbArray['user_id'] = $app->getUser()->getId();
                        $dbArray['site'] = $app->getSiteId();
                        $dbArray['participant_id'] = $participant->id;
                        $dbArray['created_ts'] = $dbArray['updated_ts'];
                        if ($evalId = $app['em']->getRepository('evaluations')->insert($dbArray)) {
                            $app->log(Log::EVALUATION_CREATE, $evalId);
                            $app->addFlashNotice('Physical measurements saved');
                            return $app->redirectToRoute('evaluation', [
                                'participantId' => $participant->id,
                                'evalId' => $evalId
                            ]);
                        } else {
                            $app->addFlashError('Failed to create new physical measurements');
                        }
                    } else {
                        if ($app['em']->getRepository('evaluations')->update($evalId, $dbArray)) {
                            $app->log(Log::EVALUATION_EDIT, $evalId);
                            $app->addFlashNotice('Physical measurements saved');

                            // If finalization failed, values are still saved, but do not redirect
                            // so that errors can be displayed
                            if ($evaluationForm->isValid()) {
                                return $app->redirectToRoute('evaluation', [
                                    'participantId' => $participant->id,
                                    'evalId' => $evalId
                                ]);
                            }
                        } else {
                            $app->addFlashError('Failed to update physical measurements');
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
            'schema' => $evaluationService->getAssociativeSchema(),
            'warnings' => $evaluationService->getWarnings(),
            'conversions' => $evaluationService->getConversions(),
            'latestVersion' => $evaluationService::CURRENT_VERSION
        ]);
    }
}
