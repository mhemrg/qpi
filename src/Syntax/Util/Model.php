<?php
namespace Navac\Qpi\Syntax\Util;

use Navac\Qpi\QueryCtrl;
use Navac\Qpi\Support\PipeLine;
use Navac\Qpi\Syntax\Util\Field;

class Model
{
    protected $_name;
    protected $_fields = [];

    protected $_offset = 0;
    protected $_limit  = 10;
    protected $_whereStats = [];

    protected $_modelInstance;

    /**
     * Is there any aggregate function on this model?
     * @var  The name of the aggregate function
     */
    public $aggregate;

    /**
     * Is this field must be in group by list?
     *
     * @note This property always is false for models.
     * @var bool
     */
    public $isInGroupBy = false;

    public function __construct($name)
    {
        $this->_name = $name;
    }

    public function setFields(array $fields = [])
    {
        $this->_fields = $fields;
        return $this;
    }

    public function setLimits($offset, $limit)
    {
        $this->_offset = $offset;
        $this->_limit = $limit;
    }

    public function setWhereStats($stats)
    {
        $this->_whereStats = $stats;
    }

    public function getName() : string
    {
        return $this->_name;
    }

    public function getFields() : array
    {
        return $this->_fields;
    }

    public function getWhereStats() : array
    {
        return $this->_whereStats;
    }

    public function filterRelations() : array
    {
        return array_filter($this->getFields(), function ($field) {
            return $field instanceof Model;
        });
    }

    public function filterModelCols() : array
    {
        $fields = array_filter($this->getFields(), function ($field) {
            return $field instanceof Field;
        });

        return array_map(function ($field) {
            return $field->name;
        }, $fields);
    }

    protected function _isResourceExists() : bool
    {
        return array_key_exists($this->getName(), QueryCtrl::$userModels);
    }

    protected function _respondError($msg='')
    {
        return [
            'ok' => false,
            'message' => $msg
        ];
    }

    protected function _getClassName()
    {
        return QueryCtrl::$userModels[$this->getName()];
    }

    protected function _respondeOk($result)
    {
        return [
            'ok' => true,
            'count' => count($result),
            'result' => $result
        ];
    }

    protected function _makeInstance()
    {
        $ClassName = $this->_getClassName();
        $this->_modelInstance = new $ClassName;
    }

    protected function _fetchRelations($rows, $relations)
    {
        if( ! $relations) {
            return $rows;
        }

        foreach ($relations as $relation) {
            foreach ($rows as $row) {
              $this->_fetchRelation($row, $relation);
            }
        }

        return $rows;
    }

    /**
     * Maps through relation's fields and appends related class name before
     * each field.
     *
     * @example We have a relation to tags table and we want to select id:
     *          id => tags.id
     *
     * @param  string $className
     * @param  array $fields
     * @return array
     */
    public function addTableNameAsPrefix($className, $fields)
    {
        $relationTableName = (new $className)->getTable();

        return array_map(function ($field) use ($relationTableName) {
            return "{$relationTableName}.{$field}";
        }, $fields);
    }

    /**
     * Filter fields to get group-by columns and set groupBy in query
     *
     * @return mixed
     */
    protected function _addGroupBy($query)
    {
        $cols = array_filter($this->getFields(), function ($field) {
            return $field->isInGroupBy;
        });

        if(count($cols) > 0) {
            $query = $query->groupBy($cols);
        }

        return $query;
    }

    protected function _addOrderBy($query)
    {
        $orderBy = [];

        foreach ($this->getFields() as $field) {
            if($field instanceof Field && $field->isInOrderBy !== false) {
                array_push($orderBy, [
                    'col' => $field->name,
                    'dir' => $field->isInOrderBy
                ]);
            }
        }

        foreach ($orderBy as $item) {
            $query = $query->orderBy($item['col'], $item['dir']);
        }

        return $query;
    }

