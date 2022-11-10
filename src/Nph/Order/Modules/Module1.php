<?php

namespace App\Nph\Order\Modules;

use App\Nph\Order\Samples;

class Module1 extends Samples
{
    public $module = 1;

    public static $visitTypes = [
        'LMT' => 'LMT'
    ];

    public function __construct($visit)
    {
        parent::__construct($this->module, $visit);
        if (!in_array($visit, array_keys(self::$visitTypes))) {
            throw new \Exception('Visit Type not supported');
        }
    }
}
