<?php
namespace Tests\Pmi;

/** Simulates GAE's User. */
class GoogleUser
{
    private $email;
    
    public function __construct($email)
    {
        $this->email = $email;
    }
    
    public function getEmail()
    {
        return $this->email;
    }
}
