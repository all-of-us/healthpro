<?php

namespace App\Nph\Order\Modules;

use App\Nph\Order\Samples;

class Module2 extends Samples
{
    private static $module = 2;

    private static $visitTypes = [
        'OrangeDiet' => 'Orange Diet',
        'OrangeDSMT' => 'Orange DSMT',
        'BlueDiet' => 'Blue Diet',
        'BlueDSMT' => 'Blue DSMT',
        'PurpleDiet' => 'Purple Diet',
        'PurpleDSMT' => 'Purple DSMT'
    ];

    private static $visitTypeMapper = [
        'OrangeDiet' => 'Diet',
        'OrangeDSMT' => 'DSMT',
        'BlueDiet' => 'Diet',
        'BlueDSMT' => 'DSMT',
        'PurpleDiet' => 'Diet',
        'PurpleDSMT' => 'DSMT'
    ];

    private static array $visitDietMapper = [
        'OrangeDiet' => 'ORANGE',
        'OrangeDSMT' => 'ORANGE',
        'BlueDiet' => 'BLUE',
        'BlueDSMT' => 'BLUE',
        'PurpleDiet' => 'PURPLE',
        'PurpleDSMT' => 'PURPLE'
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
