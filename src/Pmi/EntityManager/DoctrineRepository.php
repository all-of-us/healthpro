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
        $result = $this->parseTimestamps($result);
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
        $result = $this->parseTimestamps($result);
        return $result;
    }

    protected function parseTimestamps($result)
    {
        if(is_array($result)) {
            foreach($result as $key => $value) {
                if(is_array($value)) {
                    // Nested array sent by fetchBy
                    foreach($value as $field => $colValue) {

                        if(NULL != $colValue && substr($field, -3, 3) == '_ts' && preg_match("/^\d{4}\-\d{2}\-\d{2}/", $colValue)) {
                            $result[$key][$field] = \DateTime::createFromFormat('Y-m-d H:i:s', $colValue)->setTimezone(new \DateTimeZone($this->timezone));
                        }
                    }

                }
                else {
                    if(NULL !== $value && substr($key, -3, 3) == '_ts' && preg_match("/^\d{4}\-\d{2}\-\d{2}/", $value)) {
                        $result[$key] = \DateTime::createFromFormat('Y-m-d H:i:s', $value)->setTimezone(new \DateTimeZone($this->timezone));
                    }
                }
            }
        }
        return $result;
    }

    public function insert($data)
    {
        $success = $this->dbal->insert($this->entity, $data);
        if ($success) {
            return $this->dbal->lastInsertId();
        } else {
            return false;
        }
    }

    public function update($id, $data)
    {
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
