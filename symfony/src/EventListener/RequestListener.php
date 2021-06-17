<?php

namespace App\EventListener;

use App\Service\SiteService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
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

    private $request;

    public function __construct(LoggerService $logger, EntityManagerInterface $em, TwigEnvironment $twig, SessionInterface $session, UserService $userService, SiteService $siteService, AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->logger = $logger;
        $this->em = $em;
        $this->twig = $twig;
        $this->session = $session;
        $this->userService = $userService;
        $this->siteService = $siteService;
        $this->authorizationChecker = $authorizationChecker;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $this->request = $event->getRequest();

        $this->logRequest();

        if ($siteSelectResponse = $this->checkSiteSelect()) {
            $event->setResponse($siteSelectResponse);
        }

        if ($fullPageNoticeResponse = $this->checkPageNotices()) {
            $event->setResponse($fullPageNoticeResponse);
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
        if ($hasMultiple && $this->session->get('isLoginReturn') && !$this->isUpkeepRoute() && !preg_match('/^(\/s)?\/(splash)($|\/).*/', $this->request->getPathInfo())) {
            return new RedirectResponse('/s/splash');
        }

        if (!$this->session->has('site') && !$this->session->has('awardee') && ($this->authorizationChecker->isGranted('ROLE_USER') || $this->authorizationChecker->isGranted('ROLE_AWARDEE'))) {
            $user = $this->userService->getUser();
            if (count($user->getSites()) === 1 && empty($user->getAwardees()) && $this->siteService->isValidSite($user->getSites()[0]->email)) {
                $this->siteService->switchSite($user->getSites()[0]->email);
            } elseif (count($user->getAwardees()) === 1 && empty($user->getSites())) {
                $this->siteService->switchSite($user->getAwardees()[0]->email);
            } elseif (!preg_match('/^(\/s)?\/(_profiler|_wdt|cron|admin|help|settings|problem|biobank|review|workqueue|site|login|site_select)($|\/).*/',
                    $this->request->getPathInfo()) && !$this->isUpkeepRoute()) {
                return new RedirectResponse('/s/site/select');
            }
        }
    }

    public function onKernelFinishRequest()
    {
        if ($this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            $this->session->set('isLoginReturn', false);
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
                'loginReturn',
                'timeout',
                'keepAlive',
                'clientTimeout',
                'agreeUsage'
            ]));
    }
}
