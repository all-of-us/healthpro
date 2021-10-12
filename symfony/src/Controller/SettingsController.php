<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\SettingsType;
use App\Repository\NoticeRepository;
use App\Repository\UserRepository;
use App\Service\LoggerService;
use App\Service\TimezoneService;
use Doctrine\ORM\EntityManagerInterface;
use App\Audit\Log;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints;

/**
 * @Route("/s/settings")
 */
class SettingsController extends AbstractController
{
    /**
     * @Route("/", name="settings")
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
