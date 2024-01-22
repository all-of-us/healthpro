<?php

namespace App\Nph\Order\Modules;

use App\Nph\Order\Samples;

class Module2 extends Samples
{
    private static $module = 2;

    private static array $visitTypes = [
        'Period1Diet' => 'Diet Period 1 Diet',
        'Period1DSMT' => 'Diet Period 1 DSMT',
        'Period2Diet' => 'Diet Period 2 Diet',
        'Period2DSMT' => 'Diet Period 2 DSMT',
        'Period3Diet' => 'Diet Period 3 Diet',
        'Period3DSMT' => 'Diet Period 3 DSMT'
    ];

    private static array $visitTypeMapper = [
        'Period1Diet' => 'Diet',
        'Period1DSMT' => 'DSMT',
        'Period2Diet' => 'Diet',
        'Period2DSMT' => 'DSMT',
        'Period3Diet' => 'Diet',
        'Period3DSMT' => 'DSMT'
    ];

    private static array $visitDietMapper = [
        'Period1Diet' => 'PERIOD1',
        'Period1DSMT' => 'PERIOD1',
        'Period2Diet' => 'PERIOD2',
        'Period2DSMT' => 'PERIOD2',
        'Period3Diet' => 'PERIOD3',
        'Period3DSMT' => 'PERIOD3'
    ];

    public function __construct($visit)
    {
        if (!in_array($visit, array_keys(self::$visitTypes))) {
            throw new \Exception('Visit Type not supported');
        }
        if (isset(self::$visitTypeMapper[$visit])) {
            $visit = self::$visitTypeMapper[$visit];
        }
        parent::__construct(self::$module, $visit);
    }

    public static function getVisitTypes(): array
    {
        return self::$visitTypes;
    }

    public static function getVisitDiet(string $visitType): string
    {
        return self::$visitDietMapper[$visitType];
    }
}
