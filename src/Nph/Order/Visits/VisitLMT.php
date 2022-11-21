<?php

namespace App\Nph\Order\Visits;

use App\Nph\Order\TimePoints;

class VisitLMT extends TimePoints
{
    protected $timePoints = [
        'preLMT' => 'Pre LMT',
        'minus15min' => '-15 Min',
        'minus5min' => '-5 Min',
        '15min' => '15 Min',
        '30min' => '30 Min',
        '60min' => '60 Min',
        '90min' => '90 Min',
        '120min' => '120 Min',
        '240min' => '240 Min',
        'postLMT' => 'Post LMT'
    ];

    protected $timePointSampleTypes = [
        'preLMT' => ['urine', 'saliva', 'hair', 'nail', 'stool'],
        'postLMT' => ['urine', 'saliva']
    ];

    public function __construct($module)
    {
        $this->module = $module;
    }
}
