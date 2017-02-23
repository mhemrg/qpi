<?php
namespace Navac\Qpi\Support;

/**
 * Breaker
 */
class Breaker
{
  public $match;
  public $handler;

  public function __construct($match = null, $handler = null)
  {
    $this->match = $match;
    $this->handler = $handler;
  }

  public function matchToken($token)
  {
    if(preg_match($this->match, $token)) {
      return true;
    }
    return false;
  }

  public function findCurState($states)
  {
    $targetState = call_user_func($this->handler);
    return array_search($targetState, array_column($states, 'name'));
  }
}
