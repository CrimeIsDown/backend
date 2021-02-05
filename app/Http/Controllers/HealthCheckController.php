<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\{ConnectException, ServerException};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class HealthCheckController extends Controller
{
    /**
     * Check if YouTube stream is online
     *
     * @return \Illuminate\Http\Response
     */
    public function checkLivestreamHealth(Client $client)
    {
        $response = $client->request('GET', 'https://www.youtube.com/embed/live_stream', [
            'query' => [
                'channel' => Config::get('custom.youtube.channel_id')
            ]
        ]);

        if ($response->getStatusCode() !== 200) {
            abort($response->getStatusCode(), 'Could not get video ID');
        }

        $html = $response->getBody()->getContents();
        $matches = [];
        if (preg_match('/<link rel="canonical" href="https:\/\/www.youtube.com\/watch\?v=(.*)">/', $html, $matches) !== 1) {
            abort(400, 'Could not parse video ID');
        }

        $videoId = $matches[1];

        $response = $client->request('GET', 'https://www.googleapis.com/youtube/v3/videos', [
            'http_errors' => false,
            'headers' => [
                'referer' => 'https://explorer.apis.google.com',
                'x-referer' => 'https://explorer.apis.google.com',
            ],
            'query' => [
                'part' => 'snippet',
                'id' => $videoId,
                'key' => Config::get('custom.youtube.api_key')
            ]
        ]);

        if ($response->getStatusCode() !== 200) {
            if ($response->getStatusCode() === 403) {
                return response('Skipped check, hit API quota', 200);
            }
            abort($response->getStatusCode(), 'Heartbeat request failed');
        }

        $apiResponse = json_decode($response->getBody()->getContents());
        try {
            $status = $apiResponse->items[0]->snippet->liveBroadcastContent;
            if ($status !== 'live') throw new \Exception();
            return response('Livestream online', $response->getStatusCode());
        } catch (\Exception $e) {
            return response('No live streams found', 404);
        }
    }

    /**
     * Check if there have been recent OpenMHz calls uploaded
     *
     * @param Request $request
     * @param string $systemName  Short system name (in OpenMHz URL)
     * @return \Illuminate\Http\Response
     */
    public function checkOpenmhz(Request $request, string $systemName)
    {
        $client = new Client([
            'base_uri' => 'https://api.openmhz.com/',
            'timeout' => 5,
        ]);

        try {
            $response = $client->request('GET', $systemName.'/calls');
        } catch (ConnectException | ServerException $e) {
            return response('Got server error, assuming OK', 200);
        }

        if ($response->getStatusCode() === 200) {
            $results = json_decode($response->getBody());
            $mostRecentCall = $results->calls[0];
            // Maximum tolerable time since the last upload, in minutes
            $maxHiatus = $request->input('max_allowed_age', 10);
            if (Carbon::now()->diffInMinutes(new Carbon($mostRecentCall->time)) > $maxHiatus) {
                return response('No recent calls found', 404);
            }
        }

        return response($response->getReasonPhrase(), $response->getStatusCode());
    }
}
