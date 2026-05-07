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


    /**
     * @param list<array{participant_id: string}> $imports
     */
    public static function hasDuplicateParticipantId(array $imports, string $participantId): bool
    {
        foreach ($imports as $import) {
            if ($import['participant_id'] === $participantId) {
                return true;
            }
        }
        return false;
    }

    public static function isValidParticipantId(string $participantId): bool
    {
        return preg_match("/^P\d{9}+$/", $participantId) === 1;
    }

    public static function isValidEmail(string $email): bool
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $email);
            $domain = array_pop($parts);
            return $domain === self::EMAIL_DOMAIN;
        }
        return false;
    }

    public static function isValidDate(string $date): bool
    {
        return (bool) strtotime($date);
    }
}
