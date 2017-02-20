<?php
namespace Navac\Qpi;
use Illuminate\Support\ServiceProvider;

class QpiServiceProvider extends ServiceProvider
{
  public function boot()
  {
    $this->loadRoutesFrom(__DIR__.'/routes.php');
  }

  public function register()
  {
    $this->app->make('Navac\Qpi\QueryCtrl');
  }
}
