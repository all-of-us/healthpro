<?php
namespace Pmi\Drc;

class ParticipantSearch
{
    // Mock API data
    protected static $data = [
        1001 => ['John', 'Doe', '1980-01-01', 'M'],
        1002 => ['Jane', 'Smith', '1985-01-01', 'F']
    ];

    protected function rowToObject($row, $id)
    {
        return (object)[
            'id' => $id,
            'firstName' => $row[0],
            'lastName' => $row[1],
            'dob' => new \DateTime($row[2]),
            'gender' => $row[3]
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
