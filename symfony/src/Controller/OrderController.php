<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\User;
use App\Form\OrderCreateType;
use App\Service\LoggerService;
use App\Service\OrderService;
use App\Service\ParticipantSummaryService;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;
use Pmi\Audit\Log;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;

/**
 * @Route("/s")
 */
class OrderController extends AbstractController
{
    /**
     * @Route("/participant/{participantId}/{orderId}/order", name="order")
     */
    public function orderAction($participantId, $orderId, OrderService $orderService, EntityManagerInterface $em)
    {
        $orderRepository = $em->getRepository(Order::class);
        $order = $orderRepository->find($orderId);
        if (empty($order)) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $orderService->loadSamplesSchema($order);
        $action = $orderService->getCurrentStep();
        return $this->redirectToRoute("order_{$action}", [
            'participantId' => $participantId,
            'orderId' => $orderId
        ]);
    }

    /**
     * @Route("/participant/{participantId}/order/check", name="order_check")
     */
    public function orderCheck($participantId, ParticipantSummaryService $participantSummaryService, SiteService $siteService)
    {
        $participant = $participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        if (!$participant->status || $siteService->isTestSite() || $participant->activityStatus === 'deactivated') {
            throw $this->createAccessDeniedException('Participant ineligible for order create.');
        }
        return $this->render('order/check.html.twig', [
            'participant' => $participant
        ]);
    }

    /**
     * @Route("/participant/{participantId}/order/create", name="order_create")
     */
    public function orderCreateAction(
        $participantId,
        Request $request,
        SessionInterface $session,
        OrderService $orderService,
        ParticipantSummaryService $participantSummaryService,
        SiteService $siteService,
        EntityManagerInterface $em,
        LoggerService $loggerService
    ) {
        $participant = $participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        if (!$participant->status || $siteService->isTestSite() || ($session->get('siteType') === 'dv' && $request->request->has('saliva')) || $participant->activityStatus === 'deactivated') {
            throw $this->createAccessDeniedException('Participant ineligible for order create.');
        }
        if ($request->request->has('show-blood-tubes') && $request->request->has('show-saliva-tubes')) {
            $showBloodTubes = $request->request->get('show-blood-tubes') === 'yes' ? true : false;
            $showSalivaTubes = $request->request->get('show-saliva-tubes') === 'yes' ? true : false;
        } else {
            throw $this->createAccessDeniedException('Participant ineligible for order create.');
        }
        $order = new Order;
        $orderService->loadSamplesSchema($order);
        $createForm = $this->createForm(OrderCreateType::class, null, [
            'orderType' => $session->get('orderType'),
            'samples' => $order->getSamples(),
            'showBloodTubes' => $showBloodTubes,
            'nonBloodSamples' => $order::$nonBloodSamples
        ]);
        $showCustom = false;
        $createForm->handleRequest($request);
        if (!$createForm->isSubmitted() && !$this->get('security.csrf.token_manager')->isTokenValid(new CsrfToken('orderCheck', $request->request->get('csrf_token')))) {
            throw $this->createAccessDeniedException('Participant ineligible for order create.');
        }
        if ($createForm->isSubmitted() && $createForm->isValid()) {
            if ($request->request->has('existing')) {
                $orderRepository = $em->getRepository(Order::class);
                if (empty($createForm['kitId']->getData())) {
                    $createForm['kitId']['first']->addError(new FormError('Please enter a kit order ID'));
                } elseif ($orderRepository->findOneBy(['order_id' => $createForm['kitId']->getData()])) {
                    $createForm['kitId']['first']->addError(new FormError('This order ID already exists'));
                } else {
                    $order->setOrderId($createForm['kitId']->getData());
                    $order->setType('kit');
                    if (!$showBloodTubes) {
                        $order->setRequestedSamples(json_encode([$order->getUrineSample()]));
                    }
                }
            } else {
                if ($request->request->has('custom')) {
                    $showCustom = true;
                    $requestedSamples = $createForm['samples']->getData();
                    if (empty($requestedSamples) || !is_array($requestedSamples)) {
                        $createForm['samples']->addError(new FormError('Please select at least one sample'));
                    } else {
                        $order->setRequestedSamples(json_encode($requestedSamples));
                    }
                } elseif ($request->request->has('saliva')) {
                    $order->setType('saliva');
                }
            }
            if ($createForm->isValid()) {
                $userRepository = $em->getRepository(User::class);
                $order->setUser($userRepository->find($this->getUser()->getId()));
                $order->setSite($siteService->getSiteId());
                $order->setParticipantId($participant->id);
                $order->setBiobankId($participant->biobankId);
                $order->setCreatedTs(new \DateTime());
                $order->setVersion($order->getCurrentVersion());
                if ($session->get('orderType') === 'hpo') {
                    $order->setProcessedCentrifugeType(Order::SWINGING_BUCKET);
                }
                if ($session->get('siteType') === 'dv' && $siteService->isDiversionPouchSite()) {
                    $order->setType('diversion');
                }
                $order->setOrderId($orderService->generateId());
                $em->persist($order);
                $em->flush();
                $orderId = $order->getId();
                if ($orderId) {
                    $loggerService->log(Log::ORDER_CREATE, $orderId);
                    return $this->redirectToRoute('order', [
                        'participantId' => $participant->id,
                        'orderId' => $orderId
                    ]);
                }
                $this->addFlash('error', 'Failed to create order.');
            }
        }
        if (!$showBloodTubes) {
            $showCustom = true;
        }
        return $this->render('order/create.html.twig', [
            'participant' => $participant,
            'createForm' => $createForm->createView(),
            'showCustom' => $showCustom,
            'samplesInfo' => $order->getSamplesInformation(),
            'showBloodTubes' => $showBloodTubes,
            'showSalivaTubes' => $showSalivaTubes,
            'version' => $order->getVersion(),
            'salivaInstructions' => $order->getSalivaInstructions(),
        ]);
    }
}
