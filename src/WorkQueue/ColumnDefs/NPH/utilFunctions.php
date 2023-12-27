<?php

namespace App\WorkQueue\ColumnDefs\NPH;
use DateTime;

class utilFunctions
{
    public const GREENCHECK = "<i class='fas fa-check text-success'></i>";
    public const REDX = "<i class='fas fa-times text-danger'></i>";
    public const ORANGECHECK = "<i class='fas fa-check text-warning'></i>";
    public static function getLatestTimestampElement($data): ?array
    {
        $lastSeen = null;
        $lastSeenStatus = null;
        $lastSeenData = null;
        foreach ($data as $value) {
            $timestamp = new DateTime($value['time']);
            if ($lastSeen == null || $timestamp > $lastSeen) {
                $lastSeen = $timestamp;
                $lastSeenStatus = $value['value'];
                $lastSeenData = $value;
            }
        }
        return $lastSeenData;
    }

    public static function searchLatestTimestampElement($data, array $search): ?array
    {
        $lastSeen = null;
        $lastSeenStatus = null;
        $lastSeenData = null;
        foreach ($data as $value) {
            $timestamp = new DateTime($value['time']);
            if (in_array($value['value'], $search, true) && ($lastSeen == null || $timestamp > $lastSeen)) {
                $lastSeen = $timestamp;
                $lastSeenStatus = $value['value'];
                $lastSeenData = $value;
            }
        }
        return $lastSeenData;
    }
}
