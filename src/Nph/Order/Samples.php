<?php

namespace App\Nph\Order;

class Samples
{
    public $module;

    public $visit;

    public static $stoolSamples = ['ST1', 'ST2', 'ST3', 'ST4'];

    public function getSamples(): array
    {
        $visitClass = 'App\Nph\Order\Visits\Visit' . $this->visit;
        $visitType = new $visitClass($this->module);
        return $visitType->getSamples();
    }
}
