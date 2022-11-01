<?php

namespace App\Nph\Order\Modules;

use App\Nph\Order\Samples;

class Module3 extends Samples
{
    public $module = 3;

    public static $visitTypes = [
        'OrangeDiet' => 'Orange Diet',
        'OrangeDLW' => 'Orange DLW',
        'OrangeDSMT' => 'Orange DSMT',
        'OrangeMMTT' => 'Orange MMTT',
        'BlueDiet' => 'Blue Diet',
        'BlueDLW' => 'Blue DLW',
        'BlueDSMT' => 'Blue DSMT',
        'BlueMMTT' => 'Blue MMTT',
        'PurpleDiet' => 'Purple Diet',
        'PurpleDLW' => 'Purple DLW',
        'PurpleDSMT' => 'Purple DSMT',
        'PurpleMMTT' => 'Purple MMTT',
    ];

    public $visitTypeMapper = [
        'OrangeDiet' => 'Diet',
        'OrangeDLW' => 'DLW',
        'OrangeDSMT' => 'DSMT',
        'OrangeMMTT' => 'MMTT',
        'BlueDiet' => 'Diet',
        'BlueDLW' => 'DLW',
        'BlueDSMT' => 'DSMT',
        'BlueMMTT' => 'MMTT',
        'PurpleDiet' => 'Diet',
        'PurpleDLW' => 'DLW',
        'PurpleDSMT' => 'DSMT',
        'PurpleMMTT' => 'MMTT',
    ];

    public function __construct($visit)
    {
        $this->visit = $visit;
        if (isset($this->visitTypeMapper[$visit])) {
            $this->visit = $this->visitTypeMapper[$visit];
        }
    }
}
