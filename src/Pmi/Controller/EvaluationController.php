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
        ['evaluationRdr', '/participant/{participantId}/measurements/{evalId}/rdr.json'],
        ['evaluationModify', '/participant/{participantId}/measurements/{evalId}/modify/{type}', ['method' => 'GET|POST']],
        ['evaluationRevert', '/participant/{participantId}/evaluation/{evalId}/revert', ['method' => 'POST']],
        ['evaluationBloodDonorCheck', '/participant/{participantId}/measurements/blood/donor/check', ['method' => 'GET|POST']],
    ];

    /* For debugging generated FHIR bundle - only allowed in dev */
    public function evaluationFhirAction($participantId, $evalId, Application $app, Request $request)
    {
        $isTest = $request->query->has('test');
        if (!$app->isLocal()) {
            $app->abort(404);
        }
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        $evaluationService = new Evaluation($app);
        $evaluation = $app['em']->getRepository('evaluations')->fetchOneBy([
            'id' => $evalId,
            'participant_id' => $participantId
        ]);
        if (!$evaluation) {
            $app->abort(404);
        }
        if ($isTest) {
            $evaluation['site'] = 'test-site1';
            $evaluation['finalized_site'] = 'test-site2';
            $evaluation['participant_id'] = 'P10000001';
            $evaluation['finalized_ts'] = new \DateTime('2017-01-01', new \DateTimeZone('UTC'));
            $evaluation['finalized_user_id'] = $evaluation['user_id'];
        }
        $evaluationService->loadFromArray($evaluation);
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
        if ($isTest) {
            $fhir = $evaluationService->getFhir($date, $parentRdrId);
            $fhir = \Tests\Pmi\Evaluation\EvaluationTest::getNormalizedFhir($fhir);
            return $app->jsonPrettyPrint($fhir);
        } else {
            return $app->jsonPrettyPrint($evaluationService->getFhir($date, $parentRdrId));
        }
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
        $evaluationService = new Evaluation($app);
        if (!$evaluationService->canEdit($evalId, $participant) || $app->isTestSite()) {
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
        $evaluationService->loadFromArray($evaluation);
        return $app['twig']->render('evaluation-summary.html.twig', [
            'participant' => $participant,
            'evaluation' => $evaluation,
            'summary' => $evaluationService->getSummary()
        ]);
    }

    public function evaluationBloodDonorCheckAction($participantId, Application $app, Request $request)
    {
        $evaluationService = new Evaluation($app);
        if (!$evaluationService->requireBloodDonorCheck()) {
            $app->abort(403);
        }
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        if (!$participant->status || $app->isTestSite() || ($participant->activityStatus === 'deactivated')) {
            $app->abort(403);
        }
        $bloodDonorCheckForm = $evaluationService->getBloodDonorCheckForm($app['form.factory']);
        $bloodDonorCheckForm->handleRequest($request);
        if ($bloodDonorCheckForm->isSubmitted() && $bloodDonorCheckForm->isValid()) {
            if ($bloodDonorCheckForm['bloodDonor']->getData() === 'yes') {
                return $app->redirectToRoute('evaluation', [
                    'participantId' => $participant->id,
                    'type' => $evaluationService::DIVERSION_POUCH
                ]);
            } else {
                return $app->redirectToRoute('evaluation', [
                    'participantId' => $participant->id
                ]);
            }
        }
        return $app['twig']->render('evaluation-blood-donor-check.html.twig', [
            'participant' => $participant,
            'bloodDonorCheckForm' => $bloodDonorCheckForm->createView()
        ]);
    }

    public function evaluationAction($participantId, $evalId, Application $app, Request $request)
    {
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        $type = $request->query->get('type');
        $evaluationService = new Evaluation($app, $type);
        if (!$evaluationService->canEdit($evalId, $participant) || $app->isTestSite() || ($participant->activityStatus === 'deactivated' && empty($evalId))) {
            $app->abort(403);
        }
        if ($evalId) {
            $evaluation = $evaluationService->getEvaluationWithHistory($evalId, $participantId);
            if (!$evaluation) {
                $app->abort(404);
            }
            $evaluationService->loadFromArray($evaluation);
            $evaluation['canCancel'] = $evaluationService->canCancel();
            $evaluation['canRestore'] = $evaluationService->canRestore();
            $evaluation['reasonDisplayText'] = $evaluationService->getReasonDisplayText();
        } else {
            $evaluation = null;
        }
        $showAutoModification = false;

        $evaluationForm = $evaluationService->getForm($app['form.factory']);
        $evaluationForm->handleRequest($request);
        if ($evaluationForm->isSubmitted()) {
            // Check if PMs are cancelled
            if ($evaluationService->isEvaluationCancelled()) {
                $app->abort(403);
            }
            // Check if finalized_ts is set and rdr_id is empty
            if (!$evaluationService->isEvaluationFailedToReachRDR()) {
                if ($evaluationForm->isValid()) {
                    if ($evaluationService->isDiversionPouchForm()) {
                        $evaluationService->addBloodDonorProtocolModificationForRemovedFields();
                        if ($request->request->has('finalize') && (!$evaluation || empty($evaluation['rdr_id']))) {
                            $evaluationService->addBloodDonorProtocolModificationForBloodPressure(1);
                        }
                    } elseif ($evaluationService->isEhrProtocolForm()) {
                        $evaluationService->addEhrProtocolModifications();
                    }
                    $evaluationService->setData($evaluationForm->getData());
                    $dbArray = $evaluationService->toArray();
                    $now = new \DateTime();
                    $dbArray['updated_ts'] = $now;
                    if ($request->request->has('finalize') && (!$evaluation || empty($evaluation['rdr_id']))) {
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
                                if (!$evaluation) {
                                    $evaluationService->loadFromArray($dbArray);
                                }
                                $fhir = $evaluationService->getFhir($now);
                            }
                            if ($rdrEvalId = $app['pmi.drc.participants']->createEvaluation($participant->id, $fhir)) {
                                $dbArray['rdr_id'] = $rdrEvalId;
                                $dbArray['fhir_version'] = \Pmi\Evaluation\Fhir::CURRENT_VERSION;
                            } else {
                                $app->addFlashError('Failed to finalize the physical measurements. Please try again');
                                $rdrError = true;
                            }
                        } else {
                            foreach ($errors as $field) {
                                if (is_array($field)) {
                                    list($field, $replicate) = $field;
                                    $evaluationForm->get($field)->get($replicate)->addError(new FormError($evaluationService->getFormFieldErrorMessage($field, $replicate)));
                                } else {
                                    $evaluationForm->get($field)->addError(new FormError($evaluationService->getFormFieldErrorMessage($field)));
                                }
                            }
                            $evaluationForm->addError(new FormError('Physical measurements are incomplete and cannot be finalized. Please complete the missing values below or specify a protocol modification if applicable.'));
                            $showAutoModification = $evaluationService->canAutoModify();
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
                            if (empty($rdrError)) {
                                $app->addFlashNotice(!$request->request->has('copy') ? 'Physical measurements saved': 'Physical measurements copied');
                            }

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
                            if (empty($rdrError)) {
                                $app->addFlashNotice('Physical measurements saved');
                            }

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
                } elseif (count($evaluationForm->getErrors()) == 0) {
                    $evaluationForm->addError(new FormError('Please correct the errors below'));
                }
            } else {
                // Send evaluation to RDR
                if ($evaluationService->sendToRdr()) {
                    $app->addFlashSuccess('Physical measurements finalized');
                } else {
                    $app->addFlashError('Failed to finalize the physical measurements. Please try again');
                }
                return $app->redirectToRoute('evaluation', [
                    'participantId' => $participant->id,
                    'evalId' => $evalId
                ]);
            }
        } elseif ($request->query->get('showAutoModification')) {
            // if new physical measurements were created and failed to finalize, generate errors post-redirect
            $errors = $evaluationService->getFinalizeErrors();
            if (count($errors) > 0) {
                foreach ($errors as $field) {
                    if (is_array($field)) {
                        list($field, $replicate) = $field;
                        $evaluationForm->get($field)->get($replicate)->addError(new FormError($evaluationService->getFormFieldErrorMessage($field, $replicate)));
                    } else {
                        $evaluationForm->get($field)->addError(new FormError($evaluationService->getFormFieldErrorMessage($field)));
                    }
                }
                $evaluationForm->addError(new FormError('Physical measurements are incomplete and cannot be finalized. Please complete the missing values below or specify a protocol modification if applicable.'));
                $showAutoModification = $evaluationService->canAutoModify();
            }
        }

        return $app['twig']->render('evaluation.html.twig', [
            'participant' => $participant,
            'evaluation' => $evaluation,
            'evaluationForm' => $evaluationForm->createView(),
            'schema' => $evaluationService->getAssociativeSchema(),
            'warnings' => $evaluationService->getWarnings(),
            'conversions' => $evaluationService->getConversions(),
            'latestVersion' => $evaluationService->getLatestFormVersion(),
            'showAutoModification' => $showAutoModification,
            'revertForm' => $evaluationService->getEvaluationRevertForm()->createView(),
            'requireEhrModificationProtocol' => $evaluationService->requireEhrModificationProtocol(),
            'ehrProtocolBannerMessage' => $app->getConfig('ehr_protocol_banner_message')
        ]);
    }

    public function evaluationModifyAction($participantId, $evalId, $type, Application $app, Request $request)
    {
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        $evaluationService = new Evaluation($app);
        if (!$evaluationService->canEdit($evalId, $participant) || $app->isTestSite()) {
            $app->abort(403);
        }
        $evaluation = $evaluationService->getEvaluationWithHistory($evalId, $participantId);
        if (!$evaluation) {
            $app->abort(404);
        }
        $evaluationService->loadFromArray($evaluation);
        // Allow cancel for active and restored evaluations
        // Allow restore for only cancelled evaluations
        if (!in_array($type, [$evaluationService::EVALUATION_CANCEL, $evaluationService::EVALUATION_RESTORE])) {
            $app->abort(404);
        }
        if (($type === $evaluationService::EVALUATION_CANCEL && !$evaluationService->canCancel())
            || ($type === $evaluationService::EVALUATION_RESTORE && !$evaluationService->canRestore())) {
            $app->abort(403);
        }
        $evaluationModifyForm = $evaluationService->getEvaluationModifyForm($type);
        $evaluationModifyForm->handleRequest($request);
        if ($evaluationModifyForm->isSubmitted()) {
            $evaluationModifyData = $evaluationModifyForm->getData();
            if ($evaluationModifyData['reason'] === 'OTHER' && empty($evaluationModifyData['other_text'])) {
                $evaluationModifyForm['other_text']->addError(new FormError('Please enter a reason'));
            }
            if ($type === $evaluationService::EVALUATION_CANCEL && strtolower($evaluationModifyData['confirm']) !== $evaluationService::EVALUATION_CANCEL) {
                $evaluationModifyForm['confirm']->addError(new FormError('Please type the word "CANCEL" to confirm'));
            }
            if ($evaluationModifyForm->isValid()) {
                if ($evaluationModifyData['reason'] === 'OTHER') {
                    $evaluationModifyData['reason'] = $evaluationModifyData['other_text'];
                }
                $status = true;
                // Cancel/Restore evaluation in RDR if exists
                if (!empty($evaluation['rdr_id'])) {
                    $status = $evaluationService->cancelRestoreRdrEvaluation($type, $evaluationModifyData['reason']);
                }
                // Create evaluation history
                if ($status && $evaluationService->createEvaluationHistory($type, $evalId, $evaluationModifyData['reason'])) {
                    $successText = $type === $evaluationService::EVALUATION_CANCEL ? 'cancelled' : 'restored';
                    $app->addFlashSuccess("Physical measurements {$successText}");
                    return $app->redirectToRoute('participant', [
                        'id' => $participantId
                    ]);
                } else {
                    $app->addFlashError("Failed to {$type} physical measurements. Please try again.");
                }
            } else {
                $app->addFlashError('Please correct the errors below');
            }
        }
        $evaluations = $app['em']->getRepository('evaluations')->getEvaluationsWithHistory($participantId);
        return $app['twig']->render('evaluation-modify.html.twig', [
            'participant' => $participant,
            'evaluation' => $evaluation,
            'evaluations' => $evaluations,
            'summary' => $evaluationService->getSummary(),
            'latestVersion' => $evaluationService::CURRENT_VERSION,
            'evaluationModifyForm' => $evaluationModifyForm->createView(),
            'type' => $type,
            'evalId' => $evalId
        ]);
    }

    public function evaluationRevertAction($participantId, $evalId, Application $app, Request $request)
    {
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        $evaluationService = new Evaluation($app);
        if (!$evaluationService->canEdit($evalId, $participant) || $app->isTestSite()) {
            $app->abort(403);
        }
        $evaluation = $evaluationService->getEvaluationWithHistory($evalId, $participantId);
        if (!$evaluation) {
            $app->abort(404);
        }
        $evaluationService = new Evaluation($app);
        $evaluationRevertForm = $evaluationService->getEvaluationRevertForm();
        $evaluationRevertForm->handleRequest($request);
        if ($evaluationRevertForm->isSubmitted() && $evaluationRevertForm->isValid()) {
            // Revert Evaluation
            if ($evaluationService->revertEvaluation($evalId)) {
                $app->addFlashSuccess('Physical measurements reverted');
            } else {
                $app->addFlashError('Failed to revert physical measurements. Please try again.');
            }
        }
        return $app->redirectToRoute('participant', [
            'id' => $participantId
        ]);
    }
}
