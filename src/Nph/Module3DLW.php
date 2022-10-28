<?php

namespace App\Nph;

use App\Biobank\VisitDLW;

class Module3DLW extends VisitDLW
{
    public $module = 3;

    public $color;

    public function __construct($color)
    {
        $this->color = $color;
    }
}
