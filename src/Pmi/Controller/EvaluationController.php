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
        ['evaluationSummary', '/participant/{participantId}/measurements/{evalId}/summary'],
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
        $evaluationService->loadFromArray($evaluation, $app);
        if ($evaluation['finalized_ts']) {
            $date = $evaluation['finalized_ts'];
        } else {
            $date = new \DateTime();
        }
        $parentRdrId = null;
        if ($evaluation['parent_id']) {
            $parentEvaluation = $app['em']->getRepository('evaluations')->fetchOneBy([
                'id' => $evaluation['parent_id']
            ]);
            if ($parentEvaluation) {
                $parentRdrId = $parentEvaluation['rdr_id'];
            }
        }
        return $app->jsonPrettyPrint($evaluationService->getFhir($date, $parentRdrId));
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
        return $app->jsonPrettyPrint($rdrEvaluation);
    }

    public function evaluationSummaryAction($participantId, $evalId, Application $app)
    {
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        if (!$participant->status) {
            $app->abort(403);
        }
        
        $evaluation = $app['em']->getRepository('evaluations')->fetchOneBy([
            'id' => $evalId,
            'participant_id' => $participantId
        ]);
        if (!$evaluation) {
            $app->abort(404);
        }

        if (!$evaluation['finalized_ts']) {
            $app->abort(403);
        }
        $evaluationService = new Evaluation();
        $evaluationService->loadFromArray($evaluation, $app);
        return $app['twig']->render('evaluation-summary.html.twig', [
            'participant' => $participant,
            'evaluation' => $evaluation,
            'summary' => $evaluationService->getSummary()
        ]);
    }

    public function evaluationAction($participantId, $evalId, Application $app, Request $request)
    {
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        if (!$participant->status) {
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
            $evaluationService->loadFromArray($evaluation, $app);
        } else {
            $evaluation = null;
        }
        $showAutoModification = false;

        $evaluationForm = $evaluationService->getForm($app['form.factory']);
        $evaluationForm->handleRequest($request);
        if ($evaluationForm->isSubmitted()) {
            if ($evaluationForm->isValid()) {
                $evaluationService->setData($evaluationForm->getData());
                $dbArray = $evaluationService->toArray();
                $now = new \DateTime();
                $dbArray['updated_ts'] = $now;
                if ($request->request->has('finalize') && (!$evaluation || empty($evaluation['finalized_ts']))) {
                    $errors = $evaluationService->getFinalizeErrors();
                    if (count($errors) === 0) {
                        $dbArray['finalized_ts'] = $now;
                        if (!$evaluation) {
                            $dbArray['participant_id'] = $participant->id;
                            $dbArray['user_id'] = $app->getUser()->getId();
                            $dbArray['site'] = $app->getSiteId();
                        }
                        $dbArray['finalized_user_id'] = $app->getUser()->getId();
                        $dbArray['finalized_site'] = $app->getSiteId();
                        // Send final evaluation to RDR and store resulting id
                        if ($evaluation != null && $evaluation['parent_id'] != null) {
                            $parentEvaluation = $app['em']->getRepository('evaluations')->fetchOneBy([
                                'id' => $evaluation['parent_id']
                            ]);
                            $fhir = $evaluationService->getFhir($now, $parentEvaluation['rdr_id']);
                        } else {
                            $evaluationService->loadFromArray($dbArray, $app);
                            $fhir = $evaluationService->getFhir($now);
                        }
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
                        $showAutoModification = true;
                    }
                }
                if (!$evaluation || $request->request->has('copy')) {
                    $dbArray['user_id'] = $app->getUser()->getId();
                    $dbArray['site'] = $app->getSiteId();
                    $dbArray['participant_id'] = $participant->id;
                    $dbArray['created_ts'] = $dbArray['updated_ts'];
                    if ($request->request->has('copy')) {
                        $dbArray['parent_id'] = $evaluation['id'];
                        $dbArray['created_ts'] = $evaluation['created_ts'];
                    }
                    if ($evalId = $app['em']->getRepository('evaluations')->insert($dbArray)) {
                        $app->log(Log::EVALUATION_CREATE, $evalId);
                        $app->addFlashNotice(!$request->request->has('copy') ? 'Physical measurements saved': 'Physical measurements copied');

                        // If finalization failed, new physical measurements are created, but
                        // show errors and auto-modification options on subsequent display
                        if (!$evaluationForm->isValid()) {
                            return $app->redirectToRoute('evaluation', [
                                'participantId' => $participant->id,
                                'evalId' => $evalId,
                                'showAutoModification' => 1
                            ]);
                        } else {
                            return $app->redirectToRoute('evaluation', [
                                'participantId' => $participant->id,
                                'evalId' => $evalId
                            ]);
                        }
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
        } elseif ($request->query->get('showAutoModification')) {
            // if new physical measurements were created and failed to finalize, generate errors post-redirect
            $errors = $evaluationService->getFinalizeErrors();
            if (count($errors) > 0) {
                foreach ($errors as $field) {
                    if (is_array($field)) {
                        list($field, $replicate) = $field;
                        $evaluationForm->get($field)->get($replicate)->addError(new FormError('Please complete or add protocol modification.'));
                    } else {
                        $evaluationForm->get($field)->addError(new FormError('Please complete or add protocol modification.'));
                    }
                }
                $evaluationForm->addError(new FormError('Physical measurements are incomplete and cannot be finalized. Please complete the missing values below or specify a protocol modification if applicable.'));
                $showAutoModification = true;
            }
        }

        return $app['twig']->render('evaluation.html.twig', [
            'participant' => $participant,
            'evaluation' => $evaluation,
            'evaluationForm' => $evaluationForm->createView(),
            'schema' => $evaluationService->getAssociativeSchema(),
            'warnings' => $evaluationService->getWarnings(),
            'conversions' => $evaluationService->getConversions(),
            'latestVersion' => $evaluationService::CURRENT_VERSION,
            'showAutoModification' => $showAutoModification
        ]);
    }
}
