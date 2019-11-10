<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use GuzzleHttp\Client;
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

        $response = $client->request('GET', 'https://www.youtube.com/heartbeat', [
            'query' => [
                'video_id' => $videoId,
                'c' => 'WEB',
                'cver' => '2.20191108.05.00',
                'sequence_number' => 0,
            ],
            'headers' => [
                'x-youtube-client-name' => '1',
                'x-youtube-client-version' => '2.20191108.05.00',
                'x-youtube-page-label' => 'youtube.ytfe.desktop_20191107_5_RC0',
            ]
        ]);

        if ($response->getStatusCode() !== 200) {
            abort($response->getStatusCode(), 'Heartbeat request failed');
        }
        $apiResponse = json_decode($response->getBody()->getContents());

        if ($apiResponse->status !== 'ok') {
            return response('No live streams found', 404);
        }

        return response('Livestream online', $response->getStatusCode());
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
        ]);

        $response = $client->request('GET', $systemName.'/calls');

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
