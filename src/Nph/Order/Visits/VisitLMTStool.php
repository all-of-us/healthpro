<?php

namespace App\Nph\Order\Visits;

use App\Nph\Order\TimePoints;

class VisitLMTStool extends TimePoints
{
    protected $timePoints = [
        'preLMT' => 'Pre LMT'
    ];

    protected $rdrTimePoints = [
        'minus15min' => 'Minus 15 min',
        'minus5min' => 'Minus 5 min',
    ];

    protected $timePointSampleTypes = [
        'preLMT' => ['stool']
    ];

    public function __construct($module)
    {
        $this->module = $module;
    }
}
