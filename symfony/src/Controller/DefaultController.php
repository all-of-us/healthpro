<?php

namespace App\Controller;

use App\Entity\PatientStatus;
use App\Entity\PatientStatusHistory;
use App\Entity\PatientStatusImport;
use App\Entity\PatientStatusTemp;
use App\Entity\User;
use App\Service\LoggerService;
use App\Form\PatientStatusImportFormType;
use App\Form\PatientStatusImportConfirmFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

/**
 * @Route("/s")
 */
class DefaultController extends AbstractController
{
    /**
     * @Route("/", name="home")
     */
    public function index(LoggerService $loggerService)
    {
        $loggerService->log('REQUEST', 'HealthPro Symfony Home Page');
        return $this->render('index.html.twig');
    }

    /**
     * @Route("/patient/status/import", name="patientStatusImport", methods={"GET","POST"})
     */
    public function patientStatusImport(Request $request, SessionInterface $session, EntityManagerInterface $em, Security $security)
    {
        $form = $this->createForm(PatientStatusImportFormType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form['patient_status_csv']->getData();
            $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '-' . uniqid();
            $fileHandle = fopen($file->getPathname(), 'r');
            $patientStatuses = [];
            while (($row = fgetcsv($fileHandle, 0, ",")) !== false) {
                $patientStatus = [];
                $patientStatus['participantId'] = $row[0];
                $patientStatus['status'] = $row[1];
                $patientStatus['comments'] = $row[2];
                $patientStatuses[] = $patientStatus;
            }
            if (!empty($patientStatuses)) {
                $user = $em->getRepository(User::class)->findOneBy(['email' => $security->getUser()->getUsername()]);
                $patientStatusImport = new PatientStatusImport();
                $patientStatusImport->setFileName($fileName);
                $patientStatusImport->setImportStatus(0);
                $patientStatusImport->setConfirm(0);
                $patientStatusImport->setOrganization($session->get('siteOrganizationId'));
                $patientStatusImport->setAwardee($session->get('siteAwardeeId'));
                $patientStatusImport->setUserId($user->getId());
                $patientStatusImport->setSite($session->get('site')->id);
                $patientStatusImport->setCreatedTs(new \DateTime());
                $em->persist($patientStatusImport);
                foreach ($patientStatuses as $key => $patientStatus) {
                    $patientStatusTemp = new PatientStatusTemp();
                    $patientStatusTemp->setParticipantId($patientStatus['participantId']);
                    $patientStatusTemp->setStatus($patientStatus['status']);
                    $patientStatusTemp->setComments($patientStatus['comments']);
                    $patientStatusTemp->setImport($patientStatusImport);
                    $em->persist($patientStatusTemp);
                }
                $em->flush();
                $id = $patientStatusImport->getId();
                $em->clear();
            }
            return $this->redirectToRoute('patientStatusImportConfirmation', ['id' => $id]);
        }
        return $this->render('patientstatus/import.html.twig', [
            'importForm' => $form->createView()
        ]);
    }

    /**
     * @Route("/patient/status/confirmation/{id}", name="patientStatusImportConfirmation", methods={"GET", "POST"})
     */
    public function patientStatusImportConfirmation(int $id, Request $request, EntityManagerInterface $em)
    {
        $patientStatusImport = $em->getRepository(PatientStatusImport::class)->findOneBy(['id' => $id, 'confirm' => 0]);
        $form = $this->createForm(PatientStatusImportConfirmFormType::class);
        $form->handleRequest($request);
        $importPatientStatuses = $patientStatusImport->getPatientStatusTemps();
        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($importPatientStatuses as $importPatientStatus) {
                $patientStatus = $em->getRepository(PatientStatus::class)->findOneBy([
                    'participant_id' => $importPatientStatus->getParticipantId(),
                    'organization' => $patientStatusImport->getOrganization()
                ]);
                if (!$patientStatus) {
                    $patientStatus = new PatientStatus();
                    $patientStatus->setParticipantId($importPatientStatus->getParticipantId());
                    $patientStatus->setAwardee($patientStatusImport->getAwardee());
                    $patientStatus->setOrganization($patientStatusImport->getOrganization());
                    $em->persist($patientStatus);
                }
                $patientStatusHistory = new PatientStatusHistory();
                $patientStatusHistory->setUserId($patientStatusImport->getUserId());
                $patientStatusHistory->setSite($patientStatusImport->getSite());
                $patientStatusHistory->setStatus($importPatientStatus->getStatus());
                $patientStatusHistory->setComments($importPatientStatus->getComments());
                $patientStatusHistory->setCreatedTs(new \DateTime());
                $patientStatusHistory->setPatientStatus($patientStatus);
                $em->persist($patientStatusHistory);
                $em->flush();
                $patientStatusHistoryId = $patientStatusHistory->getId();

                // Update history id in patient_status table
                $patientStatus = $em->getRepository(PatientStatus::class)->findOneBy([
                    'participant_id' => $importPatientStatus->getParticipantId(),
                    'organization' => $patientStatusImport->getOrganization()
                ]);
                $patientStatus->setHistoryId($patientStatusHistoryId);
                $em->persist($patientStatus);
                $em->flush();
            }
            // Update confirm status
            $patientStatusImport->setConfirm(1);
            $em->flush();
            $em->clear();
            return $this->redirectToRoute('patientStatusImport');
        }
        return $this->render('patientstatus/confirmation.html.twig', [
            'patientStatuses' => $importPatientStatuses,
            'importConfirmForm' => $form->createView()
        ]);
    }
}
