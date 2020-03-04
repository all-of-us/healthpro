<?php

namespace Pmi\Security;

class MockUser
{
    private $id;
    private $email;

    public function __construct($email)
    {
        $this->email = $email;
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

    public function getUserId()
    {
        return $this->id;
    }
}
