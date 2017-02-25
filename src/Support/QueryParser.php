<?php
namespace Navac\Qpi\Support;

use Navac\Qpi\Support\ParserException;

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
    } catch (\Exception $e) {
      $row = $this->debug['row'];
      $col = $this->debug['col'];
      $source = explode("\n", $this->source);

      $output = [];
      for ($i=0; $i < count($source); $i++) {
        $line = $source[$i];

        if($i === $row - 1) {
          $line = "<div style='background: #fb2929; color: white'>{$line}</div>";

          $helper = '';
          for ($j=0; $j < $col - 1; $j++) {
            $helper .= " ";
          }
          $helper .= '^';

          $line .= "<div style='background: #fff1ac'>{$helper}</div>";
        }

        array_push($output, $line);
      }
      $output = implode("\n", $output);

      exit(trim("
      <p>
      Line: {$row} | Column: {$col}
      <br />
      {$e->getMessage()}
      </p>
<hr />
<pre>
{$output}
</pre>
"));
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
