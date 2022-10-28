<?php

namespace App\Biobank;

class VisitDLW
{
    public $module;

    public $timePoints = [
        'day0PreDoseA' => 'Day 0 Pre Dose A',
        'day0PreDoseB' => 'Day 0 Pre Dose B',
        'day0PostDoseA' => 'Day 0 Post Dose A',
        'day0PostDoseB' => 'Day 0 Post Dose B',
        'day6E' => 'Day 6 E',
        'day7F' => 'Day 7 F',
        'day13G' => 'Day 13 G',
        'day14F' => 'Day 14 F'
    ];

    public function getTimePointsWithSamples(): array
    {
        $module = 'module' . $this->module;
        $timePointSamples = [];
        foreach ($this->timePoints as $key => $timePoint) {
            $timePointSamples[$key] = Samples::${$module . 'UrineSample'};
        }
        return $timePointSamples;
    }
}