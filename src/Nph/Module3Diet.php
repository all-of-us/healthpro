<?php

namespace App\Nph;

use App\Biobank\VisitDiet;

class Module3Diet extends VisitDiet
{
    public $module = 3;

    public $color;

    public function __construct($color)
    {
        $this->color = $color;
    }
}
