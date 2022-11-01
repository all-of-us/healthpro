<?php

namespace App\Nph\Order\Visits;

use App\Nph\Order\TimePoints;

class VisitMMTT extends TimePoints
{
    public $timePoints = [
        'preMMTT' => 'Pre MMTT',
        'minus15min' => '-15 Min',
        'minus5min' => '-5 Min',
        '15min' => '15 Min',
        '30min' => '30 Min',
        '60min' => '60 Min',
        '90min' => '90 Min',
        '120min' => '120 Min',
        '240min' => '240 Min',
        'postMMTT' => 'Post MMTT'
    ];

    public $timePointSampleTypes = [
        'preMMTT' => ['urine', 'saliva', 'stool', 'hair', 'nail'],
        'postMMTT' => ['urine', 'saliva']
    ];

    public function __construct($module)
    {
        $this->module = $module;
    }
}
