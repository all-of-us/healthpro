<?php

namespace App\WorkQueue\ColumnDefs\NPH;

use DateTime;

class UtilFunctions
{
    public static function getLatestTimestampElement($data): ?array
    {
        $lastSeen = null;
        $lastSeenData = null;
        foreach ($data as $value) {
            $timestamp = new DateTime($value['time']);
            if ($lastSeen == null || $timestamp > $lastSeen) {
                $lastSeen = $timestamp;
                $lastSeenData = $value;
            }
        }
        return $lastSeenData;
    }

    public static function searchLatestTimestampElement($data, array $search): ?array
    {
        $lastSeen = null;
        $lastSeenData = null;
        foreach ($data as $value) {
            $timestamp = new DateTime($value['time']);
            if (in_array($value['value'], $search, true) && ($lastSeen == null || $timestamp > $lastSeen)) {
                $lastSeen = $timestamp;
                $lastSeenData = $value;
            }
        }
        return $lastSeenData;
    }
}
