<?php
namespace Navac\Qpi\Support;

/**
 * Pipes fetched data through handlers
 */
class PipeLine
{
  /**
   * The data which will passed through handlers
   * @var mixed
   */
  private $data;

  /**
   * The hooks are an array of handlers to pipe data to them
   * @var array
   */
  private static $hooks = [];

  /**
   * Starts piping
   * @param  string $name
   * @param  mixed $data
   * @return mixed
   */
  public function start(string $name, $data) {
    if( ! array_key_exists($name, self::$hooks)) {
      return $data;
    }

    ksort(self::$hooks[$name]);

    foreach (self::$hooks[$name] as $priority => $handlers) {
      $data = $this->pipe($data)->through($handlers);
    }

    return $data;
  }

  /**
   * Sets the data
   * @param  mixed $data
   * @return Navac\Qpi\Support\PipeLine
   */
  private function pipe($data)
  {
    $this->data = $data;
    return $this;
  }

  /**
   * Pipes data to handlers
   * @param  array  $handlers
   * @return mixed
   */
  private function through(array $handlers)
  {
    $data = $this->data;

    foreach ($handlers as $handler) {
      $data = (new $handler)->handle($data);
    }

    return $data;
  }

  /**
   * Adds a new hook to specified hook-name and priority
   * @param  string  $name
   * @param  string  $handler
   * @param  integer $priority
   * @return
   */
  public static function hook(string $name, string $handler, int $priority = 0)
  {
    self::$hooks[$name][$priority][] = $handler;
  }
}
