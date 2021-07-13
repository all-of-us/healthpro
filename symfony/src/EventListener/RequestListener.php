<?php

namespace App\EventListener;

use App\Service\EnvironmentService;
use App\Service\SiteService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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
    private $session;
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
        SessionInterface $session,
        UserService $userService,
        SiteService $siteService,
        AuthorizationCheckerInterface $authorizationChecker,
        EnvironmentService $env,
        TokenStorageInterface $tokenStorage
    ) {
        $this->logger = $logger;
        $this->em = $em;
        $this->twig = $twig;
        $this->session = $session;
        $this->userService = $userService;
        $this->siteService = $siteService;
        $this->authorizationChecker = $authorizationChecker;
        $this->env = $env;
        $this->tokenStorage = $tokenStorage;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $this->request = $event->getRequest();

        if (!$event->isMasterRequest() || $this->request->attributes->get('_route') === '_wdt') {
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

        if ($siteSelectResponse = $this->checkSiteSelect()) {
            $event->setResponse($siteSelectResponse);
        }
    }

    private function logRequest()
    {
        $this->logger->log(LoggerService::REQUEST);
    }

    private function checkPageNotices()
    {
        $path = $this->request->getPathInfo();
        $activeNotices = $this->em->getRepository(Notice::class)
            ->getActiveNotices($path);

        // If one of the notices is a full page notice, render the notice response and return
        if (!preg_match('/^(\/s)?\/admin/', $path)) { // Ignore full page notices for admin urls
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

    private function checkSiteSelect()
    {
        $hasMultiple = ($this->authorizationChecker->isGranted('ROLE_DASHBOARD') && ($this->authorizationChecker->isGranted('ROLE_USER') || $this->authorizationChecker->isGranted('ROLE_ADMIN') || $this->authorizationChecker->isGranted('ROLE_AWARDEE') || $this->authorizationChecker->isGranted('ROLE_DV_ADMIN')));
        if ($hasMultiple && $this->session->get('isLoginReturn') && !$this->isUpkeepRoute() && !preg_match('/^(\/s)?\/(splash|_wdt)($|\/).*/',
                $this->request->getPathInfo())) {
            return new RedirectResponse('/s/splash');
        }

        if (!$this->session->has('site') && !$this->session->has('awardee') && ($this->authorizationChecker->isGranted('ROLE_USER') || $this->authorizationChecker->isGranted('ROLE_AWARDEE'))) {
            $user = $this->userService->getUser();
            if (count($user->getSites()) === 1 && empty($user->getAwardees()) && $this->siteService->isValidSite($user->getSites()[0]->email)) {
                $this->siteService->switchSite($user->getSites()[0]->email);
            } elseif (count($user->getAwardees()) === 1 && empty($user->getSites())) {
                $this->siteService->switchSite($user->getAwardees()[0]->email);
            } elseif (!preg_match('/^(\/s)?\/(_profiler|_wdt|cron|admin|help|settings|problem|biobank|review|workqueue|site|login|site_select|splash)($|\/).*/',
                    $this->request->getPathInfo()) && !$this->isUpkeepRoute()) {
                return new RedirectResponse('/s/site/select');
            }
        }
    }

    private function checkLoginExpired()
    {
        // log the user out if their session is expired
        if ($this->userService->isLoginExpired() && $this->request->attributes->get('_route') !== 'logout') {
            return new RedirectResponse('/s/logout?timeout=1');
        }
    }

    public function onKernelFinishRequest()
    {
        if ($this->tokenStorage->getToken() && $this->request && !preg_match('/^(\/s)?\/(login|_wdt)($|\/).*/',
                $this->request->getPathInfo()) && !$this->isUpkeepRoute() && $this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
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

    private function setSessionVariables(): void
    {
        $this->session->set('isLoginReturn', false);
        if (!$this->session->has('userSiteDisplayNames')) {
            if (!empty($userSites = $this->userService->getUser()->getSites())) {
                $userSiteDisplayNames = [];
                foreach ($userSites as $userSite) {
                    $userSiteDisplayNames[$userSite->id] = $this->siteService->getSiteDisplayName($userSite->id, false);
                }
                $this->session->set('userSiteDisplayNames', $userSiteDisplayNames);
            }
        }
    }
}
