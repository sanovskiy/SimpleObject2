<?php
/**
 * Created: {13.11.2018}
 *
 * @author    Pavel Terentyev
 * @copyright 2018
 */

namespace Sanovskiy\SimpleObject;


use Envms\FluentPDO\Query;

/**
 * Class ActiveRecordAbstract
 * @package Sanovskiy\SimpleObject
 * @property integer $Id
 */
class ActiveRecordAbstract implements \Iterator, \ArrayAccess, \Countable
{
    protected static $SimpleObjectConfigNameRead = 'default';
    protected static $SimpleObjectConfigNameWrite = 'default';
    /**
     * @var string
     */
    protected static $TableName = '';
    /**
     * Model properties to field mapping
     * @var array
     */
    protected static $propertiesMapping = [];
    protected static $dataTransformRules = [];
    /**
     * @var PDO
     */
    protected $DBConWrite;
    /**
     * @var PDO
     */
    protected $DBConRead;
    /**
     * @var bool
     */
    protected $existInStorage = false;
    protected $values = [];
    protected $loadedValues = [];

    /**
     * ActiveRecordAbstract constructor.
     *
     * @param int|null $id
     *
     * @throws Exception
     * @throws \Envms\FluentPDO\Exception
     */
    function __construct(?int $id = null)
    {
        if (!$this->init()) {
            throw new Exception('Model ' . static::class . '::init() failed');
        }
        $this->DBConRead = Util::getConnection(static::$SimpleObjectConfigNameRead);
        $this->DBConWrite = Util::getConnection(static::$SimpleObjectConfigNameWrite);
        if (is_null($id)) {
            $this->Id = null;
            return;
        }

        $this->Id = $id;
        $this->load();
    }

    /**
     * @return bool
     */

    protected function init(): bool

    {
        return true;
    }

    /**
     * Loads model data from storge
     *
     * @param bool $forceLoad
     *
     * @return bool
     * @throws Exception
     * @throws \Envms\FluentPDO\Exception
     */
    protected function load($forceLoad = false): bool
    {
        if (null === $this->Id) {
            return false;
        }
        if (($forceLoad || !($result = RuntimeCache::getInstance()->get(static::class, $this->Id)))) {
            $query = (new Query($this->DBConRead))->from(static::$TableName);
            $query
                ->select(array_keys(static::$propertiesMapping), true)
                ->where('id = ?', $this->__get($this->getFieldProperty('id')))
            ;
            if (!$result = $query->fetch()) {
                $this->existInStorage = false;
                return false;
                //throw new Exception('Error loading data for ' . static::class . ' (Id:' . $this->__get('id') . ')');
            }
            $this->existInStorage = true;
            RuntimeCache::getInstance()->put(static::class, $this->Id, $result);
            $this->loadedValues = $result;
        }
        $this->populate($result,true,false);
        return true;
    }

    /**
     * @param string|\PDOStatement $source
     * @param array                $bind
     *
     * @return Collection<static>
     * @throws Exception
     * @throws \Envms\FluentPDO\Exception
     */
    public static function factory($source, array $bind = [])
    {
        if (!is_string($source) && !($source instanceof \PDOStatement)) {
            throw new Exception('Unknown type ' . gettype($source) . '. Expected string or PDOStatement');
        }

        if (is_string($source)) {
            $source = Util::getConnection(static::$SimpleObjectConfigNameRead)->prepare($source);
        }

        $source->execute($bind);

        $collection = new Collection();
        $collection->setClassName(static::class);

        while ($row = $source->fetch(\PDO::FETCH_ASSOC)) {
            if (count($missingFields = array_diff(array_keys($row), array_keys(static::$propertiesMapping))) > 0) {
                throw new Exception('Missing fields ' . implode(', ', $missingFields));
            }
            $entity = new static();
            $entity->populate($row,true,false);
            $collection->push($entity);

        }

        return $collection;
    }

    /**
     * @param $field
     * @param $transformName
     * @param $transformOptions
     *
     * @return bool
     */
    public static function setReadTransform($field, $transformName, $transformOptions)
    {
        if (!static::isTableFieldExist($field)) {
            return false;
        }
        static::$dataTransformRules[$field]['read'][$transformName] = $transformOptions;
        return true;
    }

    /**
     * @param $field
     * @param $transformName
     * @param $transformOptions
     *
     * @return bool
     */
    public static function setWriteTransform($field, $transformName, $transformOptions)
    {
        if (!static::isTableFieldExist($field)) {
            return false;
        }
        static::$dataTransformRules[$field]['write'][$transformName] = $transformOptions;
        return true;
    }


