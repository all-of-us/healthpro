<?php
namespace Pmi\Drc;

class CodeBook
{
    protected static $map = [
        'UNSET' => '',
        'PMI_Skip' => 'Skip',
        'PMI_PreferNotToAnswer' => 'Prefer Not To Answer',
        'PMI_Other' => 'Other',
        'PMI_Unanswered' => 'Unanswered',
        'PMI_DontKnow' => 'Don\'t Know',
        'PMI_NotSure' => 'Not Sure',
        'GenderIdentity_Man' => 'Man',
        'GenderIdentity_Woman' => 'Woman',
        'GenderIdentity_NonBinary' => 'Non-binary',
        'GenderIdentity_Transgender' => 'Transgender',
        'GenderIdentity_AdditionalOptions' => 'Other'
    ];

    public static function display($code)
    {
        if (array_key_exists($code, self::$map)) {
            return self::$map[$code];
        } else {
            return $code;
        }
    }
}
