<?php

namespace App\Controller;

use App\Entity\Incentive;
use App\Entity\Measurement;
use App\Entity\Order;
use App\Entity\PatientStatus;
use App\Entity\Problem;
use App\Entity\User;
use App\Form\CrossOriginAgreeType;
use App\Form\IncentiveType;
use App\Form\PatientStatusType;
use App\Helper\WorkQueue;
use App\Service\GcsBucketService;
use App\Service\LoggerService;
use App\Service\MeasurementService;
use App\Service\ParticipantSummaryService;
use App\Service\PatientStatusService;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;
use App\Audit\Log;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class ParticipantDetailsController extends AbstractController
{
    private const VALID_CONSENT_TYPES = [
        'consentForStudyEnrollment' => 'consentForStudyEnrollmentFilePath',
        'consentForElectronicHealthRecords' => 'consentForElectronicHealthRecordsFilePath',
        'consentForGenomicsROR' => 'consentForGenomicsRORFilePath',
        'consentForCABoR' => 'consentForCABoRFilePath',
    ];

    /**
     * @Route("/participant/{id}", name="participant")
     */
    public function participantDetailsAction(
        $id,
        Request $request,
        SessionInterface $session,
        LoggerService $loggerService,
        EntityManagerInterface $em,
        ParticipantSummaryService $participantSummaryService,
        SiteService $siteService,
        ParameterBagInterface $params,
        PatientStatusService $patientStatusService,
        MeasurementService $measurementService
    ) {
        $refresh = $request->query->get('refresh');
        $participant = $participantSummaryService->getParticipantById($id, $refresh);

        if ($refresh) {
            return $this->redirectToRoute('participant', [
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
        $isCrossOrg = $participant->hpoId !== $siteService->getSiteAwardee();
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
        $measurements = $em->getRepository(Measurement::class)->getMeasurementsWithoutParent($id);
        $orders = $em->getRepository(Order::class)->findBy(['participantId' => $id], ['id' => 'desc']);
        $problems = $em->getRepository(Problem::class)->getProblemsWithCommentsCount($id);

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
            $patientStatusRepository = $em->getRepository(PatientStatus::class);
            $orgPatientStatusData = $patientStatusRepository->getOrgPatientStatusData($id, $siteService->getSiteOrganization());
            // Determine if comment field is required
            $isCommentRequired = !empty($orgPatientStatusData) ? true : false;
            // Get patient status form
            $patientStatusForm = $this->createForm(PatientStatusType::class, null, ['require_comment' => $isCommentRequired]);
            $patientStatusForm->handleRequest($request);
            if ($patientStatusForm->isSubmitted()) {
                $patientStatus = $em->getRepository(PatientStatus::class)->findOneBy([
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
                        $this->addFlash('success', 'Patient status saved');
                        // Load newly entered data
                        $orgPatientStatusData = $patientStatusRepository->getOrgPatientStatusData($id, $siteService->getSiteOrganization());
                        // Get new form
                        $patientStatusForm = $this->createForm(PatientStatusType::class, null, ['require_comment' => true]);
                    } else {
                        $this->addFlash('error', 'Failed to create patient status. Please try again.');
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

        $incentiveForm = $this->createForm(IncentiveType::class, null, ['action' => $this->generateUrl('participant_incentive', ['id' => $id])]);
        $incentives = $em->getRepository(Incentive::class)->findBy(['participantId' => $id], ['id' => 'DESC']);

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
            'samples' => WorkQueue::$samples,
            'surveys' => WorkQueue::$surveys,
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
            'incentives' => $incentives
        ]);
    }

    /**
     * @Route("/participant/{id}/consent/{consentType}", name="participant_consent")
     */
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

    /**
     * @Route("/participant/{id}/incentive/{incentiveId}", name="participant_incentive", defaults={"incentiveId": null})
     */
    public function participantIncentiveAction(
        $id,
        $incentiveId,
        Request $request,
        LoggerService $loggerService,
        EntityManagerInterface $em,
        ParticipantSummaryService $participantSummaryService,
        SiteService $siteService
    ): Response {
        $participant = $participantSummaryService->getParticipantById($id);
        $incentive = $incentiveId ? $em->getRepository(Incentive::class)->find($incentiveId) : null;
        $incentiveForm = $this->createForm(IncentiveType::class, $incentive, ['action' => $request->getRequestUri()]);
        $incentiveForm->handleRequest($request);
        if ($incentiveForm->isSubmitted()) {
            if ($incentiveForm->isValid()) {
                $userRepository = $em->getRepository(User::class);
                $now = new \DateTime();
                $incentive = $incentiveForm->getData();
                $incentive->setParticipantId($id);
                $incentive->setCreatedTs($now);
                $incentive->setSite($siteService->getSiteId());
                $incentive->setUser($userRepository->find($this->getUser()->getId()));
                $em->persist($incentive);
                $em->flush();
                $this->addFlash('success', $incentiveId ? 'Incentive Updated' : 'Incentive Created');
            } else {
                $incentiveForm->addError(new FormError('Please correct the errors below'));
            }
            return $this->redirectToRoute("participant", ['id' => $id]);
        }

        return $this->render('/partials/participant-incentive.html.twig', [
            'incentiveForm' => $incentiveForm->createView(),
            'participant' => $participant
        ]);
    }
}
