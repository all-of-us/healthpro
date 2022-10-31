<?php

namespace App\Nph\Order\Visits;

use App\Biobank\Samples;

class VisitDiet
{
    public static $timePoints = [
        'day0' => 'Day 0'
    ];

    public function getSamples($module): array
    {
        $module = 'module' . $module;
        $timePointSamples = [];
        foreach (self::$timePoints as $key => $timePoint) {
            $timePointSamples[$key] = Samples::${$module . ucfirst($key)};
        }
        return $timePointSamples;
    }
}
