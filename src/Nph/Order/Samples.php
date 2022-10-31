<?php

namespace App\Nph\Order;

class Samples
{
    public $module;

    public $visit;

    public function getSamples(): array
    {
        $visitClass = 'App\Nph\Order\Visits\Visit' . $this->visit;
        return $visitClass::getSamples($this->module);
    }
}
