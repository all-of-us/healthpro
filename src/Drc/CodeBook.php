<?php

namespace App\Drc;

class CodeBook
{
    protected static $map = [
        'UNSET' => '',
        'UNMAPPED' => '',
        'PREFER_NOT_TO_SAY' => 'Prefer Not To Answer',
        'PMI_Skip' => 'Skip',
        'PMI_PreferNotToAnswer' => 'Prefer Not To Answer',
        'PMI_Other' => 'Other',
        'PMI_Unanswered' => 'Unanswered',
        'PMI_DontKnow' => 'Don\'t Know',
        'PMI_NotSure' => 'Not Sure',
        'RecontactMethod_HousePhone' => 'House Phone',
        'RecontactMethod_CellPhone' => 'Cell Phone',
        'RecontactMethod_Email' => 'Email',
        'RecontactMethod_Address' => 'Physical Address',
        'NO_CONTACT' => 'No Contact',
        'SexAtBirth_Male' => 'Male',
        'SexAtBirth_Female' => 'Female',
        'SexAtBirth_Intersex' => 'Intersex',
        'SexAtBirth_None' => 'Other',
        'SexAtBirth_SexAtBirthNoneOfThese' => 'Other',
        'GenderIdentity_Man' => 'Man',
        'GenderIdentity_Woman' => 'Woman',
        'GenderIdentity_NonBinary' => 'Non-binary',
        'GenderIdentity_Transgender' => 'Transgender',
        'GenderIdentity_AdditionalOptions' => 'Other',
        'GenderIdentity_MoreThanOne' => 'More Than One Gender Identity',
        'SexualOrientation_Straight' => 'Straight',
        'SexualOrientation_Gay' => 'Gay',
        'SexualOrientation_Lesbian' => 'Lesbian',
        'SexualOrientation_Bisexual' => 'Bisexual',
        'SexualOrientation_None' => 'Other',
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
        'AnnualIncome_less10k' => 'Less than $10,000',
        'AnnualIncome_10k25k' => '$10,000- $24,999',
        'AnnualIncome_25k35k' => '$25,000- $34,999',
        'AnnualIncome_35k50k' => '$35,000- $49,999',
        'AnnualIncome_50k75k' => '$50,000- $74,999',
        'AnnualIncome_75k100k' => '$75,000- $99,999',
        'AnnualIncome_100k150k' => '$100,000- $149,999',
        'AnnualIncome_150k200k' => '$150,000- $199,999',
        'AnnualIncome_more200k' => '$200,000 or more',
        'HighestGrade_NeverAttended' => 'Never attended school or only attended kindergarten',
        'HighestGrade_OneThroughFour' => 'Grades 1 through 4 (Primary)',
        'HighestGrade_FiveThroughEight' => 'Grades 5 through 8 (Secondary)',
        'HighestGrade_NineThroughEleven' => 'Grades 9 through 11 (Some high school)',
        'HighestGrade_TwelveOrGED' => 'Grade 12 or GED (High school graduate)',
        'HighestGrade_CollegeOnetoThree' => 'College 1 to 3 years (Some college or technical school)',
        'HighestGrade_CollegeGraduate' => 'College 4 years or more (College graduate)',
        'HighestGrade_AdvancedDegree' => 'Advanced degree (Master\'s, Doctorate, etc.)',
        'AMERICAN_INDIAN_OR_ALASKA_NATIVE' => 'American Indian / Alaska Native',
        'BLACK_OR_AFRICAN_AMERICAN' => 'Black or African American',
        'ASIAN' => 'Asian',
        'NATIVE_HAWAIIAN_OR_OTHER_PACIFIC_ISLANDER' => 'Native Hawaiian or Other Pacific Islander',
        'WHITE' => 'White',
        'HISPANIC_LATINO_OR_SPANISH' => 'Hispanic, Latino, or Spanish',
        'MIDDLE_EASTERN_OR_NORTH_AFRICAN' => 'Middle Eastern or North African',
        'HLS_AND_WHITE' => 'H/L/S and White',
        'HLS_AND_BLACK' => 'H/L/S and Black',
        'HLS_AND_ONE_OTHER_RACE' => 'H/L/S and one other race',
        'HLS_AND_MORE_THAN_ONE_OTHER_RACE' => 'H/L/S and more than one other race',
        'MORE_THAN_ONE_RACE' => 'More than one race',
        'OTHER_RACE' => 'Other',
        'INTERESTED' => 'Participant',
        'PARTICIPANT' => 'Participant',
        'PARTICIPANT_PLUS_EHR' => 'Participant + EHR Consent',
        'ENROLLED_PARTICIPANT' => 'Enrolled Participant',
        'PMB_ELIGIBLE' => 'PM&B Eligible',
        'CORE_PARTICIPANT' => 'Core Participant',
        'MEMBER' => 'Participant + EHR Consent',
        'FULL_PARTICIPANT' => 'Core Participant',
        'CORE_MINUS_PM' => 'Core Participant Minus PM',
        'en' => 'English',
        'es' => 'Spanish',
        'vibrent' => 'PTSC Portal',
        'careevolution' => 'DV Pilot Portal',
        'MAIL_KIT' => 'Mail Kit',
        'ON_SITE' => 'On Site'
    ];

    public static function display($code)
    {
        if (is_bool($code) || is_object($code) || is_array($code)) {
            return $code;
        }
        if (array_key_exists($code, self::$map)) {
            return self::$map[$code];
        } elseif (strpos($code, 'PIIState_') === 0) {
            return substr($code, 9);
        }
        return $code;
    }

    public static function ageRangeToDob($range): array
    {
        $parameters = [];
        if (!preg_match('/^(\d+)-(\d+)?$/', $range, $matches)) {
            return $parameters;
        }

        $start = new \DateTime("-{$matches[1]} years");
        $parameters[] = 'le' . $start->format('Y-m-d');

        if (isset($matches[2])) {
            $endRange = (int) $matches[2] + 1;
            $end = new \DateTime("-{$endRange} years");
            $parameters[] = 'gt' . $end->format('Y-m-d');
        }

        return $parameters;
    }
}
