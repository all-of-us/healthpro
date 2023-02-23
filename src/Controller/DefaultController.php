<?php

namespace App\Controller;

use App\Entity\FeatureNotification;
use App\Entity\FeatureNotificationUserMap;
use App\Entity\User;
use App\Repository\FeatureNotificationRepository;
use App\Service\AuthService;
use App\Service\ContextTemplateService;
use App\Service\LoggerService;
use App\Service\SiteService;
use App\Audit\Log;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;

class DefaultController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    /**
     * @Route("/", name="home")
     */
    public function index(Request $request, ContextTemplateService $contextTemplate)
    {
        $checkTimeZone = $this->isGranted('ROLE_USER') || $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_AWARDEE') || $this->isGranted('ROLE_DV_ADMIN') || $this->isGranted('ROLE_BIOBANK') || $this->isGranted('ROLE_SCRIPPS') || $this->isGranted('ROLE_AWARDEE_SCRIPPS');
        if ($checkTimeZone && !$this->getSecurityUser()->getTimezone()) {
            $this->addFlash('error', 'Please select your current time zone');
            return $this->redirectToRoute('settings');
        }
        if (($this->isGranted('ROLE_USER') || $this->isGranted('ROLE_NPH_USER')) || ($this->isGranted('ROLE_AWARDEE') &&
                $this->isGranted('ROLE_DV_ADMIN'))) {
            return $this->render($contextTemplate->GetProgramTemplate('index.html.twig'));
        } elseif ($this->isGranted('ROLE_AWARDEE')) {
            return $this->redirectToRoute('workqueue_index');
        } elseif ($this->isGranted('ROLE_DV_ADMIN')) {
            return $this->redirectToRoute('problem_reports');
        } elseif ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_home');
        } elseif ($this->isGranted('ROLE_BIOBANK') || $this->isGranted('ROLE_SCRIPPS')) {
            return $this->redirectToRoute('biobank_home');
        } elseif ($this->isGranted('ROLE_READ_ONLY')) {
            return $this->redirectToRoute('read_home');
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
     * @Route("/program/select", name="program_select")
     */
    public function programSelectAction(Request $request, SiteService $siteService): Response
    {
        if (!$siteService->canSwitchProgram()) {
            throw $this->createAccessDeniedException();
        }
        if ($request->query->has('program')) {
            $program = $request->query->get('program');
            if (in_array($program, User::PROGRAMS)) {
                $request->getSession()->set('program', $program);
                $siteService->resetUserRoles();
                if ($siteService->autoSwitchSite()) {
                    return $this->redirectToRoute('home');
                }
                return $this->redirectToRoute('site_select');
            }
        }
        return $this->render('program-select.html.twig');
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

    /**
     * @Route("/imports", name="imports_home")
     */
    public function importsIndex()
    {
        return $this->render('imports/index.html.twig');
    }

    /**
     * @Route("/notification/{id}", name="notification_details")
     */
    public function notificationDetails($id)
    {
        $featureNotification = $this->em->getRepository(FeatureNotification::class)->find($id);
        $this->createFeatureNotificationUserMap($featureNotification);
        return $this->render('notifications-modal.html.twig', [
            'notification' => $featureNotification
        ]);
    }

    /**
     * @Route("/notifications/mark/read", name="notifications_mark_read")
     */
    public function notificationsMarkRead(): JsonResponse
    {
        $activeNotifications = $this->em->getRepository(FeatureNotification::class)->getActiveNotifications();

        foreach ($activeNotifications as $activeNotification) {
            $this->createFeatureNotificationUserMap($activeNotification);
        }
        return $this->json(['success' => true]);
    }

    private function createFeatureNotificationUserMap($featureNotification): void
    {
        $featureNotificationUserMap = $this->em->getRepository(FeatureNotificationUserMap::class)->findOneBy([
            'featureNotification' => $featureNotification,
            'user' => $this->getUserEntity()
        ]);
        if ($featureNotificationUserMap === null) {
            $featureNotificationUserMap = new FeatureNotificationUserMap();
            $featureNotificationUserMap->setFeatureNotification($featureNotification);
            $featureNotificationUserMap->setUser($this->getUserEntity());
            $featureNotificationUserMap->setCreatedTs(new \DateTime());
            $this->em->persist($featureNotificationUserMap);
            $this->em->flush();
        }
    }
}
