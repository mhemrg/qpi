<?php
namespace Navac\Qpi;
use Illuminate\Support\ServiceProvider;

class QpiServiceProvider extends ServiceProvider
{
  public function boot()
  {
    //
  }

  public function register()
  {
    $this->app->make('Navac\Qpi\QueryCtrl');

    $this->app->get('/query/{query}', 'Navac\Qpi\QueryCtrl@index');
    $this->app->get('/schema/{output?}', 'Navac\Qpi\QueryCtrl@schema');
  }
}
