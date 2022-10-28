<?php

namespace App\Service\Nph;

class NphOrderService
{
    public function getTimePointsWithSamples($module, $visit): array
    {
        $moduleClass = 'App\Nph\Order\Module' . $module . $visit;
        $module = new $moduleClass();
        return $module->getTimePointsWithSamples();
    }
}
