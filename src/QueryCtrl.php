<?php
namespace Navac\Qpi;

use Navac\Qpi\Support\Stack;
use Navac\Qpi\Support\QueryParser;
use App\Http\Controllers\Controller;
use Navac\Qpi\Support\ParserSyntaxException;
use Navac\Qpi\Support\ParserFacade as Parser;


class QueryCtrl extends Controller
{
  protected $user_models;

  function index($query)
  {
    $config = include config_path('qpi.php');
    $this->user_models = $config['models'];
    return $this->parser($query);
  }

  public function parser($source)
  {
    Parser::setSource($source);

    $models = [];
    $model_tmp = '';
    $addWhere = function () use (&$where, &$where_item_tmp) {
      array_push($where, $where_item_tmp);
      end($where);
      $where[key($where)]['boolean'] = 'and';
      return 'Where';
    };
    $addOrWhere = function () use (&$where, &$where_item_tmp) {
      array_push($where, $where_item_tmp);
      end($where);
      $where[key($where)]['boolean'] = 'or';
      return 'Where';
    };
    Parser::newState('DetectingModel', '/^[A-Za-z0-9]/', [
      Parser::newBreaker('/^\{/', function () use (&$model_tmp, &$models) {
        $model = [
          'model' => $model_tmp,
          'fields' => [],
          'relations' => [],
          'where' => [],
          'orderBy' => [],
          'groupBy' => [],
          'limit' => '',
          'offset' => ''
        ];

        array_push($models, $model);
        $model_tmp = '';
        return 'Fields';
      }),
      Parser::newBreaker('/^\[/', $addWhere),
      Parser::newBreaker('/^\</', function () {
        return 'DetectingLimitAndOffset';
      })
    ], function ($token) use (&$model_tmp) { $model_tmp .= $token; });

    $fields = [];
    $field_tmp = '';
    $detectingGroupBy = false;
    $detectingOrderBy = false;
    $RelationsStack = new Stack;
    $expectValueForOrderBy = function () use(&$detectingOrderBy) {
      if($detectingOrderBy) {
        throw new ParserSyntaxException('Expects 1 or 0 for order by.');
      }
    };
    $addNewField = function () use (&$field_tmp, &$RelationsStack, $expectValueForOrderBy, &$detectingGroupBy) {
      $expectValueForOrderBy();

      $fields = &$RelationsStack->getLastItem()['fields'];
      $groupBy = &$RelationsStack->getLastItem()['groupBy'];
      if(!empty($field_tmp) && !array_key_exists($field_tmp, $fields)) {
        array_push($fields, $field_tmp);

        if($detectingGroupBy) {
          array_push($groupBy, $field_tmp);
        }

        $field_tmp = '';
      }
      return 'Fields';
    };
    Parser::newState('Fields', '/^[A-Za-z0-9_]/', [
      Parser::newBreaker('/^\-/', function () use(&$detectingGroupBy) {
        $detectingGroupBy = true;
        return 'Fields';
      }),
      Parser::newBreaker('/^\,/', $addNewField),
      Parser::newBreaker('/^\{/', function () use(&$addNewField, &$RelationsStack, &$field_tmp, &$relations) {
        $RelationsStack->push([
          'model' => $field_tmp,
          'fields' => [],
          'relations' => [],
          'where' => [],
          'orderBy' => [],
          'groupBy' => []
        ]);
        $field_tmp = '';
        return 'Fields';
      }),
      Parser::newBreaker('/^\}/', function () use(&$addNewField, &$models, &$RelationsStack, &$relations, &$fields, $expectValueForOrderBy) {
        call_user_func($addNewField);
        $expectValueForOrderBy();

        if($RelationsStack->stackLength() === 1) {
          $fields = &$RelationsStack->getLastItem()['fields'];
          $relations = &$RelationsStack->getLastItem()['relations'];
          $orderBy = &$RelationsStack->getLastItem()['orderBy'];
          $groupBy = &$RelationsStack->getLastItem()['groupBy'];

          end($models);
          $models[key($models)]['fields'] = $fields === null ? [] : $fields;
          $models[key($models)]['relations'] = $relations === null ? [] : $relations;
          $models[key($models)]['orderBy'] = $orderBy === null ? [] : $orderBy;
          $models[key($models)]['groupBy'] = $groupBy === null ? [] : $groupBy;

          $RelationsStack->clean();

          return 'DetectingModel';
        }

        $poped_relation = $RelationsStack->pop();
        $relations = &$RelationsStack->getLastItem()['relations'];
        array_push($relations, $poped_relation);

        return 'Fields';
      }),
      Parser::newBreaker('/^:/', function () use(&$field_tmp, &$RelationsStack, &$detectingOrderBy) {
        $orderBy = &$RelationsStack->getLastItem()['orderBy'];
        $orderBy[$field_tmp] = '';
        $detectingOrderBy = true;
        return 'Fields';
      })
    ], function ($token) use (&$field_tmp, &$RelationsStack, &$relations, &$detectingOrderBy) {
      if($detectingOrderBy) {
        $token = (int) $token;
        if($token < 0 || $token > 1) {
          throw new ParserSyntaxException("You can just pass 1 or 0 to order by.");
        }

        $orderBy = &$RelationsStack->getLastItem()['orderBy'];

        end($orderBy);
        $orderBy[key($orderBy)] = $token;

        $detectingOrderBy = false;
        return;
      }

      if($RelationsStack->isEmpty()) {
        $RelationsStack->push(['fields' => [], 'relations' => [], 'orderBy' => [], 'groupBy' => []]);
      }

      $field_tmp .= $token;
    });

    $where = [];
    $where_item_tmp = ['column' => '', 'operator' => '', 'value' => '', 'boolean' => ''];
    Parser::newState('Where', '/^[A-Za-z0-9_]/', [
      Parser::newBreaker('/^[=!>~<]/', function ($token) use (&$where) {
        switch ($token) {
          case '~':
            $token = 'like';
            break;
          case '!':
            $token = '<>';
            break;
          default:
            $token = $token;
            break;
        }

        end($where);
        $where[key($where)]['operator'] .= $token;
        return 'DetectingWhereValue';
      })
    ], function ($token) use (&$where) {
      end($where);
      $where[key($where)]['column'] .= $token;
    });

    Parser::newState('DetectingWhereValue', '/^[^\]|,]/', [
      Parser::newBreaker('/^\,/', $addWhere),
      Parser::newBreaker('/^\|/', $addOrWhere),
      Parser::newBreaker('/^\]/', function () use (&$models, &$where) {
        end($models);
        $models[key($models)]['where'] = $where;
        $where = [];
        return 'DetectingModel';
      }),
    ], function ($token) use (&$where) {
      end($where);
      $where[key($where)]['value'] .= $token;
    });

    $limit = false;
    Parser::newState('DetectingLimitAndOffset', '/^[0-9]/', [
      Parser::newBreaker('/^:/', function () use(&$limit) {
        $limit = true;
        return 'DetectingLimitAndOffset';
      }),
      Parser::newBreaker('/^>/', function () use(&$models) {
        end($models);
        $models[key($models)]['limit'] = (int) $models[key($models)]['limit'];
        $models[key($models)]['offset'] = (int) $models[key($models)]['offset'];

        return 'DetectingModel';
      })
    ], function ($token) use(&$limit, &$models) {
      end($models);

      if($limit) {
        $models[key($models)]['limit'] .= $token;
        return;
      }

      $models[key($models)]['offset'] .= $token;
    });


    $i = 0;
    while ($i < strlen($source)) {
      try {
        Parser::setToken($source[$i]);

      } catch (ParserSyntaxException $e) {
        return view('qpi::syntax_error', [
          'source' => $source,
          'row' => $e->debug['row'],
          'col' => $e->debug['col']
        ]);
      }

      $i++;
    }

    return $this->getData($models);
    // return $models;
  }

