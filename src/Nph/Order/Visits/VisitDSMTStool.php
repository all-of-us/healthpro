<?php

namespace App\Nph\Order\Visits;

use App\Nph\Order\TimePoints;

class VisitDSMTStool extends TimePoints
{
    protected $timePoints = [
        'preDSMT' => 'Pre DSMT'
    ];

    protected $rdrTimePoints = [
        'minus15min' => 'Minus 15 min',
        'minus5min' => 'Minus 5 min',
    ];

    protected $timePointSampleTypes = [
        'preDSMT' => ['stool']
    ];

    public function __construct($module)
    {
        $this->module = $module;
    }
}
