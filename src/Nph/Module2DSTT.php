<?php

namespace App\Nph;

use App\Biobank\VisitDSTT;

class Module2DSTT extends VisitDSTT
{
    public $module = 2;

    public $color;

    public function __construct($color)
    {
        $this->color = $color;
    }
}
