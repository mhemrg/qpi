<?php
namespace Navac\Qpi\Syntax\Util;

use Navac\Qpi\QueryCtrl;
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
    public function mapRelationFields($className, $fields)
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
            $query = $query->groupBy(...$cols);
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
        $relationClassName = get_class($row->$relationName()->getRelated());

        $query = $row->$relationName();
        $query = $this->_addLimits($query, $relation);

        $relationCols = $this->mapRelationFields(
            $relationClassName,
            $relation->filterModelCols()
        );

        $row[$relationName] = $query->select($relationCols)->get();
        $row[$relationName]->each(function ($record) {
            $record->setHidden(['pivot']);
        });

        return $this->_fetchRelations(
            $row[$relationName],
            $relation->filterRelations()
        );
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
     * @return mixed $query
     */
    protected function _addWhereClause($clauses, $query)
    {
        foreach ($clauses as $clause) {
            $method = $clause->boolean === '|' ? 'orWhere' : 'where';

            if($clause->type === 'Nested') {
                $query = $query->$method(function($q) use($clause) {
                    return $this->_addWhereClause($clause->query, $q);
                });
            }
            else {
                $query = $query->$method(
                    $clause->col, $clause->operator, $clause->val
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
    public function setVisibleCols($rows)
    {
        return $rows->map(function ($row) {
            return $row->setVisible(array_merge(
                $this->filterModelCols(),

                // Map through relations and get their name as a field
                array_map(function ($field) {
                    return $field->_name;
                }, $this->filterRelations())
            ));
        });
    }

    public function fetch()
    {
        if( ! $this->_isResourceExists()) {
            return $this->_respondError("Resource doesn't exists.");
        }

        $this->_makeInstance();


        // try {
            // $this->_modelInstance->qpiAccess();
            $query = $this->_addWhereClause($this->getWhereStats(), $this->_modelInstance);
            $query = $this->_addLimits($query, $this);
            $query = $this->_addGroupBy($query);
            $query = $this->_addOrderBy($query);

            $rows = $query->get();

            $rows = $this->_fetchRelations(
                $rows,
                $this->filterRelations()
            );

            $rows = $this->setVisibleCols($rows);

            return $this->_respondeOk($rows);

        // } catch (\Exception $e) {
        //     return $this->_respondError($e->getMessage());
        // }

    }
}
