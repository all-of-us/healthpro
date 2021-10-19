<?php

namespace App\Tests;

/** Simulates a Google API Group. */
class GoogleGroup
{
    private $email;
    private $name;
    private $description;

    public function __construct($email, $name, $description)
    {
        $this->email = $email;
        $this->name = $name;
        $this->description = $description;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDescription()
    {
        return $this->description;
    }
}
