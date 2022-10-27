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
        $timePointSamples = [];
        foreach ($this->timePoints as $key => $timePoint) {
            $timePointSamples[$key] = Samples::${$this->module . ucfirst($key)};
        }
        return $timePointSamples;
    }
}
