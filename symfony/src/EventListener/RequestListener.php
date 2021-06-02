<?php

namespace App\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Twig\Environment as TwigEnvironment;
use App\Entity\Notice;
use App\Service\LoggerService;

class RequestListener
{
    private $logger;
    private $em;
    private $twig;
    private $session;

    private $request;

    public function __construct(LoggerService $logger, EntityManagerInterface $em, TwigEnvironment $twig, SessionInterface $session)
    {
        $this->logger = $logger;
        $this->em = $em;
        $this->twig = $twig;
        $this->session = $session;
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
        if (!$this->session->has('site') && !preg_match('/^(\/s)?\/(_profiler|_wdt|cron|admin|help|settings|problem|biobank|review|workqueue|site)($|\/).*/',
                $this->request->getPathInfo())) {
            return new RedirectResponse('/');
        }
    }
}
