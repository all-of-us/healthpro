<?php

namespace App\Controller;

use App\Audit\Log;
use App\Entity\FeatureNotification;
use App\Form\FeatureNotificationType;
use App\Service\LoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/admin/feature-notifications')]
class FeatureNotificationController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    #[Route(path: '/', name: 'admin_feature_notifications')]
    public function index()
    {
        $featureNotifications = $this->em->getRepository(FeatureNotification::class)->findAll();
        return $this->render('featurenotification/index.html.twig', [
            'notifications' => $featureNotifications,
        ]);
    }


    #[Route(path: '/notification/{id}', name: 'admin_feature_notification')]
    public function edit(LoggerService $loggerService, Request $request, $id = null)
    {
        if ($id) {
            $featureNotification = $this->em->getRepository(FeatureNotification::class)->find($id);
            if (!$featureNotification) {
                throw $this->createNotFoundException('Feature notification not found.');
            }
        } else {
            $featureNotification = new FeatureNotification();
            $featureNotification->setStatus(true);
        }

        $form = $this->createForm(
            FeatureNotificationType::class,
            $featureNotification,
            ['timezone' => $this->getSecurityUser()->getTimezone()]
        );

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                if ($featureNotification->getId() === null) {
                    $featureNotification->setCreatedTs(new \DateTime());
                    $this->em->persist($featureNotification);
                    $this->em->flush();
                    $loggerService->log(Log::FEATURE_NOTIFICATION_ADD, $featureNotification->getId());
                    $this->addFlash('success', 'Feature notification added');
                } elseif ($request->request->has('delete')) {
                    $this->em->remove($featureNotification);
                    $this->em->flush();
                    $loggerService->log(Log::FEATURE_NOTIFICATION_DELETE, $featureNotification->getId());
                    $this->addFlash('success', 'Feature notification removed');
                } else {
                    $this->em->persist($featureNotification);
                    $this->em->flush();
                    $loggerService->log(Log::FEATURE_NOTIFICATION_EDIT, $featureNotification->getId());
                    $this->addFlash('success', 'Feature notification updated');
                }
                return $this->redirect($this->generateUrl('admin_feature_notifications'));
            }
            // Add a form-level error if there are none
            if (count($form->getErrors()) == 0) {
                $form->addError(new FormError('Please correct the errors below'));
            }
        }

        return $this->render('featurenotification/edit.html.twig', [
            'notification' => $featureNotification,
            'settings_return_url' => ($id === null)
                ? $this->generateUrl('admin_feature_notifications')
                : $this->generateUrl('admin_feature_notification', ['id' => $id]),
            'form' => $form->createView()
        ]);
    }
}
