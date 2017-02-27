<?php
namespace Navac\Qpi;
use Illuminate\Support\ServiceProvider;

class QpiServiceProvider extends ServiceProvider
{
  public function boot()
  {
  }

  public function register()
  {
    require_once __DIR__.'/routes.php';
    $this->app->make('Navac\Qpi\QueryCtrl');
  }
}
