<?php

namespace App\Service\Nph;

class NphOrderService
{
    public function getTimePointsWithSamples($module, $visit): array
    {
        $moduleClass = 'App\Nph\Order\Modules\Module' . $module;
        $module = new $moduleClass($visit);
        return $module->getSamples();
    }
}
