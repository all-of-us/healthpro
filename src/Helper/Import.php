<?php

namespace App\Helper;

class Import
{
    public const DEFAULT_CSV_ROWS_LIMIT = 5000;
    public const EMAIL_DOMAIN = 'pmi-ops.org';

    public static function hasDuplicateParticipantId($idVerifications, $participantId): bool
    {
        foreach ($idVerifications as $idVerification) {
            if ($idVerification['participant_id'] === $participantId) {
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
        return (bool)strtotime($date);
    }
}
