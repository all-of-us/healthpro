<?php

namespace App\Controller;

use App\HttpClient;
use App\Service\HelpService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/nph/help')]
class HelpController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    #[Route(path: '/', name: 'help_nph')]
    public function nphAction(HelpService $helpService): Response
    {
        return $this->render('help/nph/index.html.twig');
    }

    #[Route(path: '/faq', name: 'help_nph_faq')]
    public function nphFaqAction(HelpService $helpService): Response
    {
        return $this->render('help/nph/faq.html.twig', ['faqs' => $helpService::$faqs]);
    }

    #[Route(path: '/sop/{id}/{language}', name: 'help_nph_sopView')]
    public function nphSopViewAction($id, $language, HelpService $helpService): Response
    {
        $document = $helpService->getDocumentInfo($id, 'nph');
        if (!$document) {
            throw $this->createNotFoundException('Page Not Found!');
        }
        return $this->render('help/nph/sop-pdf.html.twig', [
            'sop' => $id,
            'document' => $document,
            'language' => $language,
            'path' => $helpService->getStoragePath()
        ]);
    }

    #[Route(path: '/sop/file/{id}/{language}/{documentGroup}', name: 'help_sopFile')]
    public function sopFileAction(string $id, string $language, HelpService $helpService, string $documentGroup = 'hpo')
    {
        $document = $helpService->getDocumentInfo($id, $documentGroup);
        if (!$document) {
            throw $this->createNotFoundException('Page Not Found!');
        }
        if ($language !== 'en') {
            $pattern = '/(.*)(\.pdf)/';
            $replacement = "\${1}($language)\${2}";
            $documentFile = preg_replace($pattern, $replacement, $document['filename']);
        } else {
            $documentFile = $document['filename'];
        }
        $url = $helpService->getStoragePath() . '/' . rawurlencode($documentFile);
        try {
            $client = new HttpClient();
            $response = $client->get($url, ['stream' => true]);
            $responseBody = $response->getBody();
            $streamedResponse = new StreamedResponse(function () use ($responseBody) {
                while (!$responseBody->eof()) {
                    echo $responseBody->read(1024); // phpcs:ignore WordPress.XSS.EscapeOutput
                }
            });
            $streamedResponse->headers->set('Content-Type', 'application/pdf');
            return $streamedResponse;
        } catch (\Exception $e) {
            error_log('Failed to retrieve Confluence file ' . $url . ' (' . $id . ')');
            echo '<html><body style="font-family: Helvetica Neue,Helvetica,Arial,sans-serif"><strong>File could not be loaded</strong></body></html>';
            exit;
        }
    }
}
