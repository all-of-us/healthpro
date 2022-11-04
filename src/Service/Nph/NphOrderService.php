<?php

namespace App\Service\Nph;

use App\Entity\NphOrder;
use App\Entity\NphSample;
use Doctrine\ORM\EntityManagerInterface;

class NphOrderService
{
    public $module;

    public $visit;

    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function loadModules($module, $visit): void
    {
        $moduleClass = 'App\Nph\Order\Modules\Module' . $module;
        $this->module = new $moduleClass($visit);

        $visitClass = 'App\Nph\Order\Visits\Visit' . $this->module->visit;
        $this->visit = new $visitClass($module);
    }

    public function getTimePointSamples(): array
    {
        return $this->module->getSamples();
    }

    public function getTimePoints()
    {
        return $this->visit->timePoints;
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
}
