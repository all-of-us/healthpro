<?php

namespace App\Controller;

use App\Entity\Measurement;
use App\Entity\Site;
use App\Entity\User;
use App\Form\MeasurementModifyType;
use App\Form\MeasurementType;
use App\Service\EnvironmentService;
use App\Service\LoggerService;
use App\Service\MeasurementService;
use App\Service\OrderService;
use App\Service\ParticipantSummaryService;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;
use Pmi\Audit\Log;
use Pmi\Evaluation\Evaluation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;

/**
 * @Route("/s")
 */
class MeasurementsController extends AbstractController
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
     */
    public function measurementsAction($participantId, $measurementId, Request $request)
    {
        $participant = $this->participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $type = $request->query->get('type');
        if (!$this->measurementService->canEdit($measurementId,
                $participant) || $this->siteService->isTestSite() || ($participant->activityStatus === 'deactivated' && empty($measurementId))) {
            throw $this->createAccessDeniedException();
        }
        if ($measurementId) {
            $measurement = $this->em->getRepository(Measurement::class)->find($measurementId);
            if (!$measurement) {
                throw $this->createNotFoundException('Physical Measurement not found.');
            }
            $this->measurementService->load($measurement, $type);
            $measurement->canCancel = $measurement->canCancel();
            $measurement->canRestore = $measurement->canRestore();
            $measurement->reasonDisplayText = $measurement->getReasonDisplayText();
        } else {
            $measurement = new Measurement;
            $this->measurementService->load($measurement, $type);
            if ($measurement->isBloodDonorForm() && $request->query->get('wholeblood')) {
                $measurement->setData((object)[
                    'weight-protocol-modification' => 'whole-blood-donor'
                ]);
            }
        }
        $showAutoModification = false;
        $measurementsForm = $this->get('form.factory')->createNamed('form', MeasurementType::class, $measurement->getFieldData(), [
            'schema' => $measurement->getSchema(),
            'locked' => $measurement->getFinalizedTs() ? true : false
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
                    $measurement->setVersion($this->measurementService->getCurrentVersion($type));
                    $measurement->setData(json_encode($measurement->getFieldData()));
                    $now = new \DateTime();
                    $measurement->setUpdatedTs($now);
                    if ($request->request->has('finalize') && (!$measurement || empty($measurement->getRdrId()))) {
                        $errors = $measurement->getFinalizeErrors();
                        if (count($errors) === 0) {
                            $measurement->setFinalizedTs($now);
                            if (!$measurement) {
                                $measurement->setParticipantId($participant->id);
                                $measurement->setUser($currentUser);
                                $measurement->setSite($this->siteService->getSiteId());
                            }
                            $measurement->setFinalizedUser($currentUser);
                            $measurement->setFinalizedSite($this->siteService->getSiteId());
                            // Send final evaluation to RDR and store resulting id
                            if ($measurement != null && $measurement->getParentId() != null) {
                                $parentEvaluation = $this->em->getRepository(Evaluation::class)->findOneBy([
                                    'id' => $measurement->getParentId()
                                ]);
                                $fhir = $measurement->getFhir($now, $parentEvaluation->getRdrId());
                            } else {
                                $fhir = $measurement->getFhir($now);
                            }
                            if ($rdrEvalId = $this->measurementService->createMeasurement($participant->id, $fhir)) {
                                $measurement->setRdrId($rdrEvalId);
                                $measurement->setFhirVersion(\Pmi\Evaluation\Fhir::CURRENT_VERSION);
                            } else {
                                $this->addFlash('error', 'Failed to finalize the physical measurements. Please try again');
                                $rdrError = true;
                            }
                        } else {
                            foreach ($errors as $field) {
                                if (is_array($field)) {
                                    list($field, $replicate) = $field;
                                    $measurementsForm->get($field)->get($replicate)->addError(new FormError($measurement->getFormFieldErrorMessage($field,
                                        $replicate)));
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
                                $this->addFlash('notice',
                                    !$request->request->has('copy') ? 'Physical measurements saved' : 'Physical measurements copied');
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
                return $this->redirectToRoute('evaluation', [
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
                        $measurementsForm->get($field)->get($replicate)->addError(new FormError($measurement->getFormFieldErrorMessage($field,
                            $replicate)));
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
            'revertForm' => '',
            'displayEhrBannerMessage' => $this->measurementService->requireEhrModificationProtocol() || $measurement->isEhrProtocolForm(),
            'ehrProtocolBannerMessage' => $this->params->has('ehr_protocol_banner_message') ? $this->params->get('ehr_protocol_banner_message') : ''
        ]);
    }

    /**
     * @Route("/participant/{participantId}/measurements/{measurementId}/modify/{type}", name="measurementModify", defaults={"measurementId": null})
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
        $measurement = $this->em->getRepository(Measurement::class)->find($measurementId);
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
        $measurements = $this->em->getRepository(Measurement::class)->findBy(['participantId' => $participantId]);
        return $this->render('measurement/modify.html.twig', [
            'participant' => $participant,
            'measurement' => $measurement,
            'measurements' => $measurements,
            'summary' => $measurement->getSummary(),
            'latestVersion' => $measurement::CURRENT_VERSION,
            'measurementModifyForm' => $measurementModifyForm->createView(),
            'type' => $type,
            'measurementId' => $measurementId
        ]);
    }

    /**
     * @Route("/participant/{participantId}/measurements/{measurementId}/summary", name="measurementsSummary", defaults={"measurementId": null})
     */
    public function measurementsSummaryAction($participantId, $measurementId, $type, Request $request)
    {
        return '';
    }
}