<?php

namespace App\Controller;

use App\Entity\Measurement;
use App\Entity\Order;
use App\Entity\Problem;
use App\Form\CrossOriginAgreeType;
use App\Helper\WorkQueue;
use App\Service\LoggerService;
use App\Service\ParticipantSummaryService;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;
use Pmi\Audit\Log;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/s")
 */
class ParticipantDetailsController extends AbstractController
{
    /**
     * @Route("/participant/{id}", name="participant_details")
     */
    public function participantDetailsAction(
        $id,
        Request $request,
        SessionInterface $session,
        LoggerService $loggerService,
        EntityManagerInterface $em,
        ParticipantSummaryService $participantSummaryService,
        SiteService $siteService,
        ParameterBagInterface $params
    ) {
        $refresh = $request->query->get('refresh');
        $participant = $participantSummaryService->getParticipantById($id, $refresh);

        if ($refresh) {
            return $this->redirectToRoute('participant', [
                'id' => $id
            ]);
        }
        if (!$participant) {
            $this->createNotFoundException();
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
        $measurements = $em->getRepository(Measurement::class)->findBy(['participantId' => $id], ['id' => 'desc']);
        $orders = $em->getRepository(Order::class)->findBy(['participantId' => $id], ['id' => 'desc']);
        $problems = $em->getRepository(Problem::class)->getProblemsWithCommentsCount($id);

        if (empty($participant->cacheTime)) {
            $participant->cacheTime = new \DateTime();
        }
        foreach ($orders as $order) {
            // Display most recent processed sample time if exists
            // This is for supporting old orders where this calculation is not implemented in the order process save step
            $processedSamplesTs = json_decode($order->getProcessedSamplesTs(), true);
            if (is_array($processedSamplesTs) && !empty($processedSamplesTs)) {
                $processedTs = new \DateTime();
                $processedTs->setTimestamp(max($processedSamplesTs));
                $processedTs->setTimezone(new \DateTimeZone($this->getUser()->getInfo()['timezone']));
                $order->setProcessedTs($processedTs);
            }
        }
        // Determine cancel route
        $cancelRoute = 'participants';
        if ($request->query->has('return')) {
            if (strpos($request->query->get('return'), '/order/') !== false) {
                $cancelRoute = 'orders';
            }
        }

        // TODO: Implement patient status create
        $patientStatusForm = null;
        $orgPatientStatusData = null;
        $orgPatientStatusHistoryData = null;
        $awardeePatientStatusData = null;
        $canViewPatientStatus = false;
        $cacheEnabled = $params->has('rdr_disable_cache') ? !$params->get('rdr_disable_cache') : true;
        $isDVType = $session->get('siteType') === 'dv' ? true : false;
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
            'cancelRoute' => $cancelRoute,
            'patientStatusForm' => $patientStatusForm,
            'orgPatientStatusData' => $orgPatientStatusData,
            'orgPatientStatusHistoryData' => $orgPatientStatusHistoryData,
            'awardeePatientStatusData' => $awardeePatientStatusData,
            'isDVType' => $isDVType,
            'canViewPatientStatus' => $canViewPatientStatus,
            'displayPatientStatusBlock' => !$isDVType,
            'canEdit' => $participant->status || $participant->editExistingOnly,
            'disablePatientStatusMessage' => $params->has('disable_patient_status_message') ? $params->get('disable_patient_status_message') : null
        ]);
    }
}
