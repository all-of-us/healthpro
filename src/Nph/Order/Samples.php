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

    public function getStoolSamples(): array
    {
        return $this->visitObj->getStoolSamples();
    }

    public function getNailSamples(): array
    {
        return $this->visitObj->getNailSamples();
    }

    public function getSampleLabelFromCode($sampleCode): string
    {
        return $this->visitObj->getSampleLabelFromCode($sampleCode);
    }
}
