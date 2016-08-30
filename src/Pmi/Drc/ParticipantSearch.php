<?php
namespace Pmi\Drc;

class ParticipantSearch
{
    public function search($params)
    {
        return [
            (object)['firstName' => 'John', 'lastName' => 'Doe', 'dob' => new \DateTime('1980-01-01')],
            (object)['firstName' => 'Jane', 'lastName' => 'Smith', 'dob' => new \DateTime('1985-01-01')]
        ];
    }
}
