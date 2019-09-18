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
    public function checkLivestreamHealth()
    {
        $client = new Client([
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
