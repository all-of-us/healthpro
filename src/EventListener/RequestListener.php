<?php

namespace App\EventListener;

use App\Audit\Log;
use App\Entity\FeatureNotification;
use App\Entity\FeatureNotificationUserMap;
use App\Entity\User;
use App\Service\EnvironmentService;
use App\Service\SiteService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment as TwigEnvironment;
use App\Entity\Notice;
use App\Service\LoggerService;

class RequestListener
{
    private $logger;
    private $em;
    private $twig;
    private $requestStack;
    private $userService;
    private $siteService;
    private $authorizationChecker;
    private $env;
    private $tokenStorage;

    private $request;

    public function __construct(
        LoggerService $logger,
        EntityManagerInterface $em,
        TwigEnvironment $twig,
        RequestStack $requestStack,
        UserService $userService,
        SiteService $siteService,
        AuthorizationCheckerInterface $authorizationChecker,
        EnvironmentService $env,
        TokenStorageInterface $tokenStorage
    ) {
        $this->logger = $logger;
        $this->em = $em;
        $this->twig = $twig;
        $this->requestStack = $requestStack;
        $this->userService = $userService;
        $this->siteService = $siteService;
        $this->authorizationChecker = $authorizationChecker;
        $this->env = $env;
        $this->tokenStorage = $tokenStorage;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $this->request = $event->getRequest();

        if (!$event->isMasterRequest() || in_array($this->request->attributes->get('_route'), ['_wdt', '_profiler'])) {
            return;
        }

        $this->logRequest();

        if ($loginExpiredResponse = $this->checkLoginExpired()) {
            $event->setResponse($loginExpiredResponse);
            return;
        }

        if ($fullPageNoticeResponse = $this->checkPageNotices()) {
            $event->setResponse($fullPageNoticeResponse);
            return;
        }

        if ($programSelectResponse = $this->checkProgramSelect()) {
            $event->setResponse($programSelectResponse);
            return;
        }

        if ($siteSelectResponse = $this->checkSiteSelect()) {
            $event->setResponse($siteSelectResponse);
        }

        $this->checkFeatureNotifications();
    }

    private function logRequest()
    {
        $this->logger->log(Log::REQUEST);
    }

    private function checkPageNotices()
    {
        $path = $this->request->getPathInfo();
        $activeNotices = $this->em->getRepository(Notice::class)
            ->getActiveNotices($path);

        // If one of the notices is a full page notice, render the notice response and return
        if (!preg_match('/^\/admin/', $path)) { // Ignore full page notices for admin urls
            foreach ($activeNotices as $notice) {
                if ($notice->getFullPage()) {
                    return new Response($this->twig->render('full-page-notice.html.twig', [
                        'message' => $notice->getMessage()
                    ]));
                }
            }
        }

        $this->twig->addGlobal('global_notices', $activeNotices);
    }

    private function checkFeatureNotifications(): void
    {
        $activeNotifications = $this->em->getRepository(FeatureNotification::class)->getActiveNotifications();
        $this->twig->addGlobal('global_notifications', $activeNotifications);

        $userNotificationIds = $activeNotifications ? $this->em->getRepository(FeatureNotificationUserMap::class)
            ->getUserNotificationIds($this->userService->getUserEntity()) : null;
        $this->twig->addGlobal('user_notification_ids', $userNotificationIds);

        $notificationsCount = 0;
        foreach ($activeNotifications as $activeNotification) {
            if (!in_array($activeNotification->getId(), $userNotificationIds)) {
                $notificationsCount++;
            }
        }
        $this->twig->addGlobal('notifications_count', $notificationsCount);
    }

    private function checkProgramSelect()
    {
        if (!$this->requestStack->getSession()->has('program') && $this->canSwitchProgram()) {
            if (!$this->ignoreRoutes() && !$this->isUpkeepRoute()) {
                return new RedirectResponse('/program/select');
            }
        }
    }

