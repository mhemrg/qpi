<?php
namespace Navac\Qpi\Support;

/**
 * Stack
 */
class Stack
{
  public $stack = [];

  public function push($item)
  {
    array_push($this->stack, $item);
    return $item;
  }

  public function pop()
  {
    return array_pop($this->stack);
  }

  public function isEmpty()
  {
    if(count($this->stack) > 0) {
      return false;
    }
    return true;
  }

  public function &getLastItem()
  {
    end($this->stack);
    return $this->stack[key($this->stack)];
  }

  public function stackLength()
  {
    return count($this->stack);
  }

  public function clean()
  {
    $this->stack = [];
  }
}
