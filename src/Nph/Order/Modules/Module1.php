<?php

namespace App\Nph\Order\Modules;

use App\Nph\Order\Samples;

class Module1 extends Samples
{
    private static $module = 1;

    private static $visitTypes = [
        'LMT' => 'LMT'
    ];

    public function __construct($visit)
    {
        parent::__construct(self::$module, $visit);
        if (!in_array($visit, array_keys(self::$visitTypes))) {
            throw new \Exception('Visit Type not supported');
        }
    }
}
