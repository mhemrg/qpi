<?php
namespace Navac\Qpi\Support;

/**
 * Breaker
 */
class Breaker
{
  public $match;
  public $handler;

  protected $token;

  public function __construct($match = null, $handler = null)
  {
    $this->match = $match;
    $this->handler = $handler;
  }

  public function matchToken($token)
  {
    $this->token = $token;
    if(preg_match($this->match, $token)) {
      return true;
    }
    return false;
  }

  public function findCurState($states)
  {
    $targetState = call_user_func_array($this->handler, [$this->token]);
    return array_search($targetState, array_column($states, 'name'));
  }
}
