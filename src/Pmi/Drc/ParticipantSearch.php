<?php
namespace Pmi\Drc;

class ParticipantSearch
{
    // Mock API data
    protected static $data = [
        1001 => ['John', 'Doe', '1980-01-01', 'M', '37203', true],
        1002 => ['Jane', 'Smith', '1985-04-21', 'F', '02142', true],
        1003 => ['Incomplete', 'User', '1990-10-10', 'F', '02142', false]
    ];

    protected function rowToObject($row, $id)
    {
        return (object)[
            'id' => $id,
            'firstName' => $row[0],
            'lastName' => $row[1],
            'dob' => new \DateTime($row[2]),
            'gender' => $row[3],
            'zip' => $row[4],
            'consentComplete' => $row[5]
        ];
    }

    public function search($params)
    {
        $results = [];
        foreach (self::$data as $id => $row) {
            $results[] = $this->rowToObject($row, $id);
        }

        return $results;
    }

    public function getById($id)
    {
        if (array_key_exists($id, self::$data)) {
            return $this->rowToObject(self::$data[$id], $id);
        } else {
            return null;
        }
    }
}
