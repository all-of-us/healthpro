<?php

namespace App\Nph\Order\Visits;

use App\Nph\Order\TimePoints;

class VisitLMT extends TimePoints
{
    protected $timePoints = [
        'preLMT' => 'Pre LMT',
        'minus15min' => '-15 min',
        'minus5min' => '-5 min',
        '15min' => '15 min',
        '30min' => '30 min',
        '60min' => '60 min',
        '90min' => '90 min',
        '120min' => '120 min',
        '180min' => '180 min',
        '240min' => '240 min',
        'postLMT' => 'Post LMT'
    ];

    protected $timePointSampleTypes = [
        'preLMT' => ['urine', 'saliva', 'hair', 'nail', 'stool'],
        'postLMT' => ['urine']
    ];

    public function __construct($module)
    {
        $this->module = $module;
    }
}
