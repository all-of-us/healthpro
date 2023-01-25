<?php

namespace App\Nph\Order\Modules;

use App\Nph\Order\Samples;

class Module3 extends Samples
{
    private $module = 3;

    private static $visitTypes = [
        'OrangeDiet' => 'Orange Diet',
        'OrangeDLW' => 'Orange DLW',
        'OrangeDSMT' => 'Orange DSMT',
        'OrangeLMT' => 'Orange LMT',
        'BlueDiet' => 'Blue Diet',
        'BlueDLW' => 'Blue DLW',
        'BlueDSMT' => 'Blue DSMT',
        'BlueLMT' => 'Blue LMT',
        'PurpleDiet' => 'Purple Diet',
        'PurpleDLW' => 'Purple DLW',
        'PurpleDSMT' => 'Purple DSMT',
        'PurpleLMT' => 'Purple LMT',
    ];

    private static $visitTypeMapper = [
        'OrangeDiet' => 'Diet',
        'OrangeDLW' => 'DLW',
        'OrangeDSMT' => 'DSMT',
        'OrangeLMT' => 'LMT',
        'BlueDiet' => 'Diet',
        'BlueDLW' => 'DLW',
        'BlueDSMT' => 'DSMT',
        'BlueLMT' => 'LMT',
        'PurpleDiet' => 'Diet',
        'PurpleDLW' => 'DLW',
        'PurpleDSMT' => 'DSMT',
        'PurpleLMT' => 'LMT',
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
}
