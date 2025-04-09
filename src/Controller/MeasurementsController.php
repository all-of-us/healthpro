<?php

namespace App\Controller;

use App\Audit\Log;
use App\Entity\Measurement;
use App\Entity\User;
use App\Form\MeasurementBloodDonorCheckType;
use App\Form\MeasurementModifyType;
use App\Form\MeasurementRevertType;
use App\Form\MeasurementType;
use App\Form\PediatricMeasurementType;
use App\Model\Measurement\Fhir;
use App\Service\EnvironmentService;
use App\Service\HelpService;
use App\Service\LoggerService;
use App\Service\MeasurementService;
use App\Service\Ppsc\PpscApiService;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MeasurementsController extends BaseController
{
    protected $measurementService;
    protected $loggerService;
    protected $siteService;
    protected $params;
    protected $helpService;
    protected PpscApiService $ppscApiService;

    public function __construct(
        EntityManagerInterface $em,
        MeasurementService $measurementService,
        LoggerService $loggerService,
        SiteService $siteService,
        ParameterBagInterface $params,
        HelpService $helpService,
        PpscApiService $ppscApiService,
    ) {
        parent::__construct($em);
        $this->measurementService = $measurementService;
        $this->loggerService = $loggerService;
        $this->siteService = $siteService;
        $this->params = $params;
        $this->helpService = $helpService;
        $this->ppscApiService = $ppscApiService;
    }

    #[Route(path: '/ppsc/participant/{participantId}/measurements/{measurementId}', name: 'measurement', defaults: ['measurementId' => null])]
    #[Route(path: '/read/participant/{participantId}/measurements/{measurementId}', name: 'read_measurement', methods: ['GET'])]
    public function measurementsAction($participantId, $measurementId, Request $request)
    {
        $participant = $this->ppscApiService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $type = $request->query->get('type');
        if ($participant->isPediatric && $participant->pediatricMeasurementsVersionType && !$type) {
            $type = $participant->pediatricMeasurementsVersionType;
        }
        if ($measurementId) {
            $measurement = $this->em->getRepository(Measurement::class)->getMeasurement($measurementId, $participantId);
            if (!$measurement) {
                throw $this->createNotFoundException('Physical Measurement not found.');
            }
            $this->measurementService->load($measurement, $participant, $type);
            $measurement->canCancel = $measurement->canCancel();
            $measurement->canRestore = $measurement->canRestore();
            $measurement->reasonDisplayText = $measurement->getReasonDisplayText();
        } else {
            $measurement = new Measurement();
            $this->measurementService->load($measurement, $participant, $type);
            if ($measurement->isPediatricForm()) {
                $measurement->setAgeInMonths($participant->ageInMonths);
            }
            if ($measurement->isBloodDonorForm() && $request->query->get('wholeblood')) {
                $measurement->setFieldData((object) [
                    'weight-protocol-modification' => 'whole-blood-donor'
                ]);
            }
        }
        if ($measurement->isPediatricForm()) {
            $growthChartsData = $measurement->getGrowthCharts();
        }
        $showAutoModification = false;
        $formType = $measurement->isPediatricForm() ? PediatricMeasurementType::class : MeasurementType::class;
        $measurementsForm = $this->get('form.factory')->createNamed('form', $formType, $measurement->getFieldData(), [
            'schema' => $measurement->getSchema(),
            'locked' => $measurement->getFinalizedTs() || $this->isReadOnly() ||
                $this->measurementService->inactiveSiteFormDisabled() || $measurement->isEvaluationCancelled()
        ]);
        $measurementsForm->handleRequest($request);
        if ($measurementsForm->isSubmitted()) {
            // Get current logged in user entity
            $userRepository = $this->em->getRepository(User::class);
            $currentUser = $userRepository->find($this->getSecurityUser()->getId());

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
                            if (!$measurementsForm->isValid()) { // @phpstan-ignore-line
                                return $this->redirectToRoute('measurement', [
                                    'participantId' => $participant->id,
                                    'measurementId' => $measurementId,
                                    'showAutoModification' => 1
                                ]);
                            }
                            if ($measurement->isPediatricForm() && $measurement->isWeightOnlyPediatricForm() && !isset($newMeasurement)) {
                                return $this->redirectToRoute('order_check_pediatric', [
                                    'participantId' => $participant->id
                                ]);
                            } elseif ($measurement->isPediatricForm() && $measurement->isWeightOnlyPediatricForm() && isset($newMeasurement)) {
                                return $this->redirectToRoute('measurement', [
                                    'participantId' => $participant->id,
                                    'measurementId' => $measurementId,
                                    'type' => 'peds-weight'
                                ]);
                            }
                            return $this->redirectToRoute('measurement', [
                                'participantId' => $participant->id,
                                'measurementId' => $measurementId
                            ]);
                        }
                        $this->addFlash('error', 'Failed to create new physical measurements');
                    } else {
                        $this->em->persist($measurement);
                        $this->em->flush();
                        $this->loggerService->log(Log::EVALUATION_EDIT, $measurementId);
                        if (empty($rdrError)) {
                            $this->addFlash('notice', 'Physical measurements saved');
                        }
                        if ($measurement->isPediatricForm() && $measurement->isWeightOnlyPediatricForm()) {
                            return $this->redirectToRoute('order_check_pediatric', [
                                'participantId' => $participant->id
                            ]);
                        }
                        // If finalization failed, values are still saved, but do not redirect
                        // so that errors can be displayed
                        if ($measurementsForm->isValid()) { // @phpstan-ignore-line
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
            'recordUserValues' => $measurement->getRecordUserValues(),
            'latestVersion' => $measurement->getLatestFormVersion(),
            'showAutoModification' => $showAutoModification,
            'revertForm' => $this->createForm(MeasurementRevertType::class, null)->createView(),
            'displayEhrBannerMessage' => $this->measurementService->requireEhrModificationProtocol() || $measurement->isEhrProtocolForm(),
            'ehrProtocolBannerMessage' => $this->params->has('ehr_protocol_banner_message') ? $this->params->get('ehr_protocol_banner_message') : '',
            'readOnlyView' => $this->isReadOnly(),
            'sopDocumentTitles' => $this->helpService->getDocumentTitlesList(),
            'inactiveSiteFormDisabled' => $this->measurementService->inactiveSiteFormDisabled(),
            'weightForAgeCharts' => $growthChartsData['weightForAgeCharts'] ?? [],
            'weightForLengthCharts' => $growthChartsData['weightForLengthCharts'] ?? [],
            'heightForAgeCharts' => $growthChartsData['heightForAgeCharts'] ?? [],
            'headCircumferenceForAgeCharts' => $growthChartsData['headCircumferenceForAgeCharts'] ?? [],
            'bmiForAgeCharts' => $growthChartsData['bmiForAgeCharts'] ?? [],
            'bpSystolicHeightPercentileChart' => $growthChartsData['bpSystolicHeightPercentileChart'] ?? [],
            'bpDiastolicHeightPercentileChart' => $growthChartsData['bpDiastolicHeightPercentileChart'] ?? [],
            'heartRateAgeCharts' => $growthChartsData['heartRateAgeCharts'] ?? [],
            'zScoreCharts' => $growthChartsData['zScoreCharts'] ?? []
        ]);
    }

    #[Route(path: '/ppsc/participant/{participantId}/measurements/{measurementId}/modify/{type}', name: 'measurement_modify', defaults: ['measurementId' => null])]
    public function measurementsModifyAction($participantId, $measurementId, $type, Request $request)
    {
        $participant = $this->ppscApiService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $measurement = $this->em->getRepository(Measurement::class)->getMeasurement($measurementId, $participantId);
        if (!$measurement) {
            throw $this->createNotFoundException('Physical Measurement not found.');
        }
        $this->measurementService->load($measurement, $participant);
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
                }
                $this->addFlash('error', "Failed to {$type} physical measurements. Please try again.");
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

    #[Route(path: '/ppsc/participant/{participantId}/measurements/{measurementId}/revert', name: 'measurement_revert')]
    public function measurementRevertAction($participantId, $measurementId, Request $request)
    {
        $participant = $this->ppscApiService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
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

    #[Route(path: '/ppsc/participant/{participantId}/measurements/{measurementId}/summary', name: 'measurement_summary')]
    #[Route(path: '/read/participant/{participantId}/measurements/{measurementId}/summary', name: 'read_measurement_summary')]
    public function measurementsSummaryAction($participantId, $measurementId)
    {
        $participant = $this->ppscApiService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }

        $measurement = $this->em->getRepository(Measurement::class)->find($measurementId);
        if (!$measurement) {
            throw $this->createNotFoundException();
        }

        if (!$measurement->getFinalizedTs()) {
            throw $this->createAccessDeniedException();
        }
        $this->measurementService->load($measurement, $participant);
        return $this->render('measurement/summary.html.twig', [
            'participant' => $participant,
            'measurement' => $measurement,
            'summary' => $measurement->getSummaryView()
        ]);
    }

    #[Route(path: '/ppsc/participant/{participantId}/measurements/blood/donor/check', name: 'measurement_blood_donor_check')]
    public function measurementBloodDonorCheckAction($participantId, Request $request)
    {
        if (!$this->measurementService->requireBloodDonorCheck()) {
            throw $this->createAccessDeniedException();
        }
        $participant = $this->ppscApiService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
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
            }
            return $this->redirectToRoute('measurement', [
                'participantId' => $participant->id
            ]);
        }
        return $this->render('measurement/blood-donor-check.html.twig', [
            'participant' => $participant,
            'bloodDonorCheckForm' => $bloodDonorCheckForm->createView()
        ]);
    }

    #[Route(path: '/ppsc/participant/{participantId}/measurements/{measurementId}/fhir', name: 'measurement_fhir')]
    public function measurementFhirAction($participantId, $measurementId, Request $request, EnvironmentService $env)
    {
        $isTest = $request->query->has('test');
        if (!$this->isGranted('ROLE_ADMIN') && !$env->isLocal()) {
            throw $this->createAccessDeniedException();
        }
        $participant = $this->ppscApiService->getParticipantById($participantId);
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
        $this->measurementService->load($measurement, $participant);
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

    #[Route(path: '/ppsc/participant/{participantId}/measurements/{measurementId}/rdr', name: 'measurement_rdr')]
    public function measurementRdrAction($participantId, $measurementId, EnvironmentService $env)
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$env->isLocal()) {
            throw $this->createAccessDeniedException();
        }
        $participant = $this->ppscApiService->getParticipantById($participantId);
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
