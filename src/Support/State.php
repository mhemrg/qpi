<?php
namespace Navac\Qpi\Support;

/**
 * State
 */
class State
{
  public $name;
  public $match;
  public $handler;
  public $breakers;

  function __construct($name = null, $match = null, $handler = null)
  {
    $this->name = $name;
    $this->match = $match;
    $this->handler = $handler;
  }

  public function getState()
  {
    return [
      'name'     => $this->name,
      'match'    => $this->match,
      'handler'  => $this->handler,
      'breakers' => $this->breakers
    ];
  }
}
