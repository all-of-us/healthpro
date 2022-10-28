<?php

namespace App\Nph;

use App\Biobank\VisitMMTT;

class Module3MMTT extends VisitMMTT
{
    public $module = 3;

    public $color;

    public function __construct($color)
    {
        $this->color = $color;
    }
}
