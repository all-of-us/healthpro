<?php

namespace App\Nph\Order\Visits;

use App\Nph\Order\TimePoints;

class VisitDSMT extends TimePoints
{
    protected $timePoints = [
        'preDSMT' => 'Pre DSMT',
        'minus15min' => '-15 min',
        'min5min' => '-5 min',
        '15min' => '15 min',
        '30min' => '30 min',
        '60min' => '60 min',
        '90min' => '90 min',
        '120min' => '120 min',
        '180min' => '180 min',
        '240min' => '240 min',
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
