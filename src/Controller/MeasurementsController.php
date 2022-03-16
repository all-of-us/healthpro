<?php

namespace App\Controller;

use App\Entity\Measurement;
use App\Entity\User;
use App\Form\MeasurementBloodDonorCheckType;
use App\Form\MeasurementModifyType;
use App\Form\MeasurementRevertType;
use App\Form\MeasurementType;
use App\Model\Measurement\Fhir;
use App\Service\EnvironmentService;
use App\Service\LoggerService;
use App\Service\MeasurementService;
use App\Service\ParticipantSummaryService;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;
use App\Audit\Log;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MeasurementsController extends BaseController
{
    protected $em;
    protected $measurementService;
    protected $participantSummaryService;
    protected $loggerService;
    protected $siteService;
    protected $params;

    public function __construct(
        EntityManagerInterface $em,
        MeasurementService $measurementService,
        ParticipantSummaryService $participantSummaryService,
        LoggerService $loggerService,
        SiteService $siteService,
        ParameterBagInterface $params
    ) {
        $this->em = $em;
        $this->measurementService = $measurementService;
        $this->participantSummaryService = $participantSummaryService;
        $this->loggerService = $loggerService;
        $this->siteService = $siteService;
        $this->params = $params;
    }

    /**
     * @Route("/participant/{participantId}/measurements/{measurementId}", name="measurement", defaults={"measurementId": null})
     * @Route("/read/participant/{participantId}/measurements/{measurementId}", name="read_measurement")
     */
    public function measurementsAction($participantId, $measurementId, Request $request)
    {
        $participant = $this->participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $type = $request->query->get('type');
        if (!$this->measurementService->canEdit(
            $measurementId,
            $participant
        ) || $this->siteService->isTestSite() || ($participant->activityStatus === 'deactivated' && empty($measurementId))) {
            throw $this->createAccessDeniedException();
        }
        if ($measurementId) {
            $measurement = $this->em->getRepository(Measurement::class)->getMeasurement($measurementId, $participantId);
            if (!$measurement) {
                throw $this->createNotFoundException('Physical Measurement not found.');
            }
            $this->measurementService->load($measurement, $type);
            $measurement->canCancel = $measurement->canCancel();
            $measurement->canRestore = $measurement->canRestore();
            $measurement->reasonDisplayText = $measurement->getReasonDisplayText();
        } else {
            $measurement = new Measurement();
            $this->measurementService->load($measurement, $type);
            if ($measurement->isBloodDonorForm() && $request->query->get('wholeblood')) {
                $measurement->setFieldData((object)[
                    'weight-protocol-modification' => 'whole-blood-donor'
                ]);
            }
        }
        $showAutoModification = false;
        $measurementsForm = $this->get('form.factory')->createNamed('form', MeasurementType::class, $measurement->getFieldData(), [
            'schema' => $measurement->getSchema(),
            'locked' => $measurement->getFinalizedTs() || $this->isReadOnly() ? true : false
        ]);
        $measurementsForm->handleRequest($request);
        if ($measurementsForm->isSubmitted()) {
            // Get current logged in user entity
            $userRepository = $this->em->getRepository(User::class);
            $currentUser = $userRepository->find($this->getUser()->getId());

            // Check if PMs are cancelled
            if ($measurement->isEvaluationCancelled()) {
                throw $this->createAccessDeniedException();
            }
            // Check if finalized_ts is set and rdr_id is empty
            if (!$measurement->isEvaluationFailedToReachRDR()) {
                if ($measurementsForm->isValid()) {
                    if ($measurement->isBloodDonorForm()) {
                        $measurement->addBloodDonorProtocolModificationForRemovedFields();
                        if ($request->request->has('finalize') && (!$measurement || empty($measurement->getRdrId()))) {
                            $measurement->addBloodDonorProtocolModificationForBloodPressure();
                        }
                    }
                    $measurement->setFieldData($measurementsForm->getData());
                    if (empty($measurement->getVersion())) {
                        $measurement->setVersion($this->measurementService->getCurrentVersion($type));
                    }
                    $measurement->setData(json_encode($measurement->getFieldData()));
                    $now = new \DateTime();
                    $measurement->setUpdatedTs($now);
                    if ($request->request->has('finalize') && (!$measurement || empty($measurement->getRdrId()))) {
                        $errors = $measurement->getFinalizeErrors();
                        if (count($errors) === 0) {
                            $measurement->setFinalizedTs($now);
                            if (!$measurementId) {
                                $measurement->setParticipantId($participant->id);
                                $measurement->setUser($currentUser);
                                $measurement->setSite($this->siteService->getSiteId());
                            }
                            $measurement->setFinalizedUser($currentUser);
                            $measurement->setFinalizedSite($this->siteService->getSiteId());
                            // Send final evaluation to RDR and store resulting id
                            if ($measurement != null && $measurement->getParentId() != null) {
                                $parentEvaluation = $this->em->getRepository(Measurement::class)->find($measurement->getParentId());
                                $fhir = $measurement->getFhir($now, $parentEvaluation->getRdrId());
                            } else {
                                $fhir = $measurement->getFhir($now);
                            }
                            if ($rdrEvalId = $this->measurementService->createMeasurement($participant->id, $fhir)) {
                                $measurement->setRdrId($rdrEvalId);
                                $measurement->setFhirVersion(Fhir::CURRENT_VERSION);
                            } else {
                                $this->addFlash('error', 'Failed to finalize the physical measurements. Please try again');
                                $rdrError = true;
                            }
                        } else {
                            foreach ($errors as $field) {
                                if (is_array($field)) {
                                    list($field, $replicate) = $field;
                                    $measurementsForm->get($field)->get($replicate)->addError(new FormError($measurement->getFormFieldErrorMessage(
                                        $field,
                                        $replicate
                                    )));
                                } else {
                                    $measurementsForm->get($field)->addError(new FormError($measurement->getFormFieldErrorMessage($field)));
                                }
                            }
                            $measurementsForm->addError(new FormError('Physical measurements are incomplete and cannot be finalized. Please complete the missing values below or specify a protocol modification if applicable.'));
                            $showAutoModification = $measurement->canAutoModify();
                        }
                    }
                    if (!$measurementId || $request->request->has('copy')) {
                        $measurement->setUser($currentUser);
                        $measurement->setSite($this->siteService->getSiteId());
                        $measurement->setParticipantId($participant->id);
                        $measurement->setCreatedTs($now);
                        if ($request->request->has('copy')) {
                            $newMeasurement = clone $measurement;
                            $this->measurementService->copyMeasurements($newMeasurement);
                            $this->em->persist($newMeasurement);
                            $this->em->flush();
                            $measurementId = $newMeasurement->getId();
                        } else {
                            $this->em->persist($measurement);
                            $this->em->flush();
                            $measurementId = $measurement->getId();
                        }
                        if ($measurementId) {
                            $this->loggerService->log(Log::EVALUATION_CREATE, $measurementId);
                            if (empty($rdrError)) {
                                $this->addFlash(
                                    'notice',
                                    !$request->request->has('copy') ? 'Physical measurements saved' : 'Physical measurements copied'
                                );
                            }

                            // If finalization failed, new physical measurements are created, but
                            // show errors and auto-modification options on subsequent display
                            if (!$measurementsForm->isValid()) {
                                return $this->redirectToRoute('measurement', [
                                    'participantId' => $participant->id,
                                    'measurementId' => $measurementId,
                                    'showAutoModification' => 1
                                ]);
                            } else {
                                return $this->redirectToRoute('measurement', [
                                    'participantId' => $participant->id,
                                    'measurementId' => $measurementId
                                ]);
                            }
                        } else {
                            $this->addFlash('error', 'Failed to create new physical measurements');
                        }
                    } else {
                        $this->em->persist($measurement);
                        $this->em->flush();
                        $this->loggerService->log(Log::EVALUATION_EDIT, $measurementId);
                        if (empty($rdrError)) {
                            $this->addFlash('notice', 'Physical measurements saved');
                        }

                        // If finalization failed, values are still saved, but do not redirect
                        // so that errors can be displayed
                        if ($measurementsForm->isValid()) {
                            return $this->redirectToRoute('measurement', [
                                'participantId' => $participant->id,
                                'measurementId' => $measurementId
                            ]);
                        }
                    }
                } elseif (count($measurementsForm->getErrors()) == 0) {
                    $measurementsForm->addError(new FormError('Please correct the errors below'));
                }
            } else {
                // Send measurements to RDR
                if ($this->measurementService->sendToRdr()) {
                    $this->addFlash('success', 'Physical measurements finalized');
                } else {
                    $this->addFlash('error', 'Failed to finalize the physical measurements. Please try again');
                }
                return $this->redirectToRoute('measurement', [
                    'participantId' => $participant->id,
                    'measurementId' => $measurementId
                ]);
            }
        } elseif ($request->query->get('showAutoModification')) {
            // if new physical measurements were created and failed to finalize, generate errors post-redirect
            $errors = $measurement->getFinalizeErrors();
            if (count($errors) > 0) {
                foreach ($errors as $field) {
                    if (is_array($field)) {
                        list($field, $replicate) = $field;
                        $measurementsForm->get($field)->get($replicate)->addError(new FormError($measurement->getFormFieldErrorMessage(
                            $field,
                            $replicate
                        )));
                    } else {
                        $measurementsForm->get($field)->addError(new FormError($measurement->getFormFieldErrorMessage($field)));
                    }
                }
                $measurementsForm->addError(new FormError('Physical measurements are incomplete and cannot be finalized. Please complete the missing values below or specify a protocol modification if applicable.'));
                $showAutoModification = $measurement->canAutoModify();
            }
        }
        return $this->render('measurement/index.html.twig', [
            'participant' => $participant,
            'measurement' => $measurement,
            'measurementForm' => $measurementsForm->createView(),
            'schema' => $measurement->getAssociativeSchema(),
            'warnings' => $measurement->getWarnings(),
            'conversions' => $measurement->getConversions(),
            'latestVersion' => $measurement->getLatestFormVersion(),
            'showAutoModification' => $showAutoModification,
            'revertForm' => $this->createForm(MeasurementRevertType::class, null)->createView(),
            'displayEhrBannerMessage' => $this->measurementService->requireEhrModificationProtocol() || $measurement->isEhrProtocolForm(),
            'ehrProtocolBannerMessage' => $this->params->has('ehr_protocol_banner_message') ? $this->params->get('ehr_protocol_banner_message') : '',
            'readOnlyView' => $this->isReadOnly()
        ]);
    }

    /**
     * @Route("/participant/{participantId}/measurements/{measurementId}/modify/{type}", name="measurement_modify", defaults={"measurementId": null})
     */
    public function measurementsModifyAction($participantId, $measurementId, $type, Request $request)
    {
        $participant = $this->participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        if (!$this->measurementService->canEdit($measurementId, $participant) || $this->siteService->isTestSite()) {
            throw $this->createAccessDeniedException();
        }
        $measurement = $this->em->getRepository(Measurement::class)->getMeasurement($measurementId, $participantId);
        if (!$measurement) {
            throw $this->createNotFoundException('Physical Measurement not found.');
        }
        $this->measurementService->load($measurement);
        // Allow cancel for active and restored measurements
        // Allow restore for only cancelled measurements
        if (!in_array($type, [$measurement::EVALUATION_CANCEL, $measurement::EVALUATION_RESTORE])) {
            throw $this->createNotFoundException();
        }
        if (($type === $measurement::EVALUATION_CANCEL && !$measurement->canCancel())
            || ($type === $measurement::EVALUATION_RESTORE && !$measurement->canRestore())) {
            throw $this->createAccessDeniedException();
        }
        $measurementModifyForm = $this->get('form.factory')->createNamed('form', MeasurementModifyType::class, null, [
            'type' => $type
        ]);
        $measurementModifyForm->handleRequest($request);
        if ($measurementModifyForm->isSubmitted()) {
            $measurementModifyData = $measurementModifyForm->getData();
            if ($measurementModifyData['reason'] === 'OTHER' && empty($measurementModifyData['other_text'])) {
                $measurementModifyForm['other_text']->addError(new FormError('Please enter a reason'));
            }
            if ($type === $measurement::EVALUATION_CANCEL && strtolower($measurementModifyData['confirm']) !== $measurement::EVALUATION_CANCEL) {
                $measurementModifyForm['confirm']->addError(new FormError('Please type the word "CANCEL" to confirm'));
            }
            if ($measurementModifyForm->isValid()) {
                if ($measurementModifyData['reason'] === 'OTHER') {
                    $measurementModifyData['reason'] = $measurementModifyData['other_text'];
                }
                $status = true;
                // Cancel/Restore evaluation in RDR if exists
                if (!empty($measurement->getRdrId())) {
                    $status = $this->measurementService->cancelRestoreRdrMeasurement($type, $measurementModifyData['reason']);
                }
                // Create evaluation history
                if ($status && $this->measurementService->createMeasurementHistory($type, $measurementId, $measurementModifyData['reason'])) {
                    $successText = $type === $measurement::EVALUATION_CANCEL ? 'cancelled' : 'restored';
                    $this->addFlash('success', "Physical measurements {$successText}");
                    return $this->redirectToRoute('participant', [
                        'id' => $participantId
                    ]);
                } else {
                    $this->addFlash('error', "Failed to {$type} physical measurements. Please try again.");
                }
            } else {
                $this->addFlash('error', 'Please correct the errors below');
            }
        }
        $measurements = $this->em->getRepository(Measurement::class)->getMeasurementsWithoutParent($participantId);
        return $this->render('measurement/modify.html.twig', [
            'participant' => $participant,
            'measurement' => $measurement,
            'measurements' => $measurements,
            'summary' => $measurement->getSummary(),
            'latestVersion' => $measurement->getLatestFormVersion(),
            'measurementModifyForm' => $measurementModifyForm->createView(),
            'type' => $type,
            'measurementId' => $measurementId
        ]);
    }

    /**
     * @Route("/participant/{participantId}/measurements/{measurementId}/revert", name="measurement_revert")
     */
    public function measurementRevertAction($participantId, $measurementId, Request $request)
    {
        $participant = $this->participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        if (!$this->measurementService->canEdit($measurementId, $participant) || $this->siteService->isTestSite()) {
            throw $this->createAccessDeniedException();
        }
        $measurement = $this->em->getRepository(Measurement::class)->getMeasurement($measurementId, $participantId);
        if (!$measurement) {
            throw $this->createNotFoundException('Physical Measurement not found.');
        }
        $measurementRevertForm = $this->createForm(MeasurementRevertType::class);
        $measurementRevertForm->handleRequest($request);
        if ($measurementRevertForm->isSubmitted() && $measurementRevertForm->isValid()) {
            // Revert Measurement
            if ($this->measurementService->revertMeasurement($measurement)) {
                $this->addFlash('success', 'Physical measurements reverted');
            } else {
                $this->addFlash('error', 'Failed to revert physical measurements. Please try again.');
            }
        }
        return $this->redirectToRoute('participant', [
            'id' => $participantId
        ]);
    }

    /**
     * @Route("/participant/{participantId}/measurements/{measurementId}/summary", name="measurement_summary")
     * @Route("/read/participant/{participantId}/measurements/{measurementId}/summary", name="read_measurement_summary")
     */
    public function measurementsSummaryAction($participantId, $measurementId)
    {
        $participant = $this->participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        if (!$this->measurementService->canEdit($measurementId, $participant) || $this->siteService->isTestSite()) {
            throw $this->createAccessDeniedException();
        }

        $measurement = $this->em->getRepository(Measurement::class)->find($measurementId);
        if (!$measurement) {
            throw $this->createNotFoundException();
        }

        if (!$measurement->getFinalizedTs()) {
            throw $this->createAccessDeniedException();
        }
        $this->measurementService->load($measurement);
        return $this->render('measurement/summary.html.twig', [
            'participant' => $participant,
            'measurement' => $measurement,
            'summary' => $measurement->getSummary()
        ]);
    }

    /**
     * @Route("/participant/{participantId}/measurements/blood/donor/check", name="measurement_blood_donor_check")
     */
    public function measurementBloodDonorCheckAction($participantId, Request $request)
    {
        if (!$this->measurementService->requireBloodDonorCheck()) {
            throw $this->createAccessDeniedException();
        }
        $participant = $this->participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        if (!$participant->status || $this->siteService->isTestSite() || ($participant->activityStatus === 'deactivated')) {
            throw $this->createAccessDeniedException();
        }
        $bloodDonorCheckForm = $this->get('form.factory')->createNamed('form', MeasurementBloodDonorCheckType::class);
        $bloodDonorCheckForm->handleRequest($request);
        if ($bloodDonorCheckForm->isSubmitted() && $bloodDonorCheckForm->isValid()) {
            if ($bloodDonorCheckForm['bloodDonor']->getData() === 'yes') {
                return $this->redirectToRoute('measurement', [
                    'participantId' => $participant->id,
                    'type' => Measurement::BLOOD_DONOR,
                    'wholeblood' => $bloodDonorCheckForm['bloodDonorType']->getData() === 'whole-blood' ? 1 : 0
                ]);
            } else {
                return $this->redirectToRoute('measurement', [
                    'participantId' => $participant->id
                ]);
            }
        }
        return $this->render('measurement/blood-donor-check.html.twig', [
            'participant' => $participant,
            'bloodDonorCheckForm' => $bloodDonorCheckForm->createView()
        ]);
    }

    /**
     * @Route("/participant/{participantId}/measurements/{measurementId}/fhir", name="measurement_fhir")
     * For debugging generated FHIR bundle - only allowed for admins or in local dev
     */
    public function measurementFhirAction($participantId, $measurementId, Request $request, EnvironmentService $env)
    {
        $isTest = $request->query->has('test');
        if (!$this->isGranted('ROLE_ADMIN') && !$env->isLocal()) {
            throw $this->createAccessDeniedException();
        }
        $participant = $this->participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $measurement = $this->em->getRepository(Measurement::class)->find($measurementId);
        if (!$measurement) {
            throw $this->createNotFoundException();
        }
        if ($isTest) {
            $measurement->setSite('test-site1');
            $measurement->setFinalizedSite('test-site2');
            $measurement->setParticipantId('P10000001');
            $measurement->setFinalizedTs(new \DateTime('2017-01-01', new \DateTimeZone('UTC')));
            $measurement->setFinalizedUser($measurement->getUser());
        }
        $this->measurementService->load($measurement);
        if ($measurement->getFinalizedTs()) {
            $date = $measurement->getFinalizedTs();
        } else {
            $date = new \DateTime();
        }
        $parentRdrId = null;
        if ($measurement->getParentId()) {
            $parentMeasurement = $this->em->getRepository(Measurement::class)->find($measurement->getParentId());
            if ($parentMeasurement) {
                $parentRdrId = $parentMeasurement->getRdrId();
            }
        }
        $fhir = $measurement->getFhir($date, $parentRdrId);
        if ($isTest) {
            $fhir = \App\Tests\Entity\MeasurementTest::getNormalizedFhir($fhir);
            $response = new JsonResponse($fhir);
            $response->setEncodingOptions(JsonResponse::DEFAULT_ENCODING_OPTIONS | JSON_PRETTY_PRINT);
        } else {
            $response = new JsonResponse($fhir);
            $response->setEncodingOptions(JsonResponse::DEFAULT_ENCODING_OPTIONS | JSON_PRETTY_PRINT);
        }
        return $response;
    }

    /**
     * @Route("/participant/{participantId}/measurements/{measurementId}/rdr", name="measurement_rdr")
     * For debugging evaluation object pushed to RDR - only allowed for admins or in local dev
     */
    public function measurementRdrAction($participantId, $measurementId, EnvironmentService $env)
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$env->isLocal()) {
            throw $this->createAccessDeniedException();
        }
        $participant = $this->participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $measurement = $this->em->getRepository(Measurement::class)->find($measurementId);
        if (!$measurement) {
            throw $this->createNotFoundException();
        }
        if (!$measurement->getRdrId()) {
            throw $this->createAccessDeniedException('rdr_id is not set');
        }
        $rdrMeasurement = $this->measurementService->getMeasurmeent($participantId, $measurement->getRdrId());
        $response = new JsonResponse($rdrMeasurement);
        $response->setEncodingOptions(JsonResponse::DEFAULT_ENCODING_OPTIONS | JSON_PRETTY_PRINT);
        return $response;
    }
}
