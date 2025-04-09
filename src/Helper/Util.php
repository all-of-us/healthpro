<?php

namespace App\Helper;

use Ramsey\Uuid\Uuid;

class Util
{
    public static function generateUuid()
    {
        return Uuid::uuid4()->toString();
    }

    public static function generateShortUuid($length = 16, $upper = true)
    {
        $id = self::generateUuid();
        $id = self::shortenUuid($id, $length);
        if ($upper) {
            $id = strtoupper($id);
        }
        return $id;
    }

    public static function shortenUuid($uuid, $length = 16)
    {
        $id = preg_replace('/[^a-f0-9]/i', '', $uuid);
        $id = substr($id, 0, $length);
        return $id;
    }

    public static function versionIsAtLeast($version, $minVersion)
    {
        $min = explode('.', $minVersion);
        $current = explode('.', $version);
        foreach ($current as $k => $val) {
            if (isset($min[$k])) {
                $compare = $min[$k];
            } else {
                $compare = 0;
            }
            if ($val < $compare) {
                return false;
            }
            if ($val > $compare) {
                return true;
            }
        }
        return true;
    }

    public static function parseMultipleTimestamps(array $result, $timezone)
    {
        foreach ($result as $key => $value) {
            $result[$key] = self::parseTimestamps($value, $timezone);
        }
        return $result;
    }

    public static function parseTimestamps(array $result, $timezone)
    {
        foreach ($result as $key => $value) {
            if (null !== $value && substr($key, -3, 3) == '_ts' && preg_match("/^\d{4}\-\d{2}\-\d{2}/", $value)) {
                $result[$key] = \DateTime::createFromFormat('Y-m-d H:i:s', $value)->setTimezone(new \DateTimeZone($timezone));
            }
        }
        return $result;
    }

    public static function jsToFixed($number, $decimals = 0): string
    {
        // Match Javascript tofixed function
        // Multiply by 10 to shift decimal, floor it to remove excess decimals, and then divide by 10 to restore
        $roundedNumber = floor($number * 10) / 10;
        // If the number is still the same after truncating, we round it normally
        if ($number - $roundedNumber >= 0.05) {
            $roundedNumber += 0.1;
        }
        return number_format($roundedNumber, $decimals);
    }
}
