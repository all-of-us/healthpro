<?php

namespace App\Nph\Order\Modules;

use App\Nph\Order\Samples;

class Module3 extends Samples
{
    private $module = 3;

    private static array $visitTypes = [
        'Period1Diet' => 'Diet_Period_1_Diet',
        'Period1DLW' => 'Diet_Period_1_DLW',
        'Period1DSMT' => 'Diet_Period_1_DSMT',
        'Period1LMT' => 'Diet_Period_1_LMT',
        'Period2Diet' => 'Diet_Period_2_Diet',
        'Period2DLW' => 'Diet_Period_2_DLW',
        'Period2DSMT' => 'Diet_Period_2_DSMT',
        'Period2LMT' => 'Diet_Period_2_LMT',
        'Period3Diet' => 'Diet_Period_3_Diet',
        'Period3DLW' => 'Diet_Period_3_DLW',
        'Period3DSMT' => 'Diet_Period_3_DSMT',
        'Period3LMT' => 'Diet_Period_3_LMT',
    ];

    private static array $visitTypeWithStool = [
        'Period1Diet' => 'Diet_Period_1_Diet',
        'Period1DLW' => 'Diet_Period_1_DLW',
        'Period1DSMTStool' => 'Diet_Period_1_DSMT_Stool',
        'Period1DSMT' => 'Diet_Period_1_DSMT',
        'Period1LMT' => 'Diet_Period_1_LMT',
        'Period2Diet' => 'Diet_Period_2_Diet',
        'Period2DLW' => 'Diet_Period_2_DLW',
        'Period2DSMTStool' => 'Diet_Period_2_DSMT_Stool',
        'Period2DSMT' => 'Diet_Period_2_DSMT',
        'Period2LMT' => 'Diet_Period_2_LMT',
        'Period3Diet' => 'Diet_Period_3_Diet',
        'Period3DLW' => 'Diet_Period_3_DLW',
        'Period3DSMTStool' => 'Diet_Period_3_DSMT_Stool',
        'Period3DSMT' => 'Diet_Period_3_DSMT',
        'Period3LMT' => 'Diet_Period_3_LMT',
    ];

    private static array $visitTypeMapper = [
        'Period1Diet' => '3Diet',
        'Period1DLW' => 'DLW',
        'Period1DSMT' => '3DSMT',
        'Period1DSMTStool' => '3DSMT',
        'Period1LMT' => '3LMT',
        'Period2Diet' => '3Diet',
        'Period2DLW' => 'DLW',
        'Period2DSMT' => '3DSMT',
        'Period2DSMTStool' => '3DSMT',
        'Period2LMT' => '3LMT',
        'Period3Diet' => '3Diet',
        'Period3DLW' => 'DLW',
        'Period3DSMT' => '3DSMT',
        'Period3DSMTStool' => '3DSMT',
        'Period3LMT' => '3LMT',
    ];

    private static array $visitDietMapper = [
        'Period1Diet' => 'PERIOD1',
        'Period1DLW' => 'PERIOD1',
        'Period1DSMT' => 'PERIOD1',
        'Period1DSMTStool' => 'PERIOD1',
        'Period1LMT' => 'PERIOD1',
        'Period2Diet' => 'PERIOD2',
        'Period2DLW' => 'PERIOD2',
        'Period2DSMT' => 'PERIOD2',
        'Period2DSMTStool' => 'PERIOD2',
        'Period2LMT' => 'PERIOD2',
        'Period3Diet' => 'PERIOD3',
        'Period3DLW' => 'PERIOD3',
        'Period3DSMT' => 'PERIOD3',
        'Period3DSMTStool' => 'PERIOD3',
        'Period3LMT' => 'PERIOD3',
    ];


    public function __construct($visit)
    {
        if (!in_array($visit, array_keys(self::$visitTypeWithStool))) {
            throw new \Exception('Visit Type not supported');
        }
        if (isset(self::$visitTypeMapper[$visit])) {
            $visit = self::$visitTypeMapper[$visit];
        }
        parent::__construct($this->module, $visit);
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
