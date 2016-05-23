<?php

namespace T4\Orm;

use T4\Core\Collection;
use T4\Dbal\QueryBuilder;

/**
 * Trait TRelations
 * @package T4\Orm
 * @mixin \T4\Orm\Model
 */
trait TRelations
{

    /**
     * @return array
     */
    public static function getRelations()
    {
        $schema = static::getSchema();
        return !empty($schema['relations']) ? $schema['relations'] : [];
    }

    /**
     * @param mixed $name
     * @return array
     */
    public static function getRelation($name)
    {
        return static::getRelations() ? static::getRelations()[$name] : [];
    }

    /**
     * @param array $relation
     * @return string
     */
    public static function getRelationLinkName($relation)
    {
        if (!empty($relation['on'])) {
            return $relation['on'];
        }

        if (!empty($relation['by'])) {
            return $relation['by'];
        }

        /** @var \T4\Orm\Model $class */
        $class = get_called_class();

        switch ($relation['type']) {
            case $class::BELONGS_TO:
                $class = explode('\\', $relation['model']);
                $class = array_pop($class);
                return '__' . strtolower($class) . '_id';
            case $class::HAS_ONE:
            case $class::HAS_MANY:
                $class = explode('\\', $class);
                $class = array_pop($class);
                return '__' . strtolower($class) . '_id';
            case $class::MANY_TO_MANY:
                $thisTableName = $class::getTableName();
                $relationClass = (false !== strpos($relation['model'], 'App\\')) ? $relation['model'] : '\\App\\Models\\' . $relation['model'];
                $thatTableName = $relationClass::getTableName();
                return $thisTableName < $thatTableName ? $thisTableName . '_to_' . $thatTableName : $thatTableName . '_to_' . $thisTableName;
        }

    }

    public static function getManyToManyThisLinkColumnName($relation)
    {
        if (!empty($relation['this'])) {
            return $relation['this'];
        }

        $class = get_called_class();
        $class = explode('\\', $class);
        $class = array_pop($class);
        return '__' . strtolower($class) . '_id';
    }

    public static function getManyToManyThatLinkColumnName($relation)
    {
        if (!empty($relation['that'])) {
            return $relation['that'];
        }

        $class = explode('\\', $relation['model']);
        $class = array_pop($class);
        return '__' . strtolower($class) . '_id';
    }

    /**
     * @param string $key
     * @param array $options
     * @return mixed
     * @throws Exception
     */
    public function getRelationLazy($key, $options = [])
    {
        $class = get_class($this);
        $relations = $class::getRelations();
        if (empty($relations[$key]))
            throw new Exception('No such column or relation: ' . $key . ' in model of ' . $class . ' class');

        $relation = $relations[$key];
        switch ($relation['type']) {

            case $class::BELONGS_TO:
                $relationClass = $relation['model'];
                $link = $class::getRelationLinkName($relation);
                $subModel = $relationClass::findByPK($this->{$link});
                if (empty($subModel))
                    return null;
                else
                    return $relationClass::findByPK($this->{$link});
                break;

            case $class::HAS_ONE:
                $relationClass = $relation['model'];
                $link = $class::getRelationLinkName($relation);
                return $relationClass::findByColumn($link, $this->getPk(), $options);
                break;

            case $class::HAS_MANY:
                $relationClass = $relation['model'];
                $link = $class::getRelationLinkName($relation);
                return $relationClass::findAllByColumn($link, $this->getPk(), $options);
                break;

            case $class::MANY_TO_MANY:
                $relationClass = $relation['model'];
                $linkTable = $class::getRelationLinkName($relation);
                $pivots = $relationClass::getPivots($class, $key);
                if (!empty($pivots)) {
                    $pivotColumnsSql = ', ' . implode(', ', array_map(function ($x) {return 'j1.'.$x;}, array_keys($pivots)));
                } else {
                    $pivotColumnsSql = '';
                }

                $query = new QueryBuilder();
                $query
                    ->select('t1.*' . $pivotColumnsSql)
                    ->from($relationClass::getTableName())
                    ->join($linkTable, 't1.' . $relationClass::PK . '=j1.' . static::getManyToManyThatLinkColumnName($relation), 'left')
                    ->where(
                        '(j1.' . static::getManyToManyThisLinkColumnName($relation) . '=:id)'
                        . (isset($options['where']) ? ' AND (' . $options['where'] . ')': '')
                    );
                if (isset($options['order'])) {
                    $query->order($options['order']);
                }
                $query->params([':id' => $this->getPk()]);

                $result = $relationClass::getDbConnection()->query($query)->fetchAll(\PDO::FETCH_CLASS, $relationClass);
                if (!empty($result)) {
                    $ret = new Collection($result);
                    $ret->setNew(false);
                    return $ret;
                } else {
                    return new Collection();
                }
        }
    }

