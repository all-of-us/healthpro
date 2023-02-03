<?php

namespace App\Nph\Order\Visits;

use App\Nph\Order\TimePoints;

class VisitDSMT extends TimePoints
{
    protected $timePoints = [
        'preDSMT' => 'Pre DSMT',
        'minus15min' => '-15 Min',
        'min5min' => '-5 Min',
        '15min' => '15 Min',
        '30min' => '30 Min',
        '60min' => '60 Min',
        '90min' => '90 Min',
        '120min' => '120 Min',
        '180min' => '180 Min',
        '240min' => '240 Min',
        'postDSMT' => 'Post DSMT'
    ];

    protected $timePointSampleTypes = [
        'preDSMT' => ['urine', 'saliva'],
        'postDSMT' => ['urine', 'saliva']
    ];

    public function __construct($module)
    {
        $this->module = $module;
    }
}
