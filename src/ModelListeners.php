<?php
namespace Navac\Qpi;

/**
 * Handles listeners that are attached to a model
 */
class ModelListeners
{
  private static $userListeners;

  public static function trigger($modelName, $data)
  {
    if( ! self::$userListeners) {
      $config = include config_path('qpi.php');
      self::$userListeners = $config['listeners'];
    }

    foreach (self::$userListeners[$modelName] as $listener) {
      $listener = new $listener;
      $data = $listener->handle($data);
    }

    return $data;
  }

}
