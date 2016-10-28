<?php
namespace Tests\Pmi;

/** Simulates GAE's User. */
class GoogleUser
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

    /*
     * For parity with google\appengine\api\users\User::getUserId
     */
    public function getUserId()
    {
        return $this->getId();
    }
}
