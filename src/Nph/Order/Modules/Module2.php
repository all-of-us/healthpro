<?php

namespace App\Nph\Order\Modules;

use App\Nph\Order\Samples;

class Module2 extends Samples
{
    private static $module = 2;

    private static $visitTypes = [
        'OrangeDiet' => 'Orange Diet',
        'OrangeLMT' => 'Orange LMT',
        'BlueDiet' => 'Blue Diet',
        'BlueLMT' => 'Blue LMT',
        'PurpleDiet' => 'Purple Diet',
        'PurpleLMT' => 'Purple LMT'
    ];

    private static $visitTypeMapper = [
        'OrangeDiet' => 'Diet',
        'OrangeLMT' => 'LMT',
        'BlueDiet' => 'Diet',
        'BlueLMT' => 'LMT',
        'PurpleDiet' => 'Diet',
        'PurpleLMT' => 'LMT'
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
}