    public function _fetchRelation($row, $relation)
    {
        $relationName = $relation->getName();
        $relationClass = $row->$relationName()->getRelated();
        $relationClassName = get_class($relationClass);
        $relationTableName = $relationClass->getTable();

        $query = $row->$relationName();
        $query = $query->where(function ($query) use ($relation, $relationTableName) {
            return $relation->_addWhereClause($query, $relationTableName);
        });

        $query = $this->_addLimits($query, $relation);
        $query = $relation->_addGroupBy($query);
        $query = $relation->_addOrderBy($query);

        $relationCols = $relation->filterModelCols();

        $rows = $this->_sendToPipeLine(
            $this->_getHookByModelName($relationClassName),
            $query->get()
        );

        $rows = $this->setVisibleCols($rows, $relation);

        if( ! is_null($relation->aggregate)) {
            $aggregate = $relation->aggregate;
            $rows = $rows->$aggregate($relationCols[0]);
        }

        $row[$relationName] = $this->_respondeOk($rows);

        return $this->_fetchRelations(
            $row[$relationName]['result'],
            $relation->filterRelations()
        );
    }

    /**
     * Finds handler in hooks list and returns it's hook-name
     *
     * @param  string $modelClassName
     * @return string
     */
    protected function _getHookByModelName($modelClassName)
    {
      return array_search($modelClassName, QueryCtrl::$userModels);
    }

    /**
     * Add where clauses to query
     *
     * We have a tree of where clauses whitch they are in two types:
     *  1) Basic
     *  2) Nested
     * So it first, detects whitch boolean (where or orWhere) should use
     * and after that makes nested and basic queries.
     *
     * @param mixed $query
     * @param mixed $prefix A prefix to add before the fields, it must be
     *                      the name of the table.
     * @return mixed $query
     */
    protected function _addWhereClause($query, $prefix='')
    {
        $prefix = $prefix . '.';
        foreach ($this->getWhereStats() as $clause) {
            $method = $clause->boolean === '|' ? 'orWhere' : 'where';

            if($clause->type === 'Nested') {
                $query = $query->$method(function($q) use($clause) {
                    return $this->_addWhereClause($clause->query, $q);
                });
            }
            else {
                $query = $query->$method(
                    $prefix.$clause->col, $clause->operator, $clause->val
                );
            }
        }

        return $query;
    }

    protected function _addLimits($ins, $model)
    {
        $ins = $ins->offset($model->_offset);
        $ins = $ins->limit($model->_limit);
        return $ins;
    }

    /**
     * Whitch columns should be visible?
     *
     */
    public function setVisibleCols($rows, $model)
    {
        return $rows->map(function ($row) use($model) {
            return $row->setVisible(array_merge(
                $model->filterModelCols(),

                // Map through relations and get their name as a field
                array_map(function ($field) {
                    return $field->_name;
                }, $model->filterRelations())
            ));
        });
    }

    /**
     * Send rows to pipeline whitch it will call all the hooks of this model
     * and allows them to modify rows
     *
     * @param  string $modelName
     * @param  collection $rows
     * @return collection Modified rows
     */
    protected function _sendToPipeLine($modelName, $rows)
    {
        $pipeline = new PipeLine;
        return $pipeline->start($modelName, $rows);
    }

    public function fetch()
    {
        if( ! $this->_isResourceExists()) {
            return $this->_respondError("Resource doesn't exists.");
        }

        $this->_makeInstance();


        try {
            // $this->_modelInstance->qpiAccess();

            $query = $this->_modelInstance;
            $query = $this->_addWhereClause($query);
            $query = $this->_addLimits($query, $this);
            $query = $this->_addGroupBy($query);
            $query = $this->_addOrderBy($query);

            $rows = $this->_sendToPipeLine($this->getName(), $query->get());

            $rows = $this->_fetchRelations(
                $rows,
                $this->filterRelations()
            );

            $rows = $this->setVisibleCols($rows, $this);

            if( ! is_null($this->aggregate)) {
                $aggregate = $this->aggregate;
                $rows = $rows->$aggregate($this->getFields()[0]);
            }

            return $this->_respondeOk($rows);
        }
        catch (\BadMethodCallException $e) {
            return $this->_respondError($e->getMessage());
        }
        catch (\Exception $e) {
            return $this->_respondError($e->getMessage());
        }
    }
}
