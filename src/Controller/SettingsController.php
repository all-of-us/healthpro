<?php

namespace App\Controller;

use App\Form\SettingsType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/settings")
 */
class SettingsController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    /**
     * @Route("/", name="settings")
     */
    public function settings(Request $request)
    {
        $user = $this->getUserEntity();
        $settingsForm = $this->createForm(SettingsType::class, $user);
        $settingsForm->handleRequest($request);
        if ($settingsForm->isSubmitted() && $settingsForm->isValid()) {
            $user = $settingsForm->getData();
            $this->em->persist($user);
            $this->em->flush();
            $this->addFlash('success', 'Your settings have been updated');
            if ($request->query->has('return') && preg_match('/^\/\w/', $request->query->get('return'))) {
                return $this->redirect($request->query->get('return'));
            }
            return $this->redirectToRoute('home');
        }

        return $this->render('settings/settings.html.twig', [
            'settingsForm' => $settingsForm->createView()
        ]);
    }
}
