<?php

namespace App\Controller;

use App\Entity\Organizations;
use App\Entity\PatientStatus;
use App\Entity\PatientStatusHistory;
use App\Entity\PatientStatusImport;
use App\Entity\PatientStatusTemp;
use App\Service\PatientStatusImportService;
use App\Service\LoggerService;
use App\Form\PatientStatusImportFormType;
use App\Form\PatientStatusImportConfirmFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Pmi\Audit\Log;

/**
 * @Route("/s")
 */
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
                    $organization = $em->getRepository(Organizations::class)->findOneBy(['id' => $session->get('siteOrganizationId')]);
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
                        $patientStatusTemp = new PatientStatusTemp();
                        $patientStatusTemp
                            ->setParticipantId($patientStatus['participantId'])
                            ->setStatus($patientStatus['status'])
                            ->setComments($patientStatus['comments'])
                            ->setImport($patientStatusImport);
                        $em->persist($patientStatusTemp);
                        if (($key % $batchSize) === 0) {
                            $em->flush();
                            $em->clear(PatientStatusTemp::class);
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
                $importPatientStatuses = $patientStatusImport->getPatientStatusTemps();
                $newImportPatientStatuses = [];
                $batchSize = 50;
                $i = 0;
                foreach ($importPatientStatuses as $importPatientStatus) {
                    $patientStatus = $em->getRepository(PatientStatus::class)->findOneBy([
                        'participantId' => $importPatientStatus->getParticipantId(),
                        'organization' => $patientStatusImport->getOrganization()->getName()
                    ]);
                    if (!$patientStatus) {
                        $newImportPatientStatus['participantId'] = $importPatientStatus->getParticipantId();
                        $newImportPatientStatus['status'] = $importPatientStatus->getStatus();
                        $newImportPatientStatus['comments'] = $importPatientStatus->getComments();
                        $newImportPatientStatuses[] = $newImportPatientStatus;
                        continue;
                    }
                    $patientStatusHistory = new PatientStatusHistory();
                    $patientStatusHistory
                        ->setUserId($patientStatusImport->getUserId())
                        ->setSite($patientStatusImport->getSite())
                        ->setStatus($importPatientStatus->getStatus())
                        ->setComments($importPatientStatus->getComments())
                        ->setCreatedTs(new \DateTime())
                        ->setPatientStatus($patientStatus)
                        ->setImport($patientStatusImport);
                    $em->persist($patientStatusHistory);

                    // Update history id in patient_status table
                    $patientStatus->setHistory($patientStatusHistory);
                    if (($i % $batchSize) === 0) {
                        $em->flush();
                        $em->clear(PatientStatusHistory::class);
                        $em->clear(PatientStatus::class);
                    }
                    $i++;
                }
                $em->flush();
                foreach ($newImportPatientStatuses as $key => $newImportPatientStatus) {
                    $patientStatus = new PatientStatus();
                    $patientStatus
                        ->setParticipantId($newImportPatientStatus['participantId'])
                        ->setAwardee($patientStatusImport->getAwardee())
                        ->setOrganization($patientStatusImport->getOrganization()->getName());
                    $em->persist($patientStatus);

                    $patientStatusHistory = new PatientStatusHistory();
                    $patientStatusHistory
                        ->setUserId($patientStatusImport->getUserId())
                        ->setSite($patientStatusImport->getSite())
                        ->setStatus($newImportPatientStatus['status'])
                        ->setComments($newImportPatientStatus['comments'])
                        ->setCreatedTs(new \DateTime())
                        ->setPatientStatus($patientStatus)
                        ->setImport($patientStatusImport);
                    $em->persist($patientStatusHistory);
                    // Update history id in patient_status table
                    if (($key % $batchSize) === 0) {
                        $em->flush();
                        $em->clear(PatientStatusHistory::class);
                        $em->clear(PatientStatus::class);
                    }
                }
                $em->flush();
                // Update confirm status
                $patientStatusImport->setConfirm(1);
                $em->flush();
                $loggerService->log(Log::PATIENT_STATUS_IMPORT_EDIT, $patientStatusImport->getId());
                $em->clear();
                $this->addFlash(
                    'success',
                    'Successfully Imported!'
                );
            } else {
                $this->addFlash(
                    'notice',
                    'Import canceled!'
                );
                $em->remove($patientStatusImport);
                $em->flush();
            }
            return $this->redirectToRoute('patientStatusImport');
        } else {
            $importPatientStatuses = $patientStatusImport->getPatientStatusTemps()->slice(0, 100);
        }
        return $this->render('patientstatus/confirmation.html.twig', [
            'patientStatuses' => $importPatientStatuses,
            'importConfirmForm' => $form->createView(),
            'rowsCount' => count($patientStatusImport->getPatientStatusTemps())
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
            $patientStatusHistories = $patientStatusImport->getPatientStatusHistories()->slice($params['start'], $params['length']);
            $ajaxData = [];
            $ajaxData['data'] = $patientStatusImportService->getAjaxData($patientStatusHistories, $patientStatusImport->getOrganization());
            $ajaxData['recordsTotal'] = $ajaxData['recordsFiltered'] = count($patientStatusImport->getPatientStatusHistories());
            return new JsonResponse($ajaxData);
        } else {
            return $this->render('patientstatus/import-details.html.twig');
        }
    }
}
