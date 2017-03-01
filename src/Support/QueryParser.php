<?php
namespace Navac\Qpi\Support;

use Navac\Qpi\Support\ParserSyntaxException;

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

  /**
   * States
   * @var array
   */
  protected $states = [];

  /**
   * Some information to debug query
   * @var array
   */
  protected $debug = [
    'row' => 1,
    'col' => 0
  ];

  public $source;

  /**
   * A function which will called when an exception happen
   * @var function
   */
  public $errorHandler;

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

    try {
      if($this->matchToken()) {
        $this->states[$this->curState]['handler']($token);
      }
    } catch (ParserSyntaxException $e) {
      throw new ParserSyntaxException($e->getMessage(), $this->debug);
    }
  }

  /**
   * If token matches a breaker, this method will change current state to breaker target state
   * @return bool
   */
  protected function matchBreakers()
  {
    foreach ($this->states[$this->curState]['breakers'] as $breaker) {
      if($breaker->matchToken($this->token)) {
        $this->debug['col']++;
        $this->curState = $breaker->findCurState($this->states);;
        return true;
      }
    }
    return false;
  }

  protected function matchToken()
  {
    $this->debug['col']++;

    $pattern = $this->getStatePattern($this->curState);

    // Is token matches current state?
    if(preg_match($pattern, $this->token)) {
      return true;
    }

    if(preg_match('/\n/', $this->token)) {
      $this->debug['row']++;
      $this->debug['col'] = 0;
      return false;
    }

    if(preg_match('/\s/', $this->token)) {
      return false;
    }

    throw new ParserSyntaxException("Unexpected token: {$this->token}");
  }

  protected function getStatePattern($state) {
    if(!array_key_exists((int) $state, $this->states)) {
      return false;
    }

    return $this->states[$state]['match'];
  }

  public function addState($state)
  {
    $this->states[] = $state;
    return $this;
  }

  public function setCurrentState($state)
  {
    $this->curState = $state;
    return $this;
  }
}
