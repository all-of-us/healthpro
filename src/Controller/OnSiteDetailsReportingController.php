<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
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
    public function index()
    {
        return $this->render('onsite/patient-status.html.twig');
    }
}