    private function checkSiteSelect()
    {
        if (!$this->requestStack->getSession()->has('site') && !$this->requestStack->getSession()->has('awardee') && ($this->authorizationChecker->isGranted('ROLE_USER') || $this->authorizationChecker->isGranted('ROLE_AWARDEE'))) {
            $user = $this->userService->getUser();
            $program = $this->requestStack->getSession()->get('program');
            $sites = $program === User::PROGRAM_HPO ? $user->getSites() : $user->getNphSites();
            if ($program === User::PROGRAM_HPO) {
                if (count($sites) === 1 && empty($user->getAwardees()) && $this->siteService->isValidSite($sites[0]->email)) {
                    $this->siteService->switchSite($sites[0]->email);
                } elseif (count($user->getAwardees()) === 1 && empty($sites)) {
                    $this->siteService->switchSite($user->getAwardees()[0]->email);
                } elseif (!$this->ignoreRoutes() && !$this->isUpkeepRoute()) {
                    return new RedirectResponse('/site/select');
                }
            } else {
                if (count($sites) === 1 && $this->siteService->isValidSite($sites[0]->email)) {
                    $this->siteService->switchSite($sites[0]->email);
                } elseif (!$this->ignoreRoutes() && !$this->isUpkeepRoute()) {
                    return new RedirectResponse('/site/select');
                }
            }
        }
    }

    private function checkLoginExpired()
    {
        // log the user out if their requestStack->getSession() is expired
        if ($this->userService->isLoginExpired() && $this->request->attributes->get('_route') !== 'logout') {
            return new RedirectResponse('/logout?timeout=1');
        }
    }

    public function onKernelFinishRequest()
    {
        if ($this->tokenStorage->getToken() && $this->request && !preg_match(
            '/^\/(login|_wdt)($|\/).*/',
            $this->request->getPathInfo()
        ) && !$this->isUpkeepRoute() && !$this->isStreamingResponseRoute() && $this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            $this->setSessionVariables();
        }
    }

    /**
     * "Upkeep" routes are routes that we typically want to allow through
     * even when workflow dictates otherwise.
     */
    public function isUpkeepRoute()
    {
        $route = $this->request->attributes->get('_route');
        return (in_array($route, [
            'logout',
            'timeout',
            'keep_alive',
            'client_timeout',
            'agree_usage'
        ]));
    }

    private function ignoreRoutes(): bool
    {
        return preg_match(
            '/^\/(_profiler|_wdt|cron|admin|read|help|settings|problem|biobank|review|workqueue|site|login|site_select|program|access\/manage)($|\/).*/',
            $this->request->getPathInfo()
        );
    }

    public function isStreamingResponseRoute()
    {
        $route = $this->request->attributes->get('_route');
        return (in_array($route, [
            'workqueue_export',
            'help_sopFile',
            'on_site_patient_status_export',
            'on_site_incentive_tracking_export',
            'on_site_id_verification_export'
        ]));
    }

    private function setSessionVariables(): void
    {
        $this->requestStack->getSession()->set('isLoginReturn', false);
        if (!$this->requestStack->getSession()->has('userSiteDisplayNames')) {
            if (!empty($userSites = $this->userService->getUser()->getSites())) {
                $userSiteDisplayNames = [];
                foreach ($userSites as $userSite) {
                    $userSiteDisplayNames[$userSite->id] = $this->siteService->getSiteDisplayName($userSite->id, false);
                }
                $this->requestStack->getSession()->set('userSiteDisplayNames', $userSiteDisplayNames);
            }
        }
        if (!$this->requestStack->getSession()->has('program')) {
            $this->setDefaultProgramSessionVariable();
        }
    }

    private function setDefaultProgramSessionVariable(): void
    {
        // Default program should not be set if user has option to switch programs
        if (!$this->canSwitchProgram()) {
            if ($this->authorizationChecker->isGranted('ROLE_NPH_USER')) {
                $this->requestStack->getSession()->set('program', User::PROGRAM_NPH);
            } else {
                $this->requestStack->getSession()->set('program', User::PROGRAM_HPO);
            }
        }
    }

    private function canSwitchProgram(): bool
    {
        if ($this->userService->getUser()) {
            $roles = $this->userService->getUser()->getRoles();
            return in_array('ROLE_NPH_USER', $roles) && count($roles) > 1;
        }
        return false;
    }
}
