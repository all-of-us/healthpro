<?php

namespace App\Controller;

use App\Audit\Log;
use App\Entity\IdVerification;
use App\Entity\Incentive;
use App\Entity\Measurement;
use App\Entity\Order;
use App\Entity\PatientStatus;
use App\Entity\Problem;
use App\Form\CrossOriginAgreeType;
use App\Form\IdVerificationType;
use App\Form\IncentiveRemoveType;
use App\Form\IncentiveType;
use App\Form\PatientStatusType;
use App\Helper\WorkQueue;
use App\Service\GcsBucketService;
use App\Service\IdVerificationService;
use App\Service\IncentiveService;
use App\Service\LoggerService;
use App\Service\MeasurementService;
use App\Service\ParticipantSummaryService;
use App\Service\PatientStatusService;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class ParticipantDetailsController extends BaseController
{
    private const VALID_CONSENT_TYPES = [
        'consentForStudyEnrollment' => 'consentForStudyEnrollmentFilePath',
        'consentForElectronicHealthRecords' => 'consentForElectronicHealthRecordsFilePath',
        'consentForGenomicsROR' => 'consentForGenomicsRORFilePath',
        'consentForCABoR' => 'consentForCABoRFilePath',
        'reconsentForStudyEnrollmentAuthored' => 'reconsentForStudyEnrollmentFilePath',
        'reconsentForElectronicHealthRecordsAuthored' => 'reconsentForElectronicHealthRecordsFilePath'
    ];
    public function __construct(
        EntityManagerInterface $em
    ) {
        parent::__construct($em);
    }

    #[Route(path: '/participant/{id}', name: 'rdr_participant')]
    #[Route(path: '/read/participant/{id}', name: 'rdr_read_participant', methods: ['GET'])]
    public function participantDetailsAction(
        $id,
        Request $request,
        SessionInterface $session,
        LoggerService $loggerService,
        ParticipantSummaryService $participantSummaryService,
        SiteService $siteService,
        ParameterBagInterface $params,
        PatientStatusService $patientStatusService,
        MeasurementService $measurementService,
        IncentiveService $incentiveService,
        IdVerificationService $idVerificationService
    ) {
        $refresh = $request->query->get('refresh');
        $participant = $participantSummaryService->getParticipantById($id, $refresh);
        $routePrefix = $this->isReadOnly() ? 'read_' : '';
        if ($refresh) {
            return $this->redirectToRoute($routePrefix . 'participant', [
                'id' => $id
            ]);
        }
        if (!$participant) {
            throw $this->createNotFoundException();
        }
        $agreeForm = $this->createForm(CrossOriginAgreeType::class, null);
        $agreeForm->handleRequest($request);
        if ($agreeForm->isSubmitted() && $agreeForm->isValid()) {
            $session->set('agreeCrossOrg_' . $id, true);
            $loggerService->log(Log::CROSS_ORG_PARTICIPANT_AGREE, [
                'participantId' => $id,
                'organization' => $participant->hpoId
            ]);
            // Check for return url and re-direct
            if ($request->query->has('return') && preg_match('/^\/\w/', $request->query->get('return'))) {
                return $this->redirect($request->query->get('return'));
            }
            return $this->redirectToRoute('participant', [
                'id' => $id
            ]);
        }
        $isCrossOrg = $this->isReadOnly() ? false : $participant->hpoId !== $siteService->getSiteAwardee();
        $canViewDetails = !$isCrossOrg && ($participant->status || in_array($participant->statusReason, [
                    'test-participant',
                    'basics',
                    'genomics',
                    'ehr-consent',
                    'program-update',
                    'primary-consent-update',
                    'deceased-pending',
                    'deceased-approved'
                ]));
        $hasNoParticipantAccess = $isCrossOrg && empty($session->get('agreeCrossOrg_' . $id));
        if ($hasNoParticipantAccess) {
            $loggerService->log(Log::CROSS_ORG_PARTICIPANT_ATTEMPT, [
                'participantId' => $id,
                'organization' => $participant->hpoId
            ]);
        } elseif ($isCrossOrg) {
            $loggerService->log(Log::CROSS_ORG_PARTICIPANT_VIEW, [
                'participantId' => $id,
                'organization' => $participant->hpoId
            ]);
        }
        $measurements = $this->em->getRepository(Measurement::class)->getMeasurementsWithoutParent($id);
        $orders = $this->em->getRepository(Order::class)->findBy(['participantId' => $id], ['id' => 'desc']);
        $problems = $this->em->getRepository(Problem::class)->getProblemsWithCommentsCount($id);

        if (empty($participant->cacheTime)) {
            $participant->cacheTime = new \DateTime();
        }
        // Determine cancel route
        $cancelRoute = 'participants';
        if ($request->query->has('return')) {
            if (strpos($request->query->get('return'), '/order/') !== false) {
                $cancelRoute = 'orders';
            }
        }

        // Check if patient status is allowed for this participant
        if ($patientStatusService->hasAccess($participant)) {
            // Patient Status
            $patientStatusRepository = $this->em->getRepository(PatientStatus::class);
            $orgPatientStatusData = $patientStatusRepository->getOrgPatientStatusData($id, $siteService->getSiteOrganization());
            // Determine if comment field is required
            $isCommentRequired = !empty($orgPatientStatusData) ? true : false;
            // Get patient status form
            $patientStatusForm = $this->createForm(PatientStatusType::class, null, ['require_comment' => $isCommentRequired, 'disabled' => $this->isReadOnly()]);
            $patientStatusForm->handleRequest($request);
            if ($patientStatusForm->isSubmitted()) {
                $patientStatus = $this->em->getRepository(PatientStatus::class)->findOneBy([
                    'participantId' => $id,
                    'organization' => $siteService->getSiteOrganization()
                ]);
                if (!empty($patientStatus) && empty($patientStatusForm['comments']->getData())) {
                    $patientStatusForm['comments']->addError(new FormError('Please enter comment'));
                }
                if ($patientStatusForm->isValid()) {
                    $patientStatusId = !empty($patientStatus) ? $patientStatus->getId() : null;
                    $patientStatusService->loadData($id, $patientStatusId, $patientStatusForm->getData());
                    if ($patientStatusService->sendToRdr() && $patientStatusService->saveData()) {
                        $this->addFlash('patient-status-success', 'Patient Status Saved');
                        // Load newly entered data
                        $orgPatientStatusData = $patientStatusRepository->getOrgPatientStatusData($id, $siteService->getSiteOrganization());
                        // Get new form
                        $patientStatusForm = $this->createForm(PatientStatusType::class, null, ['require_comment' => true]);
                    } else {
                        $this->addFlash('patient-status-error', 'Failed to create patient status. Please try again.');
                    }
                } else {
                    $patientStatusForm->addError(new FormError('Please correct the errors below'));
                }
            }
            $orgPatientStatusHistoryData = $patientStatusRepository->getOrgPatientStatusHistoryData($id, $siteService->getSiteOrganization());
            $awardeePatientStatusData = $patientStatusRepository->getAwardeePatientStatusData($id, $siteService->getSiteOrganization());
            $patientStatusForm = $patientStatusForm->createView();
            $canViewPatientStatus = $patientStatusService->hasAccess($participant);
        } else {
            $patientStatusForm = null;
            $orgPatientStatusData = null;
            $orgPatientStatusHistoryData = null;
            $awardeePatientStatusData = null;
            $canViewPatientStatus = false;
        }

        // Incentive Form
        $incentiveForm = $this->createForm(IncentiveType::class, null, ['disabled' => $this->isReadOnly(), 'pediatric_participant' => $participant->isPediatric, 'participant' => $participant]);

        // Id Verification Form
        $idVerificationForm = $this->createForm(IdVerificationType::class, null, ['disabled' => $this->isReadOnly(), 'pediatricParticipant' => $participant->isPediatric]);
        $idVerificationForm->handleRequest($request);
        if ($idVerificationForm->isSubmitted()) {
            if ($idVerificationForm->isValid()) {
                if ($idVerificationService->createIdVerification($id, $idVerificationForm->getData())) {
                    $this->addFlash('id-verification-success', 'ID Verification Saved');
                    return $this->redirectToRoute('participant', ['id' => $id]);
                }
                $this->addFlash('id-verification-error', 'Error saving id verification . Please try again');
            } else {
                $this->addFlash('id-verification-error', 'Invalid form');
            }
        }
        $idVerifications = $this->em->getRepository(IdVerification::class)->findBy(['participantId' => $id], ['id' => 'DESC']);

        // Incentive Delete Form
        $incentiveDeleteForm = $this->createForm(IncentiveRemoveType::class, null);
        $incentiveDeleteForm->handleRequest($request);
        if ($incentiveDeleteForm->isSubmitted() && $incentiveDeleteForm->isValid()) {
            $incentiveId = $incentiveDeleteForm['id']->getData();
            $incentive = $this->em->getRepository(Incentive::class)->findOneBy(['id' => $incentiveId, 'participantId' => $id]);
            if ($incentive) {
                if ($incentive->getSite() !== $siteService->getSiteId()) {
                    throw $this->createAccessDeniedException();
                }
                if ($incentiveService->cancelIncentive($id, $incentive)) {
                    $this->addFlash('incentive-success', 'Incentive Deleted');
                } else {
                    $this->addFlash('incentive-error', 'Error deleting incentive. Please try again');
                }
                $this->redirectToRoute('participant', ['id' => $id]);
            }
        }

        $incentives = $this->em->getRepository(Incentive::class)->getActiveIncentivesIncludingRelated($participant);

        $cacheEnabled = $params->has('rdr_disable_cache') ? !$params->get('rdr_disable_cache') : true;
        $isDVType = $session->get('siteType') === 'dv' ? true : false;
        // Generate url for blood donor check form
        $evaluationUrl = $measurementService->requireBloodDonorCheck() ? 'measurement_blood_donor_check' : 'measurement';
        return $this->render('/participant/details.html.twig', [
            'participant' => $participant,
            'orders' => $orders,
            'measurements' => $measurements,
            'problems' => $problems,
            'hasNoParticipantAccess' => $hasNoParticipantAccess,
            'agreeForm' => $agreeForm->createView(),
            'cacheEnabled' => $cacheEnabled,
            'canViewDetails' => $canViewDetails,
            'samples' => WorkQueue::getParticipantSummarySamples($participant->isPediatric),
            'surveys' => WorkQueue::getParticipantSummarySurveys($participant->isPediatric),
            'samplesAlias' => WorkQueue::$samplesAlias,
            'digitalHealthSharingTypes' => WorkQueue::$digitalHealthSharingTypes,
            'cancelRoute' => $cancelRoute,
            'patientStatusForm' => $patientStatusForm,
            'orgPatientStatusData' => $orgPatientStatusData,
            'orgPatientStatusHistoryData' => $orgPatientStatusHistoryData,
            'awardeePatientStatusData' => $awardeePatientStatusData,
            'isDVType' => $isDVType,
            'canViewPatientStatus' => $canViewPatientStatus,
            'displayPatientStatusBlock' => !$isDVType,
            'canEdit' => $participant->status || $participant->editExistingOnly,
            'disablePatientStatusMessage' => $params->has('disable_patient_status_message') ? $params->get('disable_patient_status_message') : null,
            'evaluationUrl' => $evaluationUrl,
            'showConsentPDFs' => (bool) $params->has('feature.participantconsentsworkqueue') && $params->get('feature.participantconsentsworkqueue'),
            'incentiveForm' => $incentiveForm->createView(),
            'incentives' => $incentives,
            'incentiveDeleteForm' => $incentiveDeleteForm->createView(),
            'readOnlyView' => $this->isReadOnly(),
            'canViewOnSiteDetails' => $incentiveService->hasAccess($participant),
            'idVerificationForm' => $idVerificationForm->createView(),
            'idVerifications' => $idVerifications,
            'idVerificationChoices' => IdVerificationType::$idVerificationChoices
        ]);
    }

    #[Route(path: '/participant/{id}/consent/{consentType}', name: 'participant_consent')]
    public function participantConsent(
        string $id,
        string $consentType,
        ParticipantSummaryService $participantSummaryService,
        GcsBucketService $bucketService,
        Request $request
    ): Response {
        if (!in_array($consentType, array_keys(self::VALID_CONSENT_TYPES))) {
            throw $this->createNotFoundException(sprintf('Not a valid consent type: %s', $consentType));
        }
        $participant = $participantSummaryService->getParticipantById($id, true);
        $participantSummaryField = self::VALID_CONSENT_TYPES[$consentType];
        if (!$participant->{$participantSummaryField} || !preg_match('/(^[a-z0-9\-\_]+)\/(.*)/i', $participant->{$participantSummaryField}, $matches)) {
            throw $this->createNotFoundException(sprintf('Invalid storage path: %s', $participant->{$participantSummaryField}));
        }
        $bucket = $matches[1];
        $consentPath = $matches[2];
        if (!$consentPath) {
            throw $this->createNotFoundException('Unable to load consent; Reason: Path not set.');
        }
        try {
            $object = $bucketService->getObjectFromPath($bucket, $consentPath);
            $response = new Response($object->downloadAsString());
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Unable to load consent; Reason: ' . $e->getMessage());
        }
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            sprintf('%s-%s.pdf', $id, $consentType)
        );
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('Content-Type', 'application/pdf');
        return $response;
    }

    #[Route(path: '/participant/{id}/incentive/{incentiveId}', name: 'participant_incentive', defaults: ['incentiveId' => null])]
    public function participantIncentiveAction(
        $id,
        $incentiveId,
        Request $request,
        ParticipantSummaryService $participantSummaryService,
        SiteService $siteService,
        IncentiveService $incentiveService
    ): Response {
        $participant = $participantSummaryService->getParticipantById($id);
        $incentive = $incentiveId ? $this->em->getRepository(Incentive::class)->findOneBy(['id' => $incentiveId, 'participantId' => $id]) : null;
        if ($incentive && $incentive->getSite() !== $siteService->getSiteId()) {
            throw $this->createAccessDeniedException();
        }
        if ($incentive && $incentive->getRecipient() == Incentive::PEDIATRIC_GUARDIAN) {
            $incentive->setRecipient($incentive->getRelatedParticipantRecipient());
        }
        $incentiveForm = $this->createForm(IncentiveType::class, $incentive, ['require_notes' => $incentiveId ? true : false, 'pediatric_participant' => $participant->isPediatric, 'participant' => $participant]);
        $incentiveForm->handleRequest($request);
        if ($incentiveForm->isSubmitted()) {
            if ($incentiveForm->isValid()) {
                if ($incentiveId) {
                    if ($incentiveService->amendIncentive($id, $incentiveForm)) {
                        $this->addFlash('incentive-success', 'Incentive Updated');
                    } else {
                        $this->addFlash('incentive-error', 'Error updating incentive. Please try again');
                    }
                } else {
                    if ($incentiveService->createIncentive($id, $incentiveForm)) {
                        $this->addFlash('incentive-success', 'Incentive Saved');
                    } else {
                        $this->addFlash('incentive-error', 'Error creating incentive. Please try again');
                    }
                }
            } else {
                $this->addFlash('incentive-error', $incentiveForm->getErrors(true)->current()->getMessage());
            }
            return $this->redirectToRoute('participant', ['id' => $id]);
        }

        return $this->render('/partials/participant-incentive.html.twig', [
            'incentiveForm' => $incentiveForm->createView(),
            'participant' => $participant,
            'type' => 'edit',
            'incentiveId' => $incentiveId
        ]);
    }

    #[Route(path: '/ajax/search/giftcard-prefill', name: 'search_gift_card_prefill')]
    public function giftCardFillAction(): JsonResponse
    {
        return $this->json(Incentive::$giftCardTypes);
    }

    #[Route(path: '/ajax/search/giftcard/{query}', name: 'search_giftcard')]
    public function giftCardAction(Request $request): JsonResponse
    {
        $query = $request->get('query');
        $giftCards = $this->em->getRepository(Incentive::class)->search($query);
        $results = [];
        foreach ($giftCards as $giftCard) {
            $results[] = $giftCard['giftCardType'];
        }
        return $this->json($results);
    }

    #[Route(path: '/ajax/search/type-of-item-prefill', name: 'search_item_type_prefill')]
    public function itemTypePrefill(): JsonResponse
    {
        return $this->json(Incentive::$itemTypes);
    }

    #[Route(path: '/ajax/search/type-of-item/{query}', name: 'search_giftcard')]
    public function itemTypeAction(Request $request): JsonResponse
    {
        $query = $request->get('query');
        $itemTypes = $this->em->getRepository(Incentive::class)->search($query, 'i.typeOfItem');
        $results = [];
        foreach ($itemTypes as $itemType) {
            $results[] = $itemType['typeOfItem'];
        }
        return $this->json($results);
    }
}
