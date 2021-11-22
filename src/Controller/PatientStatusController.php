<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\PatientStatusImport;
use App\Entity\PatientStatusImportRow;
use App\Repository\PatientStatusRepository;
use App\Service\ParticipantSummaryService;
use App\Service\PatientStatusImportService;
use App\Service\LoggerService;
use App\Form\PatientStatusImportFormType;
use App\Form\PatientStatusImportConfirmFormType;
use App\Service\PatientStatusService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Audit\Log;

class PatientStatusController extends AbstractController
{
    /**
     * @Route("/patient/status/import", name="patientStatusImport", methods={"GET","POST"})
     */
    public function patientStatusImport(Request $request, SessionInterface $session, EntityManagerInterface $em, LoggerService $loggerService, PatientStatusImportService $patientStatusImportService)
    {
        $form = $this->createForm(PatientStatusImportFormType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $file = $form['patient_status_csv']->getData();
            $fileName = $file->getClientOriginalName();
            $patientStatuses = [];
            $patientStatusImportService->extractCsvFileData($file, $form, $patientStatuses);
            if ($form->isValid()) {
                if (!empty($patientStatuses)) {
                    $organization = $em->getRepository(Organization::class)->findOneBy(['id' => $session->get('siteOrganizationId')]);
                    $patientStatusImport = new PatientStatusImport();
                    $patientStatusImport
                        ->setFileName($fileName)
                        ->setOrganization($organization)
                        ->setAwardee($session->get('siteAwardeeId'))
                        ->setUserId($this->getUser()->getId())
                        ->setSite($session->get('site')->id)
                        ->setCreatedTs(new \DateTime());
                    $em->persist($patientStatusImport);
                    $batchSize = 50;
                    foreach ($patientStatuses as $key => $patientStatus) {
                        $PatientStatusImportRow = new PatientStatusImportRow();
                        $PatientStatusImportRow
                            ->setParticipantId($patientStatus['participantId'])
                            ->setStatus($patientStatus['status'])
                            ->setComments($patientStatus['comments'])
                            ->setImport($patientStatusImport);
                        $em->persist($PatientStatusImportRow);
                        if (($key % $batchSize) === 0) {
                            $em->flush();
                            $em->clear(PatientStatusImportRow::class);
                        }
                    }
                    $em->flush();
                    $id = $patientStatusImport->getId();
                    $loggerService->log(Log::PATIENT_STATUS_IMPORT_ADD, $id);
                    $em->clear();
                    return $this->redirectToRoute('patientStatusImportConfirmation', ['id' => $id]);
                }
            } else {
                $form->addError(new FormError('Please correct the errors below'));
            }
        }
        $patientStatusImports = $em->getRepository(PatientStatusImport::class)->findBy(['userId' => $this->getUser()->getId(), 'confirm' => 1], ['id' => 'DESC']);
        return $this->render('patientstatus/import.html.twig', [
            'importForm' => $form->createView(),
            'imports' => $patientStatusImports
        ]);
    }

    /**
     * @Route("/patient/status/confirmation/{id}", name="patientStatusImportConfirmation", methods={"GET", "POST"})
     */
    public function patientStatusImportConfirmation(int $id, Request $request, EntityManagerInterface $em, LoggerService $loggerService)
    {
        $patientStatusImport = $em->getRepository(PatientStatusImport::class)->findOneBy(['id' => $id, 'userId' => $this->getUser()->getId(), 'confirm' => 0]);
        if (empty($patientStatusImport)) {
            throw $this->createNotFoundException('Page Not Found!');
        }
        $form = $this->createForm(PatientStatusImportConfirmFormType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('Confirm')->isClicked()) {
                // Update confirm status
                $patientStatusImport->setConfirm(1);
                $em->flush();
                $loggerService->log(Log::PATIENT_STATUS_IMPORT_EDIT, $patientStatusImport->getId());
                $em->clear();
                $this->addFlash('success', 'Successfully Imported!');
            } else {
                $this->addFlash('notice', 'Import canceled!');
                $em->remove($patientStatusImport);
                $em->flush();
            }
            return $this->redirectToRoute('patientStatusImport');
        } else {
            $importPatientStatuses = $patientStatusImport->getPatientStatusImportRows()->slice(0, 100);
        }
        return $this->render('patientstatus/confirmation.html.twig', [
            'patientStatuses' => $importPatientStatuses,
            'importConfirmForm' => $form->createView(),
            'rowsCount' => count($patientStatusImport->getPatientStatusImportRows())
        ]);
    }

    /**
     * @Route("/patient/status/import/{id}", name="patientStatusImportDetails", methods={"GET", "POST"})
     */
    public function patientStatusImportDetails(int $id, Request $request, EntityManagerInterface $em, PatientStatusImportService $patientStatusImportService)
    {
        $patientStatusImport = $em->getRepository(PatientStatusImport::class)->findOneBy(['id' => $id, 'userId' => $this->getUser()->getId(), 'confirm' => 1]);
        if (empty($patientStatusImport)) {
            throw $this->createNotFoundException('Page Not Found!');
        }
        //For ajax requests
        if ($request->isXmlHttpRequest()) {
            $params = $request->request->all();
            $patientStatusImportRows = $patientStatusImport->getPatientStatusImportRows()->slice($params['start'], $params['length']);
            $ajaxData = [];
            $ajaxData['data'] = $patientStatusImportService->getAjaxData($patientStatusImport, $patientStatusImportRows);
            $ajaxData['recordsTotal'] = $ajaxData['recordsFiltered'] = count($patientStatusImport->getPatientStatusImportRows());
            return new JsonResponse($ajaxData);
        } else {
            return $this->render('patientstatus/import-details.html.twig');
        }
    }

    /**
     * @Route("/participant/{participantId}/patient/status/{patientStatusId}", name="patientStatus")
     */
    public function patientStatusAction(
        $participantId,
        $patientStatusId,
        ParticipantSummaryService $participantSummaryService,
        PatientStatusService $patientStatusService,
        PatientStatusRepository $patientStatusRepository
    ) {
        $participant = $participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException();
        }
        if (!$patientStatusService->hasAccess($participant)) {
            throw $this->createAccessDeniedException();
        }
        $patientStatusData = $patientStatusRepository->findOneBy([
            'id' => $patientStatusId,
            'participantId' => $participantId
        ]);
        if (!empty($patientStatusData)) {
            $organization = $patientStatusData->getOrganization();
            $orgPatientStatusHistoryData = $patientStatusRepository->getOrgPatientStatusHistoryData($participantId, $organization);
        } else {
            $orgPatientStatusHistoryData = [];
            $organization = null;
        }
        return $this->render('patientstatus/history.html.twig', [
            'orgPatientStatusHistoryData' => $orgPatientStatusHistoryData,
            'organization' => $organization
        ]);
    }
}
