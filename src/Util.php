<?php

namespace App;

use Ramsey\Uuid\Uuid;

class Util
{
    public static function generateUuid(): string
    {
        return Uuid::uuid4()->toString();
    }

    public static function generateShortUuid(int $length = 16, bool $upper = true): string
    {
        $id = self::generateUuid();
        $id = self::shortenUuid($id, $length);
        if ($upper) {
            $id = strtoupper($id);
        }
        return $id;
    }

    public static function shortenUuid(string $uuid, int $length = 16): string
    {
        $id = preg_replace('/[^a-f0-9]/i', '', $uuid) ?? '';
        $id = substr($id, 0, $length);
        return $id;
    }

    public static function versionIsAtLeast(string $version, string $minVersion): bool
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

    /**
     * @param array<string, array<string, mixed>> $result
     *
     * @return array<string, array<string, mixed>>
     */
    public static function parseMultipleTimestamps(array $result, string $timezone): array
    {
        foreach ($result as $key => $value) {
            $result[$key] = self::parseTimestamps($value, $timezone);
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    public static function parseTimestamps(array $result, string $timezone): array
    {
        foreach ($result as $key => $value) {
            if (
                is_string($value)
                && substr($key, -3, 3) === '_ts'
                && preg_match("/^\d{4}\-\d{2}\-\d{2}/", $value)
            ) {
                $timestamp = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
                if ($timestamp !== false) {
                    $result[$key] = $timestamp->setTimezone(new \DateTimeZone($timezone));
                }
            }
        }
        return $result;
    }
}
