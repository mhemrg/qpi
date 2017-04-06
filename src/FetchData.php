<?php
namespace Navac\Qpi;

/**
 * Fetches data from db based on passed query
 */
class FetchData
{
  private $output;
  private $userModels;
  private $rowsCount = 0;

  public function __construct($models, $user_models)
  {
    $this->userModels = $user_models;

    $this->output = [];
    foreach ($models as $model) {
      array_push(
        $this->output,
        $this->fetchModel($model)
      );
    }
  }

  public function getOutput()
  {
    return $this->output;
  }

  public function fetchModel($model)
  {
    $rows = $this->fetchRows($model);

    if($rows['error']) {
      return $rows;
    }
    return [
      'error' => false,
      'count' => $this->rowsCount,
      'data' => $this->fetchRelations($rows['data'], $model['relations'])
    ];
  }

  public function fetchRows($model)
  {
    $Model = new $this->userModels[$model['model']];

    try {
      if(method_exists($Model, 'qpiAccess')) {
        $Model->qpiAccess();
      }

    } catch (\Exception $e) {
      return ['error' => true, 'data' => ['message' => $e->getMessage()]];
    }

    $Model = $this->addWhereClause($Model, $model['where']);
    $Model = $this->addQueryDetails($Model, $model);

    try {
      $data = $Model->get()->map(function($row) use($model) {
        $row->setVisible($this->getFields($model));
        return $row;
      });
    } catch (\Exception $e) {
      return ['error' => true, 'data' => ['message' => $e->getMessage()]];
    }

    return ['error' => false, 'data' => $data];
  }

  public function addWhereClause($Model, $clauses)
  {
    foreach ($clauses as $clause) {
      if($clause['boolean'] === 'and') {
        $Model = $Model->where($clause['column'], $clause['operator'], $clause['value']);

      } else {
        $Model = $Model->orWhere($clause['column'], $clause['operator'], $clause['value']);
      }
    }

    $this->rowsCount = $Model->count();

    return $Model;
  }

  public function addQueryDetails($Model, $model)
  {
    if(array_key_exists('offset', $model)) {
      $offset = empty($model['offset']) ? 0 : $model['offset'];
      $Model = $Model->offset($offset);
    }

    if(array_key_exists('limit', $model)) {
      $limit = empty($model['limit']) ? 10 : $model['limit'];
      $Model = $Model->limit($limit);
    }

    if(array_key_exists('orderBy', $model)) {
      foreach ($model['orderBy'] as $col => $dir) {
        $dir = $dir === 1 ? 'ASC' : 'DESC';
        $Model = $Model->orderBy($col, $dir);
      }
    }

    if(array_key_exists('groupBy', $model)) {
      $Model = $Model->groupBy(...$model['groupBy']);
    }

    return $Model;
  }

  public function getFields($model)
  {
    return array_merge(
      $model['fields'],
      array_map(function($relation) {
        return $relation['model'];
      }, $model['relations'])
    );
  }

  public function fetchRelations($rows, $relations)
  {
    if(!$relations) { return $rows; }

    foreach ($relations as $relation) {
      foreach ($rows as $row) {
        $this->fetchRelation($row, $relation);
      }
    }

    return $rows;
  }

  public function fetchRelation($row, $relation)
  {
    $relationName = $relation['model'];

    $row[$relationName] = $row->$relationName()->get();
    $row[$relationName]->each(function($i) use($relation) {
      $i->setVisible($this->getFields($relation));
    });

    return $this->fetchRelations($row[$relationName], $relation['relations']);
  }
}
