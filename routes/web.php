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
    return redirect('https://crimeisdown.com/');
});

Route::get('recordings/patreon-login.php', 'AudioArchiveController@patreonLogin')->name('patreon-login');

Route::get('recordings/download-audio.php', 'AudioArchiveController@download');

Route::get('recordings/generate-video.php', 'TranscodingController@generateVideoForm');
Route::post('recordings/generate-video.php', 'TranscodingController@generateVideo');

Route::prefix('directives')->group(function () {
    Route::get('', 'DirectivesController@index');
    Route::get('diff/{commit}/directives/data/{uuid}.html', 'DirectivesController@show')->name('directives.diff');
});
