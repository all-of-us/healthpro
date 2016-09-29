<?php
namespace Pmi\Drc;

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
}
