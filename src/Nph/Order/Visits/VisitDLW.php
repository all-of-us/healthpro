<?php

namespace App\Nph\Order\Visits;

use App\Nph\Order\TimePoints;

class VisitDLW extends TimePoints
{
    public $timePoints = [
        'day0PreDoseA' => 'Day 0 Pre Dose A',
        'day0PreDoseB' => 'Day 0 Pre Dose B',
        'day0PostDoseA' => 'Day 0 Post Dose A',
        'day0PostDoseB' => 'Day 0 Post Dose B',
        'day6E' => 'Day 6 E',
        'day7F' => 'Day 7 F',
        'day13G' => 'Day 13 G',
        'day14F' => 'Day 14 F'
    ];

    public $timePointSampleTypes = [
        'day0PreDoseA' => ['urineDlw'],
        'day0PreDoseB' => ['urineDlw'],
        'day0PostDoseA' => ['urineDlw'],
        'day0PostDoseB' => ['urineDlw'],
        'day6E' => ['urineDlw'],
        'day7F' => ['urineDlw'],
        'day13G' => ['urineDlw'],
        'day14F' => ['urineDlw'],
    ];

    public function __construct($module)
    {
        $this->module = $module;
    }
}
