<?php
namespace Pmi\Drc;

class CodeBook
{
    protected static $map = [
        'UNSET' => '',
        'UNMAPPED' => '',
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
        'GenderIdentity_AdditionalOptions' => 'Other',
        'SpokenWrittenLanguage_Arabic' => 'Arabic',
        'SpokenWrittenLanguage_Bengali' => 'Bengali',
        'SpokenWrittenLanguage_Czech' => 'Czech',
        'SpokenWrittenLanguage_Danish' => 'Danish',
        'SpokenWrittenLanguage_German' => 'German',
        'SpokenWrittenLanguage_GermanAustria' => 'German (Austria)',
        'SpokenWrittenLanguage_GermanSwitzerland' => 'German (Switzerland)',
        'SpokenWrittenLanguage_GermanGermany' => 'German (Germany)',
        'SpokenWrittenLanguage_Greek' => 'Greek',
        'SpokenWrittenLanguage_English' => 'English',
        'SpokenWrittenLanguage_EnglishAustralia' => 'English (Australia)',
        'SpokenWrittenLanguage_EnglishCanada' => 'English (Canada)',
        'SpokenWrittenLanguage_EnglishGreatBritain' => 'English (Great Britain)',
        'SpokenWrittenLanguage_EnglishIndia' => 'English (India)',
        'SpokenWrittenLanguage_EnglishNewZeland' => 'English (New Zeland)',
        'SpokenWrittenLanguage_EnglishSingapore' => 'English (Singapore)',
        'SpokenWrittenLanguage_EnglishUnitedStates' => 'English (United States)',
        'SpokenWrittenLanguage_Spanish' => 'Spanish',
        'SpokenWrittenLanguage_SpanishArgentina' => 'Spanish (Argentina)',
        'SpokenWrittenLanguage_SpanishSpain' => 'Spanish (Spain)',
        'SpokenWrittenLanguage_SpanishUruguay' => 'Spanish (Uruguay)',
        'SpokenWrittenLanguage_Finnish' => 'Finnish',
        'SpokenWrittenLanguage_French' => 'French',
        'SpokenWrittenLanguage_FrenchBelgium' => 'French (Belgium)',
        'SpokenWrittenLanguage_FrenchSwitzerland' => 'French (Switzerland)',
        'SpokenWrittenLanguage_FrenchFrance' => 'French (France)',
        'SpokenWrittenLanguage_Frysian' => 'Frysian',
        'SpokenWrittenLanguage_FrysianNetherlands' => 'Frysian (Netherlands)',
        'SpokenWrittenLanguage_Hindi' => 'Hindi',
        'SpokenWrittenLanguage_Croatian' => 'Croatian',
        'SpokenWrittenLanguage_Italian' => 'Italian',
        'SpokenWrittenLanguage_ItalianSwitzerland' => 'Italian (Switzerland)',
        'SpokenWrittenLanguage_ItalianItaly' => 'Italian (Italy)',
        'SpokenWrittenLanguage_Japanese' => 'Japanese',
        'SpokenWrittenLanguage_Korean' => 'Korean',
        'SpokenWrittenLanguage_Dutch' => 'Dutch',
        'SpokenWrittenLanguage_DutchBelgium' => 'Dutch (Belgium)',
        'SpokenWrittenLanguage_DutchNetherlands' => 'Dutch (Netherlands)',
        'SpokenWrittenLanguage_Norwegian' => 'Norwegian',
        'SpokenWrittenLanguage_NorwegianNorway' => 'Norwegian (Norway)',
        'SpokenWrittenLanguage_Punjabi' => 'Punjabi',
        'SpokenWrittenLanguage_Portuguese' => 'Portuguese',
        'SpokenWrittenLanguage_PortugueseBrazil' => 'Portuguese (Brazil)',
        'SpokenWrittenLanguage_Russian' => 'Russian',
        'SpokenWrittenLanguage_RussianRussia' => 'Russian (Russia)',
        'SpokenWrittenLanguage_Serbian' => 'Serbian',
        'SpokenWrittenLanguage_SerbianSerbia' => 'Serbian (Serbia)',
        'SpokenWrittenLanguage_Swedish' => 'Swedish',
        'SpokenWrittenLanguage_SwedishSweden' => 'Swedish (Sweden)',
        'SpokenWrittenLanguage_Telegu' => 'Telegu',
        'SpokenWrittenLanguage_Chinese' => 'Chinese',
        'SpokenWrittenLanguage_ChineseChina' => 'Chinese (China)',
        'SpokenWrittenLanguage_ChineseHongKong' => 'Chinese (Hong Kong)',
        'SpokenWrittenLanguage_ChineseSingapore' => 'Chinese (Singapore)',
        'SpokenWrittenLanguage_ChineseTaiwan' => 'Chinese (Taiwan)',
    ];

    public static function display($code)
    {
        if (array_key_exists($code, self::$map)) {
            return self::$map[$code];
        } elseif (strpos($code, 'PIIState_') === 0) {
            return substr($code, 9);
        } else {
            return $code;
        }
    }
}
