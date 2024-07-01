<?php

namespace App\Drc;

/**
 * Represents the salesforce user logged in through OAuth.
 */
class SalesforceUser
{
    private string $id;
    private string $email;
    private string $timezone;

    public function __construct($id, $email, $timezone)
    {
        $this->id = $id;
        $this->email = $email;
        $this->timezone = $timezone;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUserId(): string
    {
        return $this->getId();
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }
}
