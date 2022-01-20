<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AuthService;
use App\Service\LoggerService;
use App\Service\SiteService;
use App\Audit\Log;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;

class DefaultController extends AbstractController
{
    /**
     * @Route("/", name="home")
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
        } elseif ($this->isGranted('ROLE_BIOBANK') || $this->isGranted('ROLE_SCRIPPS')) {
            return $this->redirectToRoute('biobank_home');
        } else {
            throw $this->createAccessDeniedException();
        }
    }

    /**
     * @Route("/admin", name="admin_home")
     */
    public function adminIndex()
    {
        return $this->render('admin/index.html.twig');
    }

    /**
     * @Route("/site/select", name="site_select")
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
                return $this->redirectToRoute('home');
            } else {
                throw $this->createAccessDeniedException();
            }
        }
        return $this->render('site-select.html.twig');
    }
    /**
     * @Route("/keepalive", name="keep_alive")
     * Dummy action that serves to extend the user's session.
     */
    public function keepAliveAction(Request $request, LoggerService $loggerService)
    {
        if (!$this->isCsrfTokenValid('keepAlive', $request->request->get('csrf_token'))) {
            $loggerService->log(Log::CSRF_TOKEN_MISMATCH, [
                'submitted_token' => $request->request->get('csrf_token'),
                'referrer' => $request->headers->get('referer')
            ]);
            throw $this->createAccessDeniedException();
        }
        $request->getSession()->set('pmiLastUsed', time());
        return (new JsonResponse())->setData([]);
    }

    /**
     * @Route("/agree", name="agree_usage")
     */
    public function agreeUsageAction(Request $request)
    {
        if (!$this->isCsrfTokenValid('agreeUsage', $request->request->get('csrf_token'))) {
            throw $this->createAccessDeniedException();
        }
        $request->getSession()->set('isUsageAgreed', true);
        return (new JsonResponse())->setData([]);
    }

    /**
     * @Route("/client-timeout", name="client_timeout")
     * Handles a clientside session timeout, which might not be a true session
     * timeout if the user is working in multiple tabs.
     */
    public function clientTimeoutAction(Request $request)
    {
        // if we got to this point, then the beforeCallback() has
        // already checked the user's session is not expired - simply reload the page
        if ($request->headers->get('referer')) {
            return $this->redirect($request->headers->get('referer'));
        }
        return $this->redirect($this->generateUrl('home'));
    }

    /**
     * @Route("/hide-tz-warning", name="hide_tz_warning")
     */
    public function hideTZWarningAction(Request $request)
    {
        if (!$this->get('security.csrf.token_manager')->isTokenValid(new CsrfToken('hideTZWarning', $request->request->get('csrf_token')))) {
            throw $this->createAccessDeniedException();
        }
        $request->getSession()->set('hideTZWarning', true);
        return (new JsonResponse())->setData([]);
    }

    /**
     * @Route("/timeout", name="timeout")
     */
    public function timeoutAction()
    {
        return $this->render('timeout.html.twig');
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logoutAction(Request $request, LoggerService $loggerService, SessionInterface $session, AuthService $authService)
    {
        $timeout = $request->get('timeout');
        $loggerService->log(Log::LOGOUT);
        $this->get('security.token_storage')->setToken(null);
        $session->invalidate();
        return $this->redirect($authService->getGoogleLogoutUrl($timeout ? 'timeout' : 'home'));
    }
}
