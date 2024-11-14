<?php

namespace App\EventListener;

use App\Audit\Log;
use App\Entity\Notice;
use App\Entity\User;
use App\Service\LoggerService;
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

class RequestListener
{
    private $logger;
    private $em;
    private $twig;
    private $requestStack;
    private $userService;
    private $siteService;
    private $authorizationChecker;
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
        TokenStorageInterface $tokenStorage
    ) {
        $this->logger = $logger;
        $this->em = $em;
        $this->twig = $twig;
        $this->requestStack = $requestStack;
        $this->userService = $userService;
        $this->siteService = $siteService;
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $this->request = $event->getRequest();

        if (!$event->isMainRequest() || in_array($this->request->attributes->get('_route'), ['_wdt', '_profiler'])) {
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
    }

    public function onKernelFinishRequest()
    {
        if ($this->canSetSessionVariables()) {
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

    public function isStreamingResponseRoute()
    {
        $route = $this->request->attributes->get('_route');
        return (in_array($route, [
            'help_sopFile',
            'aliquot_instructions_file'
        ]));
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

    private function checkProgramSelect()
    {
        if (!$this->requestStack->getSession()->has('program') && $this->siteService->canSwitchProgram()) {
            if (!$this->ignoreRoutes() && !$this->isUpkeepRoute()) {
                return new RedirectResponse('/program/select');
            }
        }
    }

    private function checkSiteSelect()
    {
        if (!$this->requestStack->getSession()->has('program') && $this->canSetSessionVariables()) {
            $this->setDefaultProgramSessionVariable();
        }
        if ($this->requestStack->getSession()->has('program') &&
            !$this->requestStack->getSession()->has('site') &&
            !$this->requestStack->getSession()->has('awardee') &&
            (
                $this->authorizationChecker->isGranted('ROLE_USER')
                || $this->authorizationChecker->isGranted('ROLE_NPH_USER')
                || $this->authorizationChecker->isGranted('ROLE_AWARDEE')
            )) {
            if (!$this->siteService->autoSwitchSite() && !$this->ignoreRoutes() && !$this->isUpkeepRoute()) {
                return new RedirectResponse('/site/select');
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

    private function ignoreRoutes(): bool
    {
        return preg_match(
            '/^\/(_profiler|_wdt|cron|admin|nph\/admin|read|help|settings|problem|biobank|review|workqueue|site|login|site_select|program|access\/manage|nph\/biobank|nph\/aliquot\/instructions)($|\/).*/',
            $this->request->getPathInfo()
        );
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
        if (!$this->siteService->canSwitchProgram()) {
            if ($this->authorizationChecker->isGranted('ROLE_NPH_USER') || $this->authorizationChecker->isGranted('ROLE_NPH_BIOBANK')) {
                $this->requestStack->getSession()->set('program', User::PROGRAM_NPH);
            } else {
                $this->requestStack->getSession()->set('program', User::PROGRAM_HPO);
            }
        }
    }

    private function canSetSessionVariables(): bool
    {
        return $this->tokenStorage->getToken() &&
            $this->request && !preg_match('/^\/(login|_wdt)($|\/).*/', $this->request->getPathInfo()) &&
            !$this->isUpkeepRoute() &&
            !$this->isStreamingResponseRoute() && $this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY');
    }
}
