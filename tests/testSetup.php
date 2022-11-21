<?php

namespace App\Tests;

use App\Helper\Participant;

class testSetup
{
    public static function generateParticipant(string $id = null, string $firstName = null, string $lastName = null, \DateTime $dateOfBirth = null): Participant
    {
        if ($id === null) {
            $id = "P0000001";
        }
        if ($firstName === null) {
            $firstName = "John";
        }
        if ($lastName === null) {
            $lastName = "Doe";
        }
        if ($dateOfBirth === null) {
            $dateOfBirth = new \DateTime('2000-01-01');
        }
        return new Participant((object)[
            'id' => $id,
            'dateOfBirth' => $dateOfBirth->format('y-m-d'),
            'firstName' => $firstName,
            'lastName' => $lastName
        ]);
    }
}
