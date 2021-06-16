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
        $checkTimeZone = $this->isGranted('ROLE_USER') || $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_AWARDEE') || $this->isGranted('ROLE_DV_ADMIN') || $this->isGranted('ROLE_BIOBANK') || $this->isGranted('ROLE_SCRIPPS') || $this->isGranted('ROLE_AWARDEE_SCRIPPS');
        if ($checkTimeZone && !$this->getUser()->getTimezone()) {
            $this->addFlash('error', 'Please select your current time zone');
            return $this->redirectToRoute('settings');
        }
        if ($this->isGranted('ROLE_USER') || ($this->isGranted('ROLE_AWARDEE') && $this->isGranted('ROLE_DV_ADMIN'))) {
            return $this->render('index.html.twig');
        } elseif ($this->isGranted('ROLE_AWARDEE')) {
            return $this->redirectToRoute('workqueue_index');
        } elseif ($this->isGranted('ROLE_DV_ADMIN')) {
            return $this->redirectToRoute('problem_reports');
        } elseif ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_home');
        } elseif ($this->isGranted('ROLE_DASHBOARD')) {
            return $this->redirectToRoute('dashboard_home');
        } elseif ($this->isGranted('ROLE_BIOBANK') || $this->isGranted('ROLE_SCRIPPS')) {
            return $this->redirectToRoute('biobank_home');
        } else {
            throw $this->createAccessDeniedException();
        }
    }

    /**
     * @Route("/s/admin", name="admin_home")
     */
    public function adminIndex()
    {
        return $this->render('admin/index.html.twig');
    }

    /**
     * @Route("/s/splash", name="dash_splash")
     */
    public function dashSplashAction()
    {
        return $this->render('dash-splash.html.twig');
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
