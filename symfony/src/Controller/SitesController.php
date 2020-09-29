<?php

namespace App\Controller;

use App\Repository\SiteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/s/admin/sites")
 */
class SitesController extends AbstractController
{
    /**
     * @Route("/", name="admin_sites")
     */
    public function index(SiteRepository $siteRepository)
    {
        $sites = $siteRepository->findBy(['deleted' => 0], ['name' => 'asc']);
        return $this->render('admin/sites/index.html.twig', [
            'sites' => $sites
        ]);
    }
}
