<?php
namespace Navac\Qpi\Support;

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

  /**
   * If token matches a breaker, this method will change current state to breaker target state
   * @return bool
   */
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

    // throw new \Exception("Unexpected token: {$this->token}");
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
