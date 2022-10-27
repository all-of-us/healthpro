<?php

namespace App\Nph;

use App\Biobank\VisitDLW;

class ModuleDLW extends VisitDLW
{
    public $color;

    public $module;

    public function __construct($module, $color)
    {
        $this->color = $color;
        $this->module = 'module' . $module;
    }
}
