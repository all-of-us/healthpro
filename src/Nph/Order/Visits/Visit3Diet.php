<?php

namespace App\Nph\Order\Visits;

use App\Nph\Order\TimePoints;

class Visit3Diet extends TimePoints
{
    protected $timePoints = [
        'day0' => 'Day 0',
        'day2' => 'Day 2',
        'day12' => 'Day 12'
    ];

    protected $timePointSampleTypes = [
        'day0' => ['urine', 'saliva', 'blood'],
        'day2' => ['24urine'],
        'day12' => ['24urine']
    ];

    public function __construct($module)
    {
        $this->module = $module;
    }
}