  protected function getData($models)
  {
    function getFields($model) {
      $model['fields'] = $model['fields'] === null ? [] : $model['fields'];
      $model['relations'] = $model['relations'] === null ? [] : $model['relations'];

      return array_merge(
        $model['fields'],
        array_map(function($relation) {
          return $relation['model'];
        }, $model['relations'])
      );
    }

    function addWhereClause($Model, $clauses) {
      foreach ($clauses as $clause) {
        if($clause['boolean'] === 'and') {
          $Model = $Model->where($clause['column'], $clause['operator'], $clause['value']);

        } else {
          $Model = $Model->orWhere($clause['column'], $clause['operator'], $clause['value']);
        }
      }

      return $Model;
    }

    function fetchRows($model, $userModels) {
      $Model = new $userModels[$model['model']];

      try {
        if(method_exists($Model, 'qpiAccess')) {
          $Model->qpiAccess();
        }

      } catch (\Exception $e) {
        return ['error' => true, 'data' => ['message' => $e->getMessage()]];
      }

      $Model = addWhereClause($Model, $model['where']);
      $data = $Model->get()->map(function($row) use($model) {
        $row->setVisible(getFields($model));
        return $row;
      });

      return ['error' => false, 'data' => $data];
    }

    function fetchRelations($rows, $relations) {
      if(!$relations) { return $rows; }

      foreach ($relations as $relation) {
        foreach ($rows as $row) {
          fetchRelation($row, $relation);
        }
      }

      return $rows;
    }

    function fetchRelation($row, $relation) {
      $relationName = $relation['model'];

      $row[$relationName] = $row->$relationName()->get();
      $row[$relationName]->each(function($i) use($relation) {
        $i->setVisible(getFields($relation));
      });

      return fetchRelations($row[$relationName], $relation['relations']);
    }

    function fetchModel($model, $userModels) {
      $rows = fetchRows($model, $userModels);

      if($rows['error']) {
        return $rows;
      }
      return [
        'error' => false,
        'data' => fetchRelations($rows['data'], $model['relations'])
      ];
    }

    $output = [];
    foreach ($models as $model) {
      array_push(
        $output,
        fetchModel($model, $this->user_models)
      );
    }

    return $output;
  }
}
