<?php

namespace App\Service\Nph;

class NphOrderService
{
    public $module;

    public $visit;

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
}
