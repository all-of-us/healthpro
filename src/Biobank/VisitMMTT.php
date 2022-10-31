<?php

namespace App\Biobank;

class VisitMMTT
{
    public static $timePoints = [
        'preMMTT' => 'Pre MMTT',
        'minus15min' => '-15 Min',
        'minus5min' => '-5 Min',
        '15min' => '15 Min',
        '30min' => '30 Min',
        '60min' => '60 Min',
        '90min' => '90 Min',
        '120min' => '120 Min',
        '240min' => '240 Min',
        'postMMTT' => 'Post MMTT'
    ];

    public static function getTimePointsWithSamples($module): array
    {
        $module = 'module' . $module;
        $timePointSamples = [];
        foreach (self::$timePoints as $key => $timePoint) {
            if ($key === 'preMMTT' || $key === 'postMMTT') {
                $timePointSamples[$key] = Samples::${$module . ucfirst($key)};
            } else {
                $timePointSamples[$key] = Samples::${$module . 'BloodSamples'};
            }
        }
        return $timePointSamples;
    }
}
