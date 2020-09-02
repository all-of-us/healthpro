<?php

namespace App\Controller;

use App\Service\HelpService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/s/help")
 */
class HelpController extends AbstractController
{
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
        $id = $session->get('sitType') === 'dv' ? 'biobank-dv' : 'biobank-hpo';
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
}
