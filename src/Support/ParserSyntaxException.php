<?php
namespace Navac\Qpi\Support;

/**
 * Parser Syntax Exception
 */
class ParserSyntaxException extends \Exception
{
  public $debug;

  public function __construct($message = "", $debug = null)
  {
    $this->debug = $debug;
    parent::__construct($message);
  }
}
