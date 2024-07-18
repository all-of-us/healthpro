<?php

namespace App\Nph\Order\Visits;

use App\Nph\Order\TimePoints;

class Visit3DSMT extends TimePoints
{
    protected $timePoints = [
        'preDSMT' => 'Pre DSMT',
        'minus15min' => '-15 min',
        'minus5min' => '-5 min',
        '15min' => '15 min',
        '30min' => '30 min',
        '60min' => '60 min',
        '90min' => '90 min',
        '120min' => '120 min',
        '180min' => '180 min',
        '240min' => '240 min',
        'postDSMT' => 'Post DSMT'
    ];

    protected $rdrTimePoints = [
        'minus15min' => 'Minus 15 min',
        'minus5min' => 'Minus 5 min',
    ];

    protected $timePointSampleTypes = [
        'preDSMT' => ['urine', 'saliva3', 'hair', 'nail', 'stool', 'stool2'],
        'postDSMT' => ['urine', 'saliva3']
    ];

    public function __construct($module)
    {
        $this->module = $module;
    }
}
