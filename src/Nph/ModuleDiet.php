<?php

namespace App\Nph;

use App\Biobank\VisitDiet;

class ModuleDiet extends VisitDiet
{
    public $color;

    public $module;

    public function __construct($module, $color)
    {
        $this->color = $color;
        $this->module = 'module' . $module;
    }
}
