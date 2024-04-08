<?php

namespace App\Drc;

/**
 * Represents the salesforce user logged in through OAuth.
 */
class SalesforceUser
{
    private string $id;
    private string $email;

    public function __construct($id, $email)
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

    public function getUserId(): string
    {
        return $this->getId();
    }
}
