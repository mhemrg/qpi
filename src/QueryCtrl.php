<?php
namespace Navac\Qpi;
require 'QueryParser.php';

use Navac\Qpi\QueryParser;
use App\Http\Controllers\Controller;


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
    $Parser = new Parser();

    $Parser->newState('DetectingModel', '/^[A-Za-z0-9]/', [
      $Parser->newBreaker('/^\{/', 'Fields', function () { $this->addModel(); })
    ], function ($token) {
      $this->model .= $token;
    });

    $field = '';
    $fields = [];
    $Parser->newState('Fields', '/^[A-Za-z0-9_]/', [
      $Parser->newBreaker('/^\,/', 'Fields', function () { $this->addField(); }),
      $Parser->newBreaker('/^\}/', 'EndOfModel', function () { $this->addField()->addFields(); }),
      $Parser->newBreaker('/^\{/', 'SubModel1', function () use (&$fields) {
        $fields = [];
        $this->addField();
      })
    ], function ($token) {
      $this->field .= $token;
    });

    $Parser->newState('SubModel1', '/^[A-Za-z0-9_]/', [
      $Parser->newBreaker('/^,/', 'SubModel1', function () use (&$fields, &$field) {
        if(!array_key_exists($field, $fields)) {
          $fields[$field] = '';
        }
        $field = '';
      }),
      $Parser->newBreaker('/^\}/', 'Fields', function () use (&$fields, &$field) {
        if(!array_key_exists($field, $fields) && !empty($field)) {
          $fields[$field] = '';
        }
        $field = '';

        end($this->fields);
        $this->fields[key($this->fields)] = $fields;
      }),
      $Parser->newBreaker('/^\{/', 'SubModel2', function () use (&$fields2, &$fields, &$field) {
        $fields2 = [];
        $fields[$field] = '';
      })
    ], function ($token) use (&$field) {
      $field .= $token;
    });

    $field2 = '';
    $fields2 = [];
    $Parser->newState('SubModel2', '/^[A-Za-z0-9_]/', [
      $Parser->newBreaker('/^,/', 'SubModel2', function () use (&$fields2, &$field2) {
        if(!array_key_exists($field2, $fields2)) {
          $fields2[$field2] = '';
        }
        $field2 = '';
      }),
      $Parser->newBreaker('/^\}/', 'SubModel1', function () use (&$fields, &$fields2, &$field2) {
        if(!array_key_exists($field2, $fields2) && !empty($field2)) {
          $fields2[$field2] = '';
        }
        $field2 = '';

        end($fields);
        $fields[key($fields)] = $fields2;
      }),
      $Parser->newBreaker('/^\{/', 'SubModel3', function () use (&$fields3, &$fields2, &$field2) {
        $fields3 = [];
        $fields2[$field2] = '';
      })
    ], function ($token) use (&$field2) {
      $field2 .= $token;
    });

    $field3 = '';
    $fields3 = [];
    $Parser->newState('SubModel3', '/^[A-Za-z0-9_]/', [
      $Parser->newBreaker('/^,/', 'SubModel3', function () use (&$fields3, &$field3) {
        if(!array_key_exists($field3, $fields3)) {
          $fields3[$field3] = '';
        }
        $field3 = '';
      }),
      $Parser->newBreaker('/^\}/', 'SubModel2', function () use (&$fields2, &$fields3, &$field3) {
        if(!array_key_exists($field3, $fields2) && !empty($field3)) {
          $fields3[$field3] = '';
        }
        $field3 = '';

        end($fields2);
        $fields2[key($fields2)] = $fields3;
      })
    ], function ($token) use (&$field3) {
      $field3 .= $token;
    });

    $Parser->newState('EndOfModel', '', [
      $Parser->newBreaker('/^\,/', 'DetectingModel', function () { }),
    ], function ($token) {});

    $i = 0;
    while ($i < strlen($source)) {
      $Parser->setToken($source[$i]);
      $i++;
    }

    return $Parser->Tree->getTree();
  }
}
