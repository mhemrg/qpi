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

    $Parser->newState('Fields', '/^[A-Za-z0-9_]/', [
      $Parser->newBreaker('/^\,/', 'Fields', function () { $this->addField(); }),
      $Parser->newBreaker('/^\}/', 'EndOfModel', function () { $this->addField()->addFields(); })
    ], function ($token) {
      $this->field .= $token;
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
