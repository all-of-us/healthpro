<?php

namespace App\Controller;

use App\Form\SettingsType;
use App\Repository\UserRepository;
use App\Service\TimezoneService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SettingsController extends AbstractController
{
    /**
     * @Route("/settings", name="settings")
     * @Route("/read/settings", name="read_settings")
     */
    public function settings(Request $request, TimezoneService $timezeoneService, UserRepository $userRepository, EntityManagerInterface $em)
    {
        $user = $userRepository->find($this->getUser()->getId());
        $settingsForm = $this->createForm(SettingsType::class, $user);
        $settingsForm->handleRequest($request);
        if ($settingsForm->isSubmitted() && $settingsForm->isValid()) {
            $user = $settingsForm->getData();
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', 'Your settings have been updated');
            if ($request->query->has('return') && preg_match('/^\/\w/', $request->query->get('return'))) {
                return $this->redirect($request->query->get('return'));
            } else {
                return $this->redirectToRoute('home');
            }
        }

        return $this->render('settings/settings.html.twig', [
            'settingsForm' => $settingsForm->createView()
        ]);
    }
}
