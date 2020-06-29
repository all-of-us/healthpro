<?php

namespace App\Controller;

use App\Entity\PatientStatusImport;
use App\Entity\PatientStatusTemp;
use App\Service\LoggerService;
use App\Form\PatientStatusImportFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

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
     * @Route("/patient/status/import", name="patientStatusImports", methods={"GET","POST"})
     */
    public function patientStatusImport(Request $request, SessionInterface $session, EntityManagerInterface $em)
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
                $patientStatusImport = new PatientStatusImport();
                $patientStatusImport->setFileName($fileName);
                $patientStatusImport->setImportStatus(0);
                $patientStatusImport->setOrganization($session->get('siteOrganizationId'));
                $patientStatusImport->setAwardee($session->get('siteAwardeeId'));
                $patientStatusImport->setUserId(1);
                $patientStatusImport->setSite($session->get('site')->id);
                $patientStatusImport->setCreatedTs(new \DateTime());
                foreach ($patientStatuses as $key => $patientStatus) {
                    $patientStatusTemp = new PatientStatusTemp();
                    $patientStatusTemp->setParticipantId($patientStatus['participantId']);
                    $patientStatusTemp->setStatus($patientStatus['status']);
                    $patientStatusTemp->setComments($patientStatus['comments']);
                    $patientStatusTemp->setImport($patientStatusImport);
                    $em->persist($patientStatusImport);
                    $em->persist($patientStatusTemp);
                }
                $em->flush();
                $em->clear();
            }
        }
        return $this->render('patientstatus/import.html.twig', [
            'importForm' => $form->createView()
        ]);
    }
}