    /**
     * @param string $name
     *
     * @return mixed
     * @throws Exception
     */
    public function __get(string $name)
    {
        if (static::isPropertyExist($name)) {
            return $this->values[static::getPropertyField($name)];
        }
        if (static::isTableFieldExist($name)) {
            return $this->values[$name];
        }

        switch ($name) {
            case 'TableName':
                return static::$TableName;
            case 'TableFields':
                return array_keys(static::$propertiesMapping);
            case 'Properties':
                return array_values(static::$propertiesMapping);
            case 'SimpleObjectConfigNameRead':
                return static::$SimpleObjectConfigNameRead;
            case 'SimpleObjectConfigNameWrite':
                return static::$SimpleObjectConfigNameWrite;
        }
        return null;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return bool|mixed
     * @throws Exception
     */
    public function __set(string $name, $value)
    {
        if (static::isPropertyExist($name)) {
            return $this->values[static::getPropertyField($name)] = $value;
        }
        if (static::isTableFieldExist($name)) {
            return $this->values[$name] = $value;
        }
        return false;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public static function isPropertyExist(string $name): bool
    {
        return in_array($name, static::$propertiesMapping);
    }

    /**
     * @param string $propertyName
     *
     * @return string
     * @throws Exception
     */
    public static function getPropertyField(string $propertyName): string
    {
        if (!static::isPropertyExist($propertyName)) {
            throw new Exception('Property ' . $propertyName . ' not exist im model ' . static::class);
        }
        return array_flip(static::$propertiesMapping)[$propertyName];

    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public static function isTableFieldExist(string $name): bool
    {
        return array_key_exists($name, static::$propertiesMapping);
    }

    /**
     * @param string $tableFieldName
     *
     * @return string
     * @throws Exception
     */
    public function getFieldProperty(string $tableFieldName): string
    {
        if (!static::isTableFieldExist($tableFieldName)) {
            throw new Exception('Table field ' . $tableFieldName . ' not exist im model ' . static::class);
        }
        return static::$propertiesMapping[$tableFieldName];
    }

    /**
     * Fills model fith supplied array data
     *
     * @param array $data
     * @param bool  $applyTransforms
     * @param bool  $isNewRecord
     */
    public function populate(array $data, $applyTransforms = true, $isNewRecord = false)
    {
        if (!$isNewRecord){
            $this->loadedValues = $data;
        }
        foreach ($data as $tableFieldName => $value) {
            if (!static::isTableFieldExist($tableFieldName)) {
                continue;
            }
            if ($applyTransforms && $transformations = $this->getReadTransforms($tableFieldName)) {
                foreach ($transformations as $transformation => $options) {
                    $value = Transform::apply_transform($transformation, $value, $options);
                }
            }
            $this->values[$tableFieldName] = $value;
        }
    }

    /**
     * @param string $fieldName
     *
     * @return array
     */
    public function getReadTransforms(string $fieldName)
    {
        if (!array_key_exists($fieldName, static::$dataTransformRules) || !array_key_exists('read', static::$dataTransformRules[$fieldName])) {
            return [];
        }
        return static::$dataTransformRules[$fieldName]['read'];
    }

    /**
     * @return string
     */
    public static function getTableName(): string
    {
        return self::$TableName;
    }

    /**
     * @return PDO
     */
    public static function getDBConRead()
    {
        return Util::getConnection(self::$SimpleObjectConfigNameRead);
    }

    /**
     * @return PDO
     */
    public static function getDBConWrite()
    {
        return Util::getConnection(self::$SimpleObjectConfigNameWrite);
    }

    /**
     * @param array $conditions
     *
     * @return static
     * @throws Exception
     * @throws \Envms\FluentPDO\Exception
     */
    public static function one(array $conditions)
    {
        return static::find($conditions)->getElement();
    }

    /**
     * @param array $conditions
     *
     * @return Collection<static>
     * @throws Exception
     * @throws \Envms\FluentPDO\Exception
     */
    public static function find(array $conditions)
    {
        $select = (new Query(Util::getConnection(static::$SimpleObjectConfigNameRead)))->from(static::$TableName);

        $select->select(array_keys(static::$propertiesMapping), true);
        foreach ($conditions as $condition => $value) {
            switch (strtolower($condition)) {
                case '(order)':
                    $select->orderBy($value);
                    break;
                case '(limit)':
                    $select->limit($value);
                    break;
                case '(offset)':
                    $select->offset($value);
                    break;
                case '(having)':
                    $select->having($value);
                    break;
                case '(group)':
                    continue;
                /*$select->groupBy($value);
                break;*/
                default:
                    $select->where($condition, $value);
                    break;
            }
        }
        //echo $select->getQuery().PHP_EOL;die();
        $result = new Collection();
        foreach ($select as $_row) {
            $entity = new static();
            $entity->populate($_row,true,false);
            $result->push($entity);
        }
        return $result;
    }

    /**
     * @throws Exception
     * @throws \Envms\FluentPDO\Exception
     */
    public function reload()
    {
        $this->load(true);
    }

    /**
     * @param bool $force
     *
     * @return bool
     * @throws Exception
     * @throws \Envms\FluentPDO\Exception
     */
    public function save($force = false): bool
    {
        $data = $this->getDataForSave();

        unset($data['id']);
        switch ($this->isExistInStorage()) {
            case false: // Create new record
                $insert = (new Query($this->DBConWrite))
                    ->insertInto(static::$TableName)
                    ->values($data)
                ;

                if (!($id = $insert->execute())) {
                    return false;
                }
                $this->values['id'] = $id;
                $this->loadedValues = $this->values;
                $this->existInStorage = true;
                break;
            case true: // Update existing record

                if (!$force) {
                    $data = array_diff(array_intersect_key($data, $this->loadedValues), $this->loadedValues);
                    if (empty($data)) {
                        return true;
                    }
                }

                $q = (new Query($this->DBConWrite));
                $q->exceptionOnError = true;
                $update = $q
                    ->update(static::$TableName)
                    ->set($data)
                    ->where('id = ?', $this->__get('id'))
                ;

                if (!$update->execute(true)) {
                    return false;
                }
                break;
        }
        return true;
    }


    /**
     * @param bool $applyTransforms
     *
     * @return array|null
     */
    public function getDataForSave($applyTransforms = true)
    {
        $data = [];
        foreach (array_keys(static::$propertiesMapping) as $tableFieldName) {
            $value = null;
            if (isset($this->values[$tableFieldName])) {
                $value = $this->values[$tableFieldName];
            }

            if ($applyTransforms && $value !== null && $transformations = $this->getWriteTransforms($tableFieldName)) {
                foreach ($transformations as $transformation => $options) {
                    $value = Transform::apply_transform($transformation, $value, $options);
                }
            }
            if (null === $value) {
                continue;
            }
            $data[$tableFieldName] = $value;
        }
        return $data;
    }

    /**
     * @param string $fieldName
     *
     * @return array
     */
    public function getWriteTransforms(string $fieldName)
    {
        if (!array_key_exists($fieldName, static::$dataTransformRules) || !array_key_exists('write', static::$dataTransformRules[$fieldName])) {
            return [];
        }
        return static::$dataTransformRules[$fieldName]['write'];
    }

    /**
     * @return bool
     */
    public function isExistInStorage(): bool
    {
        //return !!$this->existInStorage;
        return !empty($this->loadedValues);
    }

    /**
     * @return bool
     * @throws Exception
     * @throws \Envms\FluentPDO\Exception
     */
    public function delete()
    {
        if (!$this->isExistInStorage()) {
            return false;
        }
        $success = !!(new Query($this->DBConWrite))->deleteFrom(static::$TableName)->where('id = ?', $this->__get('id'))->execute();
        if ($success) {
            RuntimeCache::getInstance()->drop(static::class, $this->__get('id'));
        }
        return $success;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function __toArray()
    {
        $result = [];
        foreach ($this->values as $tableFieldName => $value) {
            if (!$propertyName = $this->getFieldProperty($tableFieldName)) {
                continue;
            }
            $result[$propertyName] = $value;
        }
        return $result;
    }

    /**
     *
     */
    public function rewind()
    {
        reset(static::$propertiesMapping);
    }

    /**
     * @return mixed
     */
    public function current()
    {
        $property = current(static::$propertiesMapping);
        if (static::isPropertyExist($property)) {
            try {
                return $this->__get($property);
            } catch (Exception $e) {
            }
        }
        return false;
    }

    /**
     * @return mixed
     */
    public function next()
    {
        $property = next(static::$propertiesMapping);
        if (static::isPropertyExist($property)) {
            try {
                return $this->__get($property);
            } catch (Exception $e) {
            }

        }

        return false;
    }

    /**
     * @return bool
     */
    public function valid(): bool
    {
        $key = $this->key();
        return ($key !== null && $key !== false);
    }

    /**
     * @return mixed
     */
    public function key()
    {
        return key(static::$propertiesMapping);
    }

    /**
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return (static::isPropertyExist($offset) || static::isTableFieldExist($offset));
    }

    /**
     * @param mixed $offset
     *
     * @return bool|mixed
     */
    public function offsetGet($offset)
    {
        if (static::isPropertyExist($offset) || static::isTableFieldExist($offset)) {
            try {
                return $this->__get($offset);
            } catch (Exception $e) {
            }
        }
        return false;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     *
     * @return bool|mixed
     */
    public function offsetSet($offset, $value)
    {
        try {
            return $this->__set($offset, $value);
        } catch (Exception $e) {
        }
        return false;
    }

    /**
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetUnset($offset)
    {
        return false;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count(static::$propertiesMapping);
    }

}