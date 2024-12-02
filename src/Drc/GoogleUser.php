<?php

namespace App\Drc;

/**
 * Represents the Google user logged in through OAuth.
 */
class GoogleUser
{
    private $id;
    private $email;

    public function __construct($id, $email)
    {
        $this->id = $id;
        $this->email = $email;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getEmail()
    {
        return $this->email;
    }

    // For parity with google\appengine\api\users\User::getUserId
    public function getUserId()
    {
        return $this->getId();
    }

    public function getTimezone()
    {
        return null;
    }
}
