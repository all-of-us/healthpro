<?php

namespace App\Service\Nph;

use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Entity\User;
use App\Nph\Order\Samples;
use App\Service\LoggerService;
use App\Service\SiteService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use DateTime;

class NphOrderService
{
    private $em;
    private $userService;
    private $siteService;
    private $loggerService;

    private $module;
    private $visit;
    private $moduleObj;
    private $participantId;
    private $user;
    private $site;

    private static $nonBloodTimePoints = ['preLMT', 'postLMT', 'preDSMT', 'postDSMT'];

    private static $placeholderSamples = ['nail', 'stool'];

    public function __construct(
        EntityManagerInterface $em,
        UserService $userService,
        SiteService $siteService,
        LoggerService $loggerService
    ) {
        $this->em = $em;
        $this->userService = $userService;
        $this->siteService = $siteService;
        $this->loggerService = $loggerService;
    }

    public function loadModules($module, $visit, $participantId): void
    {
        $moduleClass = 'App\Nph\Order\Modules\Module' . $module;
        $this->moduleObj = new $moduleClass($visit);

        $this->module = $module;
        $this->visit = $visit;
        $this->participantId = $participantId;

        $this->user = $this->em->getRepository(User::class)->find($this->userService->getUser()->getId());
        $this->site = $this->siteService->getSiteId();
    }

    public function getTimePointSamples(): array
    {
        return $this->moduleObj->getTimePointSamples();
    }

    public function getTimePoints()
    {
        return $this->moduleObj->getTimePoints();
    }

    public function getSamples()
    {
        return $this->moduleObj->getSamples();
    }

    public function getStoolSamples(): array
    {
        return $this->moduleObj->getStoolSamples();
    }

    public function getNailSamples(): array
    {
        return $this->moduleObj->getNailSamples();
    }

    public function getExistingOrdersData(): array
    {
        $ordersData = [];
        $orders = $this->em->getRepository(NphOrder::class)->getOrdersByVisitType($this->user,
            $this->participantId, $this->visit);
        $addStoolKit = true;
        foreach ($orders as $order) {
            $samples = $order->getNphSamples();
            foreach ($samples as $sample) {
                if (in_array($sample->getSampleCode(), $this->getStoolSamples())) {
                    if ($addStoolKit) {
                        $ordersData['stoolKit'] = $order->getOrderId();
                        $addStoolKit = false;
                    }
                    $ordersData[$sample->getSampleCode()] = $sample->getSampleId();
                } else {
                    $ordersData[$order->getTimepoint()][] = $sample->getSampleCode();
                }
            }
        }
        return $ordersData;
    }

    public function generateOrderId(): string
    {
        $attempts = 0;
        $nphOrderRepository = $this->em->getRepository(NphOrder::class);
        while (++$attempts <= 20) {
            $id = $this->getNumericId();
            if ($nphOrderRepository->findOneBy(['orderId' => $id])) {
                $id = null;
            } else {
                break;
            }
        }
        if (empty($id)) {
            throw new \Exception('Failed to generate unique order id');
        }
        return $id;
    }

    public function generateSampleId(): string
    {
        $attempts = 0;
        $nphSampleRepository = $this->em->getRepository(NphSample::class);
        while (++$attempts <= 20) {
            $id = $this->getNumericId();
            if ($nphSampleRepository->findOneBy(['sampleId' => $id])) {
                $id = null;
            } else {
                break;
            }
        }
        if (empty($id)) {
            throw new \Exception('Failed to generate unique sample id');
        }
        return $id;
    }

    private function getNumericId(): string
    {
        $length = 10;
        // Avoid leading 0s
        $id = (string)rand(1, 9);
        for ($i = 0; $i < $length - 1; $i++) {
            $id .= (string)rand(0, 9);
        }
        return $id;
    }

    public function createOrdersAndSamples($formData)
    {
        foreach ($formData as $timePoint => $samples) {
            if (!empty($samples) && is_array($samples)) {
                if (in_array($timePoint, self::$nonBloodTimePoints)) {
                    foreach ($samples as $sample) {
                        if (!in_array($sample, self::$placeholderSamples)) {
                            $nphOrder = $this->createOrder($timePoint);
                            $this->createSample($sample, $nphOrder);
                        }
                    }
                } else {
                    $nphOrder = $this->createOrder($timePoint);
                    foreach ($samples as $sample) {
                        $this->createSample($sample, $nphOrder);
                    }
                }
            }
        }
        // For stool kit samples
        if (!empty($formData['stoolKit'])) {
            // TODO: dynamically load stool visit type
            $nphOrder = $this->createOrder('preLMT', $formData['stoolKit']);
            foreach ($this->getStoolSamples() as $stoolSample) {
                $this->createSample($stoolSample, $nphOrder, $formData[$stoolSample]);
            }
        }
    }

    public function createOrder($timePoint, $orderId = null): NphOrder
    {
        if ($orderId === null) {
            $orderId = $this->generateOrderId();
        }
        $nphOrder = new NphOrder();
        $nphOrder->setModule($this->module);
        $nphOrder->setVisitType($this->visit);
        $nphOrder->setTimepoint($timePoint);
        $nphOrder->setOrderId($orderId);
        $nphOrder->setParticipantId($this->participantId);
        $nphOrder->setUser($this->user);
        $nphOrder->setSite($this->site);
        $nphOrder->setCreatedTs(new DateTime());
        $this->em->persist($nphOrder);
        $this->em->flush();
        return $nphOrder;
    }

    public function createSample($sample, $nphOrder, $sampleId = null): void
    {
        if ($sampleId === null) {
            $sampleId = $this->generateSampleId();
        }
        $nphSample = new NphSample();
        $nphSample->setNphOrder($nphOrder);
        $nphSample->setSampleId($sampleId);
        $nphSample->setSampleCode($sample);
        $this->em->persist($nphSample);
        $this->em->flush();
    }
}
