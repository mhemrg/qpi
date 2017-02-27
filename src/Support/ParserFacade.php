<?php
namespace Navac\Qpi\Support;

/**
 * Parser Fecade
 */
class ParserFacade
{
  protected static $ins;

  public static function __callStatic($method, $args)
  {
    if(empty(self::$ins)) {
      self::$ins = new Parser;
    }

    return self::$ins->$method(...$args);
  }
}
