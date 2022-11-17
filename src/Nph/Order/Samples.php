<?php

namespace App\Nph\Order;

class Samples
{
    public $visitObj;

    public function __construct($module, $visit)
    {
        $visitClass = 'App\Nph\Order\Visits\Visit' . $visit;
        $this->visitObj = new $visitClass($module);
    }

    public function getTimePointSamples(): array
    {
        return $this->visitObj->getTimePointSamples();
    }

    public function getTimePoints(): array
    {
        return $this->visitObj->timePoints;
    }

    public function getSamples(): array
    {
        return $this->visitObj->getSamples();
    }

    public function getSamplesByType($type): array
    {
        return $this->visitObj->getSamplesByType($type);
    }

    public function getSampleType($sample): string
    {
        return $this->visitObj->getSampleType($sample);
    }
}
