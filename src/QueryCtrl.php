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
    // remove all whitespaces
    $query = preg_replace('/[\s|\n]/', '', $query);
    return $this->parser($query);
  }

  public function parser($source)
  {
    $models = [];
    $model_tmp = '';
    Parser::newState('DetectingModel', '/^[A-Za-z0-9]/', [
      Parser::newBreaker('/^\{/', function () use (&$model_tmp, &$models) {
        $model = (object) [
          'model' => $model_tmp,
          'fields' => []
        ];

        array_push($models, $model);
        $model_tmp = '';
        return 'Fields';
      })
    ], function ($token) use (&$model_tmp) { $model_tmp .= $token; });

    $FieldsStack = new Stack;
    $field_tmp = '';
    $addNewField = function () use (&$field_tmp, &$FieldsStack) {
      $fields = &$FieldsStack->getLastItem();
      if(!empty($field_tmp) && !array_key_exists($field_tmp, $fields)) {
        $fields[$field_tmp] = '';
        $field_tmp = '';
      }
      return 'Fields';
    };
    Parser::newState('Fields', '/^[A-Za-z0-9_]/', [
      Parser::newBreaker('/^\,/', $addNewField),
      Parser::newBreaker('/^\{/', function () use (&$addNewField, &$FieldsStack) {
        call_user_func($addNewField);
        $FieldsStack->push([]);
        return 'Fields';
      }),
      Parser::newBreaker('/^\}/', function () use(&$FieldsStack, &$models, &$addNewField) {
        call_user_func($addNewField);

        if($FieldsStack->stackLength() === 1) {
          $fields = &$FieldsStack->getLastItem();

          end($models);
          $models[key($models)]->fields = $fields;

          $FieldsStack->clean();

          return 'DetectingModel';
        }

        $poped_fields = $FieldsStack->pop();
        $fields = &$FieldsStack->getLastItem();

        end($fields);
        $fields[key($fields)] = $poped_fields;
        return 'Fields';
      })
    ], function ($token) use (&$field_tmp, &$FieldsStack) {
      if($FieldsStack->isEmpty()) {
        $FieldsStack->push([]);
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
