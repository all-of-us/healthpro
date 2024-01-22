<?php

namespace App\Nph\Order\Modules;

use App\Nph\Order\Samples;

class Module3 extends Samples
{
    private $module = 3;

    private static array $visitTypes = [
        'Period1Diet' => 'Diet Period 1 Diet',
        'Period1DLW' => 'Diet Period 1 DLW',
        'Period1DSMT' => 'Diet Period 1 DSMT',
        'Period1LMT' => 'Diet Period 1 LMT',
        'Period2Diet' => 'Diet Period 2 Diet',
        'Period2DLW' => 'Diet Period 2 DLW',
        'Period2DSMT' => 'Diet Period 2 DSMT',
        'Period2LMT' => 'Diet Period 2 LMT',
        'Period3Diet' => 'Diet Period 3 Diet',
        'Period3DLW' => 'Diet Period 3 DLW',
        'Period3DSMT' => 'Diet Period 3 DSMT',
        'Period3LMT' => 'Diet Period 3 LMT',
    ];

    private static array $visitTypeMapper = [
        'Period1Diet' => '3Diet',
        'Period1DLW' => 'DLW',
        'Period1DSMT' => '3DSMT',
        'Period1LMT' => '3LMT',
        'Period2Diet' => '3Diet',
        'Period2DLW' => 'DLW',
        'Period2DSMT' => '3DSMT',
        'Period2LMT' => '3LMT',
        'Period3Diet' => '3Diet',
        'Period3DLW' => 'DLW',
        'Period3DSMT' => '3DSMT',
        'Period3LMT' => '3LMT',
    ];

    private static array $visitDietMapper = [
        'Period1Diet' => 'PERIOD1',
        'Period1DLW' => 'PERIOD1',
        'Period1DSMT' => 'PERIOD1',
        'Period1LMT' => 'PERIOD1',
        'Period2Diet' => 'PERIOD2',
        'Period2DLW' => 'PERIOD2',
        'Period2DSMT' => 'PERIOD2',
        'Period2LMT' => 'PERIOD2',
        'Period3Diet' => 'PERIOD3',
        'Period3DLW' => 'PERIOD3',
        'Period3DSMT' => 'PERIOD3',
        'Period3LMT' => 'PERIOD3',
    ];


    public function __construct($visit)
    {
        if (!in_array($visit, array_keys(self::$visitTypes))) {
            throw new \Exception('Visit Type not supported');
        }
        if (isset(self::$visitTypeMapper[$visit])) {
            $visit = self::$visitTypeMapper[$visit];
        }
        parent::__construct($this->module, $visit);
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
