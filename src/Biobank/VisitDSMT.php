<?php

namespace App\Biobank;

class VisitDSMT
{
    public static $timePoints = [
        'preDSTT' => 'Pre DSTT',
        'minus15min' => '-15 Min',
        'min5min' => '-5 Min',
        '15min' => '15 Min',
        '30min' => '30 Min',
        '60min' => '60 Min',
        '90min' => '90 Min',
        '120min' => '120 Min',
        '240min' => '240 Min',
        'postDSTT' => 'Post DSTT'
    ];

    public function getTimePointsWithSamples($module): array
    {
        $module = 'module' . $module;
        $timePointSamples = [];
        foreach (self::$timePoints as $key => $timePoint) {
            if ($key === 'preDSTT' || $key === 'postDSTT') {
                $timePointSamples[$key] = Samples::${$module . ucfirst($key)};
            } else {
                $timePointSamples[$key] = Samples::${$module . 'BloodSamples'};
            }
        }
        return $timePointSamples;
    }
}
