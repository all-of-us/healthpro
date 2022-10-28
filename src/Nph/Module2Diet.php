<?php

namespace App\Nph;

use App\Biobank\VisitDiet;

class Module2Diet extends VisitDiet
{
    public $module = 2;

    public $color;

    public function __construct($color)
    {
        $this->color = $color;
    }
}
