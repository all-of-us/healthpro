<?php

namespace App\Service;

class TimezoneService
{
    public static $timezoneOptions = [
        'America/New_York' => 'Eastern Time',
        'America/Chicago' => 'Central Time',
        'America/Denver' => 'Mountain Time',
        'America/Phoenix' => 'Mountain Time - Arizona',
        'America/Los_Angeles' => 'Pacific Time',
        'America/Anchorage' => 'Alaska Time',
        'Pacific/Honolulu' => 'Hawaii Time',
        'America/Puerto_Rico' => 'Atlantic Standard Time'
    ];

    /**
     * Get Timezone Display Value
     *
     * Returns human-friendly timezone from given string. If no match found,
     * return the given value.
     */
    public function getTimezoneDisplay(string $timezone): ?string
    {
        if (array_key_exists($timezone, static::$timezoneOptions)) {
            return static::$timezoneOptions[$timezone];
        } else {
            return $timezone;
        }
    }
}
