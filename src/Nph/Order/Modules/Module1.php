<?php

namespace App\Nph\Order\Modules;

use App\Nph\Order\Samples;

class Module1 extends Samples
{
    public const SAMPLE_CONSENT_TYPE_HAIR = ['HAIR'];
    public const SAMPLE_CONSENT_TYPE_NAIL = ['NAILL', 'NAILB'];
    private static $module = 1;

    private static $visitTypes = [
        'LMT' => 'LMT'
    ];

    private static array $visitTypeWithStool = [
        'LMTStool' => 'LMTStool',
        'LMT' => 'LMT'
    ];

    private static array $visitDietMapper = [
        'LMT' => 'LMT',
        'LMTStool' => 'LMT'
    ];

    public function __construct($visit)
    {
        parent::__construct(self::$module, $visit);
        if (!in_array($visit, array_keys(self::$visitTypeWithStool))) {
            throw new \Exception('Visit Type not supported');
        }
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
