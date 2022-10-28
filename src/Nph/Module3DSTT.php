<?php

namespace App\Nph;

use App\Biobank\VisitDSTT;

class Module3DSTT extends VisitDSTT
{
    public $module = 3;

    public $color;

    public function __construct($color)
    {
        $this->color = $color;
    }
}
