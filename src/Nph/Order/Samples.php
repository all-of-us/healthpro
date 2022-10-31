<?php

namespace App\Nph\Order;

class Samples
{
    public $module;

    public $visit;

    public function getSamples(): array
    {
        $visitClass = 'App\Biobank\Visit' . $this->visit;
        return $visitClass::getTimePointsWithSamples($this->module);
    }
}
