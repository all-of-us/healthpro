<?php

namespace App\Nph;

use App\Biobank\VisitMMTT;

class ModuleMMTT extends VisitMMTT
{
    public $color;

    public $module;

    public function __construct($module, $color)
    {
        if (!in_array($module, $this->allowedModules)) {
            throw new \Exception('Module not supported');
        }
        $this->color = $color;
        $this->module = 'module' . $module;
    }
}
