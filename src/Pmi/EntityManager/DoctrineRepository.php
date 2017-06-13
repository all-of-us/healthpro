<?php
namespace Pmi\EntityManager;

class DoctrineRepository
{
    protected $dbal;
    protected $entity;
    protected $timezone;

    public function __construct($dbal, $entity, $timezone)
    {
        $this->dbal = $dbal;
        $this->entity = $entity;
        $this->timezone = $timezone;
    }

    public function fetchOneBy(array $where)
    {
        $parameters = [];
        $columns = [];
        foreach ($where as $column => $value) {
            $parameters[] = $value;
            $columns[] = "`{$column}` = ?";
        }
        $query = "SELECT * FROM `{$this->entity}` WHERE " . join(' AND ', $columns);
        $result = $this->dbal->fetchAssoc($query, $parameters);

        // Convert timestamp fields into user's time zone since they're stored as UTC in the database
        if ($result) {
            $result = $this->parseTimestamps($result);
        }
        return $result;
    }

    public function fetchBy(array $where, array $order = [], $limit = null)
    {
        $query = "SELECT * FROM `{$this->entity}`";
        $parameters = [];
        if (!empty($where)) {
            $columns = [];
            foreach ($where as $column => $value) {
                $parameters[] = $value;
                $columns[] = "`{$column}` = ?";
            }
            $query .= ' WHERE ' . join(' AND ', $columns);
        }
        if (!empty($order)) {
            $sorts = [];
            foreach ($order as $column => $direction) {
                $sorts[] = "`{$column}` {$direction}";
            }
            $query .= ' ORDER BY ' . join(', ', $sorts);
        }
        if ($limit) {
            $query .= ' LIMIT ' . (int)$limit;
        }
        $result = $this->dbal->fetchAll($query, $parameters);
        if ($result) {
            $result = $this->parseMultipleTimestamps($result);
        }
        return $result;
    }

    protected function parseMultipleTimestamps(array $result)
    {
        foreach ($result as $key => $value) {
            $result[$key] = $this->parseTimestamps($value);
        }
        return $result;
    }

    protected function parseTimestamps(array $result)
    {
        foreach ($result as $key => $value) {
            if (null !== $value && substr($key, -3, 3) == '_ts' && preg_match("/^\d{4}\-\d{2}\-\d{2}/", $value)) {
                $result[$key] = \DateTime::createFromFormat('Y-m-d H:i:s', $value)->setTimezone(new \DateTimeZone($this->timezone));
            }
        }
        return $result;
    }

    protected function dateTimesToStrings(array $data)
    {
        foreach ($data as $key => $value) {
            if ($value instanceof \DateTime) {
                $value->setTimezone(new \DateTimezone('UTC'));
                $data[$key] = $value->format('Y-m-d H:i:s');
            }
        }
        return $data;
    }

    public function fetchBySql($where, array $parameters = [], array $order = [], $limit = null)
    {
        $query = "SELECT * FROM `{$this->entity}`";
        $query .= ' WHERE ' . $where;
        if (!empty($order)) {
            $sorts = [];
            foreach ($order as $column => $direction) {
                $sorts[] = "`{$column}` {$direction}";
            }
            $query .= ' ORDER BY ' . join(', ', $sorts);
        }
        if ($limit) {
            $query .= ' LIMIT ' . (int)$limit;
        }
        return $this->dbal->fetchAll($query, $parameters);
    }

    public function insert($data)
    {
        $data = $this->dateTimesToStrings($data);
        $success = $this->dbal->insert($this->entity, $data);
        if ($success) {
            return $this->dbal->lastInsertId();
        } else {
            return false;
        }
    }

    public function update($id, $data)
    {
        $data = $this->dateTimesToStrings($data);
        return $this->dbal->update(
            $this->entity,
            $data,
            ['id' => $id]
        );
    }

    public function delete($id)
    {
        return $this->dbal->delete(
            $this->entity,
            ['id' => $id]
        );
    }
}
