<?php
namespace Navac\Qpi;

class QueryParser
{
  /**
   * Current state of parser
   * @var integer
   */
  protected $curState = 0;

  /**
   * Passed token
   * @var string
   */
  protected $token = '';

  protected $states = [];

  /**
   * Set token and run handler callback
   * @param string $token
   */
  public function setToken($token)
  {
    $this->token = $token;

    if($this->matchBreakers()) {
      return;
    }

    if($this->matchToken()) {
      $this->states[$this->curState]['handler']($token);
    }
  }

  protected function matchBreakers()
  {
    foreach ($this->states[$this->curState]['breakers'] as $breaker) {
      if($breaker->matchToken($this->token)) {
        $this->curState = $breaker->findCurState($this->states);;
        return true;
      }
    }
    return false;
  }

  protected function matchToken()
  {
    $pattern = $this->getStatePattern($this->curState);

    // Is token matches current state?
    if(preg_match($pattern, $this->token)) {
      return true;
    }

    // Is token matches next state?
    // if matches, so change the current state.
    $pattern = $this->getStatePattern($this->curState + 1);
    if($pattern && preg_match($pattern, $this->token)) {
      $this->curState++;
      return true;
    }

    throw new \Exception("Unexpected token: {$this->token}");
  }

  protected function getStatePattern($state) {
    if(!array_key_exists((int) $state, $this->states)) {
      return false;
    }

    return $this->states[$state]['match'];
  }

  public function addState($State)
  {
    $this->states[] = $State->getState();
    return $this;
  }

  public function setCurrentState($state)
  {
    $this->curState = $state;
    return $this;
  }
}

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

/**
 *
 */
class Tree
{
  protected $tree = [];

  // temporary variables
  public $model = '';
  public $field = '';
  public $fields = [];

  /**
   * Adds model to tree
   */
  public function addModel()
  {
    $this->tree[] = [
      'model' => $this->model
    ];

    // reset temporary variables
    $this->model  = '';
    $this->field  = '';
    $this->fields = [];

    return $this;
  }

  /**
   * Adds a field
   */
  public function addField()
  {
    if(!empty($this->field)) {
      $this->fields[$this->field] = '';
    }

    $this->field  = '';

    return $this;
  }

  /**
   * Adds fields to last added model
   */
  public function addFields()
  {
    $this->tree[count($this->tree) - 1]['fields'] = $this->fields;
    return $this;
  }

  public function getTree()
  {
    return $this->tree;
  }
}

/**
 * Breaker
 */
class Breaker
{
  public $match;
  public $targetState;
  public $handler;

  public function __construct($match = null, $targetState = null, $handler = null)
  {
    $this->match = $match;
    $this->targetState = $targetState;
    $this->handler = $handler;
  }

  public function matchToken($token)
  {
    if(preg_match($this->match, $token)) {
      call_user_func($this->handler);
      return true;
    }
    return false;
  }

  public function findCurState($states)
  {
    return array_search($this->targetState, array_column($states, 'name'));
  }
}

/**
 * Parser Fecade
 */
class Parser
{
  public $Tree;
  public $QueryParser;

  public function __construct()
  {
    $this->Tree = new Tree;
    $this->QueryParser = new QueryParser();
  }

  public function setToken($token)
  {
    $this->QueryParser->setToken($token);
  }

  public function newState($name, $match, $breakers, $handler)
  {
    $State = new State($name, $match, ($handler)->bindTo($this->Tree));
    $State->breakers = $breakers;
    $this->QueryParser->addState($State);
    return $this;
  }

  public function newBreaker($match, $targetState, $handler)
  {
    return new Breaker($match, $targetState, ($handler)->bindTo($this->Tree));
  }
}
