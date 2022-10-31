<?php

namespace App\Nph\Order\Modules;

use App\Nph\Order\Samples;

class Module2 extends Samples
{
    public $module = 2;

    public $visit;

    public static $visitTypes = [
        'OrangeDiet' => 'Orange Diet',
        'OrangeDSMT' => 'Orange DSMT',
        'BlueDiet' => 'Blue Diet',
        'BlueDSMT' => 'Blue DSMT',
        'PurpleDiet' => 'Purple Diet',
        'PurpleDSMT' => 'Purple DSMT'
    ];

    public $visitTypeMapper = [
        'OrangeDiet' => 'Diet',
        'OrangeDSMT' => 'DSMT',
        'BlueDiet' => 'Diet',
        'BlueDSMT' => 'DSMT',
        'PurpleDiet' => 'Diet',
        'PurpleDSMT' => 'DSMT'
    ];

    public function __construct($visit)
    {
        $this->visit = $visit;
        if (isset($this->visitTypeMapper[$visit])) {
            $this->visit = $this->visitTypeMapper[$visit];
        }
    }
}
