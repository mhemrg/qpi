<?php
namespace Navac\Qpi;

use Navac\Qpi\Support\Stack;
use Navac\Qpi\Support\QueryParser;
use App\Http\Controllers\Controller;
use Facades\Navac\Qpi\Support\Parser;


class QueryCtrl extends Controller
{
  public $tree = [];

  function index($query)
  {
    return $this->parser($query);
  }

  public function parser($source)
  {
    Parser::setSource($source);

    $models = [];
    $model_tmp = '';
    Parser::newState('DetectingModel', '/^[A-Za-z0-9]/', [
      Parser::newBreaker('/^\{/', function () use (&$model_tmp, &$models) {
        $model = (object) [
          'model' => $model_tmp,
          'fields' => [],
          'relations' => []
        ];

        array_push($models, $model);
        $model_tmp = '';
        return 'Fields';
      })
    ], function ($token) use (&$model_tmp) { $model_tmp .= $token; });

    $fields = [];
    $field_tmp = '';
    $RelationsStack = new Stack;
    $addNewField = function () use (&$field_tmp, &$RelationsStack) {
      $fields = &$RelationsStack->getLastItem()['fields'];
      if(!empty($field_tmp) && !array_key_exists($field_tmp, $fields)) {
        array_push($fields, $field_tmp);
        $field_tmp = '';
      }
      return 'Fields';
    };
    Parser::newState('Fields', '/^[A-Za-z0-9_]/', [
      Parser::newBreaker('/^\,/', $addNewField),
      Parser::newBreaker('/^\{/', function () use(&$addNewField, &$RelationsStack, &$field_tmp, &$relations) {
        $RelationsStack->push(['model' => $field_tmp, 'fields' => [], 'relations' => []]);
        $field_tmp = '';
        return 'Fields';
      }),
      Parser::newBreaker('/^\}/', function () use(&$addNewField, &$models, &$RelationsStack, &$relations, &$fields) {
        call_user_func($addNewField);

        if($RelationsStack->stackLength() === 1) {
          $fields = &$RelationsStack->getLastItem()['fields'];
          $relations = &$RelationsStack->getLastItem()['relations'];

          end($models);
          $models[key($models)]->fields = $fields;
          $models[key($models)]->relations = $relations;

          $RelationsStack->clean();

          return 'DetectingModel';
        }

        $poped_relation = $RelationsStack->pop();
        $relations = &$RelationsStack->getLastItem()['relations'];
        array_push($relations, $poped_relation);
        
        return 'Fields';
      })
    ], function ($token) use (&$field_tmp, &$RelationsStack, &$relations) {
      if($RelationsStack->isEmpty()) {
        $RelationsStack->push(['fields' => [], 'relations' => []]);
      }

      $field_tmp .= $token;
    });

    $i = 0;
    while ($i < strlen($source)) {
      Parser::setToken($source[$i]);
      $i++;
    }

    return $models;
  }
}
