<?php

namespace App\Biobank;

class VisitDiet
{
    public $module;

    public $timePoints = [
        'day0' => 'Day 0'
    ];

    public function getTimePointsWithSamples(): array
    {
        $module = 'module' . $this->module;
        $timePointSamples = [];
        foreach ($this->timePoints as $key => $timePoint) {
            $timePointSamples[$key] = Samples::${$module . ucfirst($key)};
        }
        return $timePointSamples;
    }
}
