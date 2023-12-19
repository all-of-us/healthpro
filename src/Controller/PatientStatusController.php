<?php

namespace App\Controller;

use App\Audit\Log;
use App\Entity\Organization;
use App\Entity\PatientStatusImport;
use App\Entity\PatientStatusImportRow;
use App\Form\PatientStatusImportConfirmFormType;
use App\Form\PatientStatusImportFormType;
use App\Repository\PatientStatusRepository;
use App\Service\LoggerService;
use App\Service\ParticipantSummaryService;
use App\Service\PatientStatusImportService;
use App\Service\PatientStatusService;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class PatientStatusController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    #[Route(path: '/patient/status/import', name: 'patientStatusImport', methods: ['GET', 'POST'])]
    public function patientStatusImport(
        Request $request,
        SessionInterface $session,
        LoggerService $loggerService,
        PatientStatusImportService $patientStatusImportService,
        SiteService $siteService
    ) {
        $form = $this->createForm(PatientStatusImportFormType::class);
        $form->handleRequest($request);
        try {
            if ($form->isSubmitted()) {
                $file = $form['patient_status_csv']->getData();
                $fileName = $file->getClientOriginalName();
                $patientStatuses = [];
                $patientStatusImportService->extractCsvFileData($file, $form, $patientStatuses);
                if ($form->isValid()) {
                    if (!empty($patientStatuses)) {
                        $organization = $this->em->getRepository(Organization::class)->findOneBy(['id' => $siteService->getSiteOrganization()]);
                        $patientStatusImport = new PatientStatusImport();
                        $patientStatusImport
                            ->setFileName($fileName)
                            ->setOrganization($organization)
                            ->setAwardee($siteService->getSiteAwardeeId())
                            ->setUserId($this->getSecurityUser()->getId())
                            ->setSite($session->get('site')->id)
                            ->setCreatedTs(new \DateTime());
                        $this->em->persist($patientStatusImport);
                        $batchSize = 50;
                        foreach ($patientStatuses as $key => $patientStatus) {
                            $PatientStatusImportRow = new PatientStatusImportRow();
                            $PatientStatusImportRow
                                ->setParticipantId($patientStatus['participant_id'])
                                ->setStatus($patientStatus['status'])
                                ->setComments($patientStatus['comments'])
                                ->setImport($patientStatusImport);
                            $this->em->persist($PatientStatusImportRow);
                            if (($key % $batchSize) === 0) {
                                $this->em->flush();
                                $this->em->clear(PatientStatusImportRow::class);
                            }
                        }
                        $this->em->flush();
                        $id = $patientStatusImport->getId();
                        $loggerService->log(Log::PATIENT_STATUS_IMPORT_ADD, $id);
                        $this->em->clear();
                        return $this->redirectToRoute('patientStatusImportConfirmation', ['id' => $id]);
                    }
                } else {
                    $form->addError(new FormError('Please correct the errors below'));
                }
            }
        } catch (\Exception $e) {
            $loggerService->log('error', $e->getMessage());
            $this->addFlash('error', 'Import failed. Please try again.');
        }
        $patientStatusImports = $this->em->getRepository(PatientStatusImport::class)->findBy(['userId' => $this->getSecurityUser()->getId(), 'confirm' => 1], ['id' => 'DESC']);
        return $this->render('patientstatus/import.html.twig', [
            'importForm' => $form->createView(),
            'imports' => $patientStatusImports
        ]);
    }

    #[Route(path: '/patient/status/confirmation/{id}', name: 'patientStatusImportConfirmation', methods: ['GET', 'POST'])]
    public function patientStatusImportConfirmation(int $id, Request $request, LoggerService $loggerService)
    {
        $patientStatusImport = $this->em->getRepository(PatientStatusImport::class)->findOneBy(['id' => $id, 'userId' => $this->getSecurityUser()->getId(), 'confirm' => 0]);
        if (empty($patientStatusImport)) {
            throw $this->createNotFoundException('Page Not Found!');
        }
        $form = $this->createForm(PatientStatusImportConfirmFormType::class);
        $form->handleRequest($request);
        try {
            if ($form->isSubmitted() && $form->isValid()) {
                /** @var SubmitButton $confirmButton */
                $confirmButton = $form->get('Confirm');
                if ($confirmButton->isClicked()) {
                    // Update confirm status
                    $patientStatusImport->setConfirm(1);
                    $this->em->flush();
                    $loggerService->log(Log::PATIENT_STATUS_IMPORT_EDIT, $patientStatusImport->getId());
                    $this->em->clear();
                    $this->addFlash('success', 'Successfully Imported!');
                } else {
                    $this->addFlash('notice', 'Import canceled!');
                    $this->em->remove($patientStatusImport);
                    $this->em->flush();
                }
                return $this->redirectToRoute('patientStatusImport');
            }
        } catch (\Exception $e) {
            $loggerService->log('error', $e->getMessage());
            $this->addFlash('error', 'Import failed. Please try again.');
        }
        $importPatientStatuses = $patientStatusImport->getPatientStatusImportRows()->slice(0, 100);

        return $this->render('patientstatus/confirmation.html.twig', [
            'patientStatuses' => $importPatientStatuses,
            'importConfirmForm' => $form->createView(),
            'rowsCount' => count($patientStatusImport->getPatientStatusImportRows())
        ]);
    }

    #[Route(path: '/patient/status/import/{id}', name: 'patientStatusImportDetails', methods: ['GET', 'POST'])]
    public function patientStatusImportDetails(int $id, Request $request, PatientStatusImportService $patientStatusImportService)
    {
        $patientStatusImport = $this->em->getRepository(PatientStatusImport::class)->findOneBy(['id' => $id, 'userId' => $this->getSecurityUser()->getId(), 'confirm' => 1]);
        if (empty($patientStatusImport)) {
            throw $this->createNotFoundException('Page Not Found!');
        }
        //For ajax requests
        if ($request->isXmlHttpRequest()) {
            $params = $request->request->all();
            $patientStatusImportRows = $patientStatusImport->getPatientStatusImportRows()->slice((int) $params['start'], (int) $params['length']);
            $ajaxData = [];
            $ajaxData['data'] = $patientStatusImportService->getAjaxData($patientStatusImport, $patientStatusImportRows);
            $ajaxData['recordsTotal'] = $ajaxData['recordsFiltered'] = count($patientStatusImport->getPatientStatusImportRows());
            return new JsonResponse($ajaxData);
        }
        return $this->render('patientstatus/import-details.html.twig');
    }

    #[Route(path: '/participant/{participantId}/patient/status/{patientStatusId}', name: 'patientStatus')]
    #[Route(path: '/read/participant/{participantId}/patient/status/{patientStatusId}', name: 'read_patientStatus')]
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
