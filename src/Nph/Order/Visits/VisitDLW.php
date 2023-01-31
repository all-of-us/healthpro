<?php

namespace App\Nph\Order\Visits;

use App\Nph\Order\TimePoints;

class VisitDLW extends TimePoints
{
    protected $timePoints = [
        'day0PreDoseA' => 'Day 0 Pre Dose A',
        'day1PreDoseB' => 'Day 1 Pre Dose B',
        'day1PostDoseC' => 'Day 1 Post Dose C (4 hrs)',
        'day1PostDoseD' => 'Day 1 Post Dose D (4 hrs)',
        'day6E' => 'Day 6 E',
        'day7F' => 'Day 7 F',
        'day13G' => 'Day 13 G',
        'day14F' => 'Day 14 H'
    ];

    protected $timePointSampleTypes = [
        'day0PreDoseA' => ['urineDlw'],
        'day1PreDoseB' => ['urineDlw'],
        'day1PostDoseC' => ['urineDlw'],
        'day1PostDoseD' => ['urineDlw'],
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
