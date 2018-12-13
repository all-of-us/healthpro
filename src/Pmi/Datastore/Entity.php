<?php
namespace Pmi\Datastore;

use Pmi\Datastore\Exception\UnknownEntityFieldException;
use Exception;

abstract class Entity
{
    /** Use self::getSchema() to access these. */
    private static $schemas;
    private $gdsEntity;

    protected $keyId;
    protected $keyName;
    protected $data = [];
    
    /** Constucts and returns the entity's GDS\Schema. */
    protected static function buildSchema()
    {
        throw new Exception("buildSchema() not implemented!");
    }
    
    /** Gets the called class' GDS\Schema. */
    public static function getSchema() {
        $cls = get_called_class();
        if (!isset(self::$schemas[$cls])) {
            self::$schemas[$cls] = static::buildSchema();
        }
        return self::$schemas[$cls];
    }
    
    public function getKeyId()
    {
        return $this->keyId;
    }

    public function setKeyId($keyId)
    {
        $this->keyId = $keyId;
    }

    public function getKeyName()
    {
        return $this->keyId;
    }

    public function setKeyName($keyName)
    {
        $this->keyName = $keyName;
    }

    public function getDataArray()
    {
        return $this->data;
    }

    public function __call($name, $arguments)
    {
        if (preg_match('/^(get|set)(.+)$/', $name, $m)) {
            $action = $m[1];
            $field = lcfirst($m[2]);
            if (!self::fieldExists($field)) {
                throw new UnknownEntityFieldException(self::getSchema()->getKind(), $field);
            }
            if ($action == 'set') {
                if (count($arguments) === 0) {
                    throw new Exception("No arguments passed to set{$field}()");
                }
                $value = $arguments[0];
                $value = self::convertType($field, $value);
                $this->data[$field] = $value;
                return $this;
            } elseif ($action == 'get') {
                if (isset($this->data[$field])) {
                    return $this->data[$field];
                } else {
                    return null;
                }
            }
        }
        throw new Exception("Call to undefined method {$name} for class " . get_class());
    }

    private static function convertType($field, $value)
    {
        if (!self::fieldExists($field)) {
            return $value;
        }
        $fields = self::getSchema()->getProperties();
        if (empty($fields[$field]['type'])) {
            return $value;
        }
        switch ($fields[$field]['type']) {
            case \GDS\Schema::PROPERTY_DATETIME:
                if (is_string($value)) {
                    $value = new \DateTime($value);
                }
                break;
        }
        return $value;
    }

    private static function fieldExists($field)
    {
        $fields = self::getSchema()->getProperties();
        return array_key_exists($field, $fields);
    }

    public function save()
    {
        $datastore = new Datastore();
        $keyId = $datastore->insert(self::getSchema(), $this->data, $this->keyName, $this->keyId);
        $this->setKeyId($keyId);
        return $this;
    }

    public function delete()
    {
        if ($this->gdsEntity) {
            $repository = new \GDS\Store(self::getSchema());
            $repository->delete($this->gdsEntity);
        }
    }

    public function loadFromArray(array $data)
    {
        foreach ($data as $field => $value) {
            if (self::fieldExists($field)) {
                $value = self::convertType($field, $value);
                $this->data[$field] = $value;
            }
        }
    }

    private function loadFromGdsEntity($gdsEntity)
    {
        $this->loadFromArray($gdsEntity->getData());
        if ($gdsEntity->getKeyId()) {
            $this->setKeyId($gdsEntity->getKeyId());
        }
        if ($gdsEntity->getKeyName()) {
            $this->setKeyName($gdsEntity->getKeyName());
        }
    }

    private static function buildSqlAndParameters(array $criteria)
    {
        $sql = 'SELECT * FROM ' . self::getSchema()->getKind();
        $parameters = [];
        if (count($criteria) > 0) {
            $sql  .= ' WHERE ';
            $conditions = [];
            foreach ($criteria as $field => $value) {
                $conditions[] = "{$field} = @{$field}";
                $parameters[$field] = $value;
            }
            $sql .= implode(' AND ', $conditions);
        }
        return [$sql, $parameters];
    }

    public static function fetchOneBy(array $criteria)
    {
        $datastore = new Datastore();
        list($sql, $parameters) = static::buildSqlAndParameters($criteria);
        $result = $datastore->fetchOneBySql(self::getSchema(), $sql, $parameters);
        if ($result) {
            $entity = new static();
            $entity->gdsEntity = $result;
            $entity->loadFromGdsEntity($result);
            return $entity;
        } else {
            return null;
        }
    }

    public static function fetchOneByKey($keyId) {
        $datastore = new Datastore();
        $result = $datastore->fetchOneByKey(self::getSchema(), $keyId);
        if ($result) {
            $entity = new static();
            $entity->gdsEntity = $result;
            $entity->loadFromGdsEntity($result);
            return $entity;
        } else {
            return null;
        }
    }

    public static function fetchOneByName($keyName) {
        $datastore = new Datastore();
        $result = $datastore->fetchOneByName(self::getSchema(), $keyName);
        if ($result) {
            $entity = new static();
            $entity->gdsEntity = $result;
            $entity->loadFromGdsEntity($result);
            return $entity;
        } else {
            return null;
        }
    }

    public static function fetchBy()
    {
        $datastoreClient = new DatastoreClientHelper();
        return $datastoreClient->fetchAll(static::getKind());
    }
    
    public static function walkEntities(callable $callback, $sql, $params)
    {
        $datastore = new Datastore();
        $datastore->walkEntities($callback, self::getSchema(), $sql, $params);
    }
    
    public static function countEntities($sql, $params, $limit = 0)
    {
        $datastore = new Datastore();
        return $datastore->countEntities(self::getSchema(), $sql, $params, $limit);
    }
}
