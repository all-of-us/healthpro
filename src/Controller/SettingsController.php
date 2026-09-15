<?php

namespace App\Controller;

use App\Entity\UserTimezoneAuditLog;
use App\Form\SettingsType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/settings')]
class SettingsController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    #[Route(path: '/', name: 'settings')]
    public function settings(Request $request): Response
    {
        $user = $this->getUserEntity();
        $previousTimezone = $user->getTimezone();
        $settingsForm = $this->createForm(SettingsType::class, $user);
        $settingsForm->handleRequest($request);
        if ($settingsForm->isSubmitted() && $settingsForm->isValid()) {
            $user = $settingsForm->getData();
            $this->em->persist($user);
            if ($user->getTimezone() !== $previousTimezone) {
                $auditLog = (new UserTimezoneAuditLog())
                    ->setUser($user)
                    ->setPreviousTimezone($previousTimezone)
                    ->setCurrentTimezone($user->getTimezone())
                    ->setClientTimezone($settingsForm->get('clientTimezone')->getData() ?: null)
                    ->setModifiedTs(new \DateTime());
                $this->em->persist($auditLog);
            }
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
