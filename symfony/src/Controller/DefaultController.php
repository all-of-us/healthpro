<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\SiteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    /**
     * @Route("/s", name="symfony_home")
     */
    public function index()
    {
        return $this->render('index.html.twig');
    }

    /**
     * @Route("/s/admin", name="admin_home")
     */
    public function adminIndex()
    {
        return $this->render('admin/index.html.twig');
    }

    /**
     * @Route("/s/site/select", name="site_select")
     */
    public function siteSelectAction(Request $request, SiteService $siteService)
    {
        if ($request->request->has('site')) {
            if (!$this->isCsrfTokenValid('siteSelect', $request->request->get('csrf_token'))) {
                throw $this->createAccessDeniedException();
            }
            $siteId = $request->request->get('site');
            if (strpos($siteId, User::AWARDEE_PREFIX) !== 0 && !$siteService->isValidSite($siteId)) {
                $this->addFlash('error', "Sorry, there is a problem with your site's configuration. Please contact your site administrator.");
                return $this->render('site-select.html.twig', ['siteEmail' => $siteId]);
            }
            if ($siteService->switchSite($siteId)) {
                return $this->redirectToRoute('symfony_home');
            } else {
                throw $this->createAccessDeniedException();
            }
        }
        return $this->render('site-select.html.twig');
    }
}
