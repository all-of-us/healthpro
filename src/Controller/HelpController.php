<?php

namespace App\Controller;

use App\HttpClient;
use App\Service\HelpService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/help")
 */
class HelpController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    /**
     * @Route("/", name="help_home")
     */
    public function index()
    {
        return $this->render('help/index.html.twig');
    }

    /**
     * @Route("/videos", name="help_videos")
     */
    public function videosAction(SessionInterface $session)
    {
        $id = $session->get('orderType') === 'dv' ? 'biobank-dv' : 'biobank-hpo';
        return $this->redirectToRoute('help_videosPlaylist', ['id' => $id]);
    }

    /**
     * @Route("/videos/{id}", name="help_videosPlaylist")
     */
    public function videosPlaylistAction($id, Request $request, HelpService $helpService)
    {
        if (!array_key_exists($id, $helpService::$videoPlaylists)) {
            throw $this->createNotFoundException('Page Not Found!');
        }

        $parameters = [
            'videoPlaylists' => $helpService::$videoPlaylists,
            'active' => $id
        ];
        if ($id === 'other') {
            $parameters['type'] = $request->query->get('type', 'yt');
            $parameters['helpVideosPath'] = rtrim($this->getParameter('help_videos_path'), '/');
        }

        return $this->render('help/videos.html.twig', $parameters);
    }

    /**
     * @Route("/faq", name="help_faq")
     */
    public function faqAction(HelpService $helpService)
    {
        return $this->render('help/faq.html.twig', ['faqs' => $helpService::$faqs]);
    }

    /**
     * @Route("/sop", name="help_sop")
     */
    public function sopAction(HelpService $helpService)
    {
        return $this->render('help/sop.html.twig', [
            'documentGroups' => $helpService::$documentGroups,
            'path' => $helpService->getStoragePath(),
            'supportedLanguages' => $helpService::$SupportedLanguages
        ]);
    }

    /**
     * @Route("/sop/{id}/{language}", name="help_sopView")
     */
    public function sopViewAction($id, $language, HelpService $helpService)
    {
        $document = $helpService->getDocumentInfo($id);
        if (!$document) {
            throw $this->createNotFoundException('Page Not Found!');
        }


        return $this->render('help/sop-pdf.html.twig', [
            'sop' => $id,
            'title' => trim(str_replace($id, '', $document['title'])),
            'document' => $document,
            'language' => $language,
            'path' => $helpService->getStoragePath()
        ]);
    }

    /**
     * @Route("/sop/file/{id}/{language}", name="help_sopFile")
     */
    public function sopFileAction($id, $language, HelpService $helpService)
    {
        $document = $helpService->getDocumentInfo($id);
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

    /**
     * @Route("/sop/redirect/{id}", name="help_sopRedirect")
     */
    public function sopRedirectAction($id, HelpService $helpService)
    {
        $document = $helpService->getDocumentInfo($id);
        if (!$document) {
            throw $this->createNotFoundException('Page Not Found!');
        }
        $url = $helpService->getStoragePath() . '/' . rawurlencode($document['filename']);
        return $this->redirect($url);
    }
}
