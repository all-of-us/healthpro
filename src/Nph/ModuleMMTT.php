<?php

namespace App\Nph;

use App\Biobank\VisitMMTT;

class ModuleMMTT extends VisitMMTT
{
    public $color;

    public $module;

    public function __construct($module, $color)
    {
        $this->color = $color;
        $this->module = 'module' . $module;
    }
}
