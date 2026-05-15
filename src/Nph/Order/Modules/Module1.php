<?php

namespace App\Nph\Order\Modules;

use App\Nph\Order\Samples;

class Module1 extends Samples
{
    public const SAMPLE_CONSENT_TYPE_HAIR = ['HAIR'];
    public const SAMPLE_CONSENT_TYPE_NAIL = ['NAILL', 'NAILB'];
    private static int $module = 1;

    /** @var array<string, string> */
    private static array $visitTypes = [
        'LMT' => 'LMT'
    ];

    /** @var array<string, string> */
    private static array $visitDietMapper = [
        'LMT' => 'LMT'
    ];

    public function __construct(string $visit)
    {
        parent::__construct(self::$module, $visit);
        if (!array_key_exists($visit, self::$visitTypes)) {
            throw new \Exception('Visit Type not supported');
        }
    }

    /**
     * @return array<string, string>
     */
    public static function getVisitTypes(): array
    {
        return self::$visitTypes;
    }

    public static function getVisitDiet(string $visitType): string
    {
        return self::$visitDietMapper[$visitType];
    }
}
