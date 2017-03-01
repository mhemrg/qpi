<?php
namespace Navac\Qpi\Support;

use Navac\Qpi\Tree\Tree;
use Navac\Qpi\Support\Breaker;
use Navac\Qpi\Support\QueryParser;

/**
 * Parser
 */
class Parser
{
  public $QueryParser;

  public function __construct()
  {
    $this->QueryParser = new QueryParser();
  }

  public function setToken($token)
  {
    $this->QueryParser->setToken($token);
  }

  public function newState($name, $match, $breakers, $handler)
  {
    $this->QueryParser->addState([
      'name'     => $name,
      'match'    => $match,
      'handler'  => $handler,
      'breakers' => $breakers
    ]);
    return $this;
  }

  public function newBreaker($match, $handler)
  {
    return new Breaker($match, $handler);
  }

  public function setSource($source)
  {
    $this->QueryParser->source = $source;
    return $this;
  }
}
