<?php

namespace App\Biobank;

class VisitDiet
{
    public static $timePoints = [
        'day0' => 'Day 0'
    ];

    public function getTimePointsWithSamples($module): array
    {
        $module = 'module' . $module;
        $timePointSamples = [];
        foreach (self::$timePoints as $key => $timePoint) {
            $timePointSamples[$key] = Samples::${$module . ucfirst($key)};
        }
        return $timePointSamples;
    }
}
