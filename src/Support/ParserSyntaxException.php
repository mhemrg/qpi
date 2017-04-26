<?php
namespace Navac\Qpi\Support;

/**
 * Parser Syntax Exception
 */
class ParserSyntaxException extends \Exception
{
  public $debug;

  public function __construct($message = "", $token = null)
  {
      $this->debug = [
          'row' => $token['line'],
          'col' => $token['offset'] + 1,
      ];
      parent::__construct($message);
  }
}
