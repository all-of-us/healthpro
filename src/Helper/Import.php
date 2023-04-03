<?php

namespace App\Helper;

class Import
{
    public const DEFAULT_CSV_ROWS_LIMIT = 5000;
    public const EMAIL_DOMAIN = 'pmi-ops.org';
    public const STATUS_SUCCESS = 1;
    public const STATUS_INVALID_PARTICIPANT_ID = 2;
    public const STATUS_RDR_INTERNAL_SERVER_ERROR = 3;
    public const STATUS_OTHER_RDR_ERRORS = 4;
    public const STATUS_INVALID_USER = 5;
    public const COMPLETE = 1;
    public const COMPLETE_WITH_ERRORS = 2;


    public static function hasDuplicateParticipantId($imports, $participantId): bool
    {
        foreach ($imports as $import) {
            if ($import['participant_id'] === $participantId) {
                return true;
            }
        }
        return false;
    }

    public static function isValidParticipantId($participantId): bool
    {
        return preg_match("/^P\d{9}+$/", $participantId);
    }

    public static function isValidEmail($email): bool
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $email);
            $domain = array_pop($parts);
            return $domain === self::EMAIL_DOMAIN;
        }
        return false;
    }

    public static function isValidDate($date): bool
    {
        return (bool) strtotime($date);
    }
}
