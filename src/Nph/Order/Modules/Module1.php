<?php

namespace App\Nph\Order\Modules;

use App\Nph\Order\Samples;

class Module1 extends Samples
{
    public $module = 1;

    public static $visitTypes = [
        'MMTT' => 'MMTT'
    ];

    public function __construct($visit)
    {
        if (!in_array($visit, self::$visitTypes)) {
            throw new \Exception('Visit Type not supported');
        }
        $this->visit = $visit;
    }
}
