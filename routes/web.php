<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('recordings/download-audio.php', 'AudioArchiveController@download')->middleware(['throttle:50,1440']);

Route::prefix('directives')->group(function () {
    Route::get('diff/{commit}/directives/data/{uuid}.html', 'DirectivesController@show');
    Route::get('diff_list.json', function () {
        return response(Storage::get(Config::get('custom.directives.public_path').'/diff_list.json'), 200, ['Content-Type' => 'application/json']);
    });
});
