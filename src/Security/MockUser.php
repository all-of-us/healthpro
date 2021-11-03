<?php

namespace App\Security;

class MockUser
{
    private $id;
    private $email;
    private $timezone;

    public function __construct($email, $timezone = null)
    {
        $this->email = $email;
        $this->timezone = $timezone;
        $this->id = hexdec(substr(sha1($email), 0, 8));
    }

    public function getId()
    {
        return $this->id;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getTimezone()
    {
        return $this->timezone;
    }

    public function getUserId()
    {
        return $this->id;
    }
}
