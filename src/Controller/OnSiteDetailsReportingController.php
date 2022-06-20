<?php

namespace App\Controller;

use App\Entity\PatientStatus;
use App\Service\OnSiteDetailsReportingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/on-site")
 */
class OnSiteDetailsReportingController extends BaseController
{
    public function __construct(
        EntityManagerInterface $em
    )
    {
        parent::__construct($em);
    }

    /**
     * @Route("/patient-status", name="on_site_patient_status")
     */
    public function index(OnSiteDetailsReportingService $onSiteDetailsReportingService, Request $request)
    {
        //For ajax requests
        if ($request->isXmlHttpRequest()) {
            $params = $request->request->all();
            $patientStatuses = $this->em->getRepository(PatientStatus::class)->findBy([], ['id' => 'DESC'], $params['length'], $params['start']);
            $ajaxData = [];
            $ajaxData['data'] = $onSiteDetailsReportingService->getAjaxData($patientStatuses);
            // TODO get count
            $ajaxData['recordsTotal'] = $ajaxData['recordsFiltered'] = 3;
            return $this->json($ajaxData);
        } else {
            return $this->render('onsite/patient-status.html.twig');
        }
    }
}
