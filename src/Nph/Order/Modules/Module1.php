<?php

namespace App\Nph\Order\Modules;

use App\Nph\Order\Samples;

class Module1 extends Samples
{
    public $module = 1;

    public $visit;

    public static $visitTypes = [
        'MMTT' => 'MMTT'
    ];

    public function __construct($visit)
    {
        $this->visit = $visit;
    }
}
