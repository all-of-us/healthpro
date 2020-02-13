<?php

namespace Pmi\Security;

/** Simulates GAE's User. */
class MockGoogleUser
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
}
