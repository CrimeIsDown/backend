<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('livestream-health', function () {
    $client = new \GuzzleHttp\Client([
        'base_uri' => 'https://www.googleapis.com/youtube/v3/',
    ]);

    $response = $client->request('GET', 'search', [
        'query' => [
            'part' => 'snippet',
            'channelId' => Config::get('custom.youtube.channel_id'),
            'type' => 'video',
            'eventType' => 'live',
            'key' => Config::get('custom.youtube.api_key')
        ]
    ]);

    if ($response->getStatusCode() === 200) {
        $results = json_decode($response->getBody());
        if (!count($results->items)) {
            return response('No live streams found', 404);
        }
    }

    return response($response->getReasonPhrase(), $response->getStatusCode());
});