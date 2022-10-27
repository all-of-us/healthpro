<?php

namespace App\Nph;

use App\Biobank\VisitDSTT;

class ModuleDSTT extends VisitDSTT
{
    public $color;

    public $module;

    public function __construct($module, $color)
    {
        $this->color = $color;
        $this->module = 'module' . $module;
    }
}
