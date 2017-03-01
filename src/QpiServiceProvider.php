<?php
namespace Navac\Qpi;
use Illuminate\Support\ServiceProvider;

class QpiServiceProvider extends ServiceProvider
{
  public function boot()
  {
    $this->publishes([
        __DIR__.'/config.php' => config_path('qpi.php'),
    ]);
  }

  public function register()
  {
    require_once __DIR__.'/routes.php';
    $this->app->make('Navac\Qpi\QueryCtrl');
  }
}