    protected function setRelation($key, $value)
    {
        $class = get_class($this);
        $relations = $class::getRelations();
        if (empty($relations[$key])) {
            throw new Exception('No such relation: ' . $key . ' in model of ' . $class . ' class');
        }

        $relation = $relations[$key];
        switch ($relation['type']) {

            case $class::HAS_ONE:
            case $class::BELONGS_TO:
                $relationClass = $relation['model'];
                if (empty($value) || $value instanceof $relationClass) {
                    $this->$key = $value;
                } else {
                    $this->$key = $relationClass::findByPk($value);
                }
                break;

            case $class::HAS_MANY:
                if (empty($value) || $value instanceof Collection) {
                    $this->$key = $value;
                } elseif (is_array($value)) {
                    $relationClass = $relation['model'];
                    $this->$key = new Collection();
                    foreach ($value as $pk)
                        $this->key->append($relationClass::findByPk($pk));
                }
                break;

            default:
                $this->$key = $value;
                break;

        }

    }

    protected function saveRelationsAfterHasMany($key)
    {
        /** @var \T4\Orm\Model $class */
        $class = get_class($this);
        $relation = $class::getRelations()[$key];
        $column = $class::getRelationLinkName($relation);

        /** @var \T4\Core\Collection $oldSubModelsSet */
        $oldSubModelsSet = $this->getRelationLazy($key);
        /** @var \T4\Core\Collection $newSubModelsSet */
        $newSubModelsSet = $this->{$key};

        $toDeletePks =
            array_diff(
                $oldSubModelsSet->collect($relation['model']::PK),
                $newSubModelsSet->collect($relation['model']::PK)
            );

        foreach ($toDeletePks as $toDeletePk) {
            /** @var \T4\Orm\Model $subModel */
            $subModel = $oldSubModelsSet->findByAttributes([$relation['model']::PK => $toDeletePk]);
            $subModel->{$column} = null;
            $subModel->save();
        }

        foreach ( $newSubModelsSet ?: [] as $subModel ) {
            /** @var \T4\Orm\Model $subModel */
            $subModel->{$column} = $this->getPk();
            $subModel->save();
        }
    }

    protected function saveRelationsAfterHasOne($key)
    {
        /** @var \T4\Orm\Model $class */
        $class = get_class($this);
        $relation = $class::getRelations()[$key];
        $column = $class::getRelationLinkName($relation);

        /** @var \T4\Orm\Model $oldSubModel */
        $oldSubModel = $this->getRelationLazy($key);
        /** @var \T4\Orm\Model $newSubModel */
        $newSubModel = $this->{$key};

        if ( !empty($oldSubModel) && (empty($newSubModel) || $newSubModel->getPk() != $oldSubModel->getPk()) ) {
            $oldSubModel->{$column} = null;
            $oldSubModel->save();
        }

        if ( !empty($newSubModel) ) {
            $newSubModel->{$column} = $this->getPk();
            $newSubModel->save();
        }
    }

}