<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of the routes that are handled
| by your application. Just tell Laravel the URIs it should respond
| to using a Closure or controller method. Build something great!
|
*/

Route::get('/', function () {
    return redirect()->away(env('APP_PANEL_URL'));
});

Route::group(['prefix' => 'maintenance'], function() {
	Route::get('logs', '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index');
});
