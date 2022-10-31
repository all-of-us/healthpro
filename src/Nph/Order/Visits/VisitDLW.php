<?php

namespace App\Nph\Order\Visits;

use App\Biobank\Samples;

class VisitDLW
{
    public static $timePoints = [
        'day0PreDoseA' => 'Day 0 Pre Dose A',
        'day0PreDoseB' => 'Day 0 Pre Dose B',
        'day0PostDoseA' => 'Day 0 Post Dose A',
        'day0PostDoseB' => 'Day 0 Post Dose B',
        'day6E' => 'Day 6 E',
        'day7F' => 'Day 7 F',
        'day13G' => 'Day 13 G',
        'day14F' => 'Day 14 F'
    ];

    public function getSamples($module): array
    {
        $module = 'module' . $module;
        $timePointSamples = [];
        foreach (self::$timePoints as $key => $timePoint) {
            $timePointSamples[$key] = Samples::${$module . 'UrineSample'};
        }
        return $timePointSamples;
    }
}
