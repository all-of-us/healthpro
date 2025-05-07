<?php

namespace App\Nph\Order\Modules;

use App\Nph\Order\Samples;

class Module2 extends Samples
{
    private static $module = 2;

    private static array $visitTypes = [
        'Period1Diet' => 'Diet_Period_1_Diet',
        'Period1DSMT' => 'Diet_Period_1_DSMT',
        'Period2Diet' => 'Diet_Period_2_Diet',
        'Period2DSMT' => 'Diet_Period_2_DSMT',
        'Period3Diet' => 'Diet_Period_3_Diet',
        'Period3DSMT' => 'Diet_Period_3_DSMT'
    ];

    private static array $visitTypeMapper = [
        'Period1Diet' => 'Diet',
        'Period1DSMT' => 'DSMT',
        'Period1DSMTStool' => 'DSMTStool',
        'Period2Diet' => 'Diet',
        'Period2DSMT' => 'DSMT',
        'Period2DSMTStool' => 'DSMTStool',
        'Period3Diet' => 'Diet',
        'Period3DSMT' => 'DSMT',
        'Period3DSMTStool' => 'DSMTStool',
    ];

    private static array $visitDietMapper = [
        'Period1Diet' => 'PERIOD1',
        'Period1DSMT' => 'PERIOD1',
        'Period1DSMTStool' => 'PERIOD1',
        'Period2Diet' => 'PERIOD2',
        'Period2DSMT' => 'PERIOD2',
        'Period2DSMTStool' => 'PERIOD2',
        'Period3Diet' => 'PERIOD3',
        'Period3DSMT' => 'PERIOD3',
        'Period3DSMTStool' => 'PERIOD3',
    ];

    private static array $visitTypeWithStool = [
        'Period1Diet' => 'Diet_Period_1_Diet',
        'Period1DSMTStool' => 'Diet_Period_1_DSMT_Stool',
        'Period1DSMT' => 'Diet_Period_1_DSMT',
        'Period2Diet' => 'Diet_Period_2_Diet',
        'Period2DSMTStool' => 'Diet_Period_2_DSMT_Stool',
        'Period2DSMT' => 'Diet_Period_2_DSMT',
        'Period3Diet' => 'Diet_Period_3_Diet',
        'Period3DSMTStool' => 'Diet_Period_3_DSMT_Stool',
        'Period3DSMT' => 'Diet_Period_3_DSMT'
    ];

    public function __construct($visit)
    {
        if (!in_array($visit, array_keys(self::$visitTypeWithStool))) {
            throw new \Exception('Visit Type not supported');
        }
        if (isset(self::$visitTypeMapper[$visit])) {
            $visit = self::$visitTypeMapper[$visit];
        }
        parent::__construct(self::$module, $visit);
    }

    public static function getVisitTypes(): array
    {
        return self::$visitTypeWithStool;
    }

    public static function getVisitDiet(string $visitType): string
    {
        return self::$visitDietMapper[$visitType];
    }
}
