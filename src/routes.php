<?php
Route::get('/query/{query}', 'Navac\Qpi\QueryCtrl@index');
Route::get('/schema/{output?}', 'Navac\Qpi\QueryCtrl@schema');
