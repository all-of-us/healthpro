<?php

namespace App\Nph\Order\Visits;

use App\Nph\Order\TimePoints;

class VisitDiet extends TimePoints
{
    protected $timePoints = [
        'day0' => 'Day 0'
    ];

    protected $timePointSampleTypes = [
        'day0' => ['urine', 'saliva', 'blood']
    ];

    public function __construct($module)
    {
        $this->module = $module;
    }
}
