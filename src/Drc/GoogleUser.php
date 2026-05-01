<?php

namespace App\Drc;

/**
 * Represents the Google user logged in through OAuth.
 */
class GoogleUser
{
    private string $id;
    private string $email;

    public function __construct(string $id, string $email)
    {
        $this->id = $id;
        $this->email = $email;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    // For parity with google\appengine\api\users\User::getUserId
    public function getUserId(): string
    {
        return $this->getId();
    }

    public function getTimezone(): null
    {
        return null;
    }
}
