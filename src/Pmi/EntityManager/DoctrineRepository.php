<?php
namespace Pmi\EntityManager;

use App\Util;

class DoctrineRepository extends EntityManager
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
            $result = Util::parseTimestamps($result, $this->timezone);
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
            $result = Util::parseMultipleTimestamps($result, $this->timezone);
        }
        return $result;
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

    public function truncate()
    {
        $this->dbal->query("DELETE FROM `{$this->entity}`");
    }

    public function wrapInTransaction($callback)
    {
        $this->dbal->transactional($callback);
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
}
