<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AudioArchiveController extends Controller
{
    /**
     * Download an audio archive
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function download(Request $request)
    {
        $tiers = [
            'https://www.patreon.com/posts/login-to-get-8672312' => [
                'hash' => hash('sha256', '$1/mo patron'),
                'limit' => 10,
            ],
            'https://www.patreon.com/posts/login-to-get-8672332' => [
                'hash' => hash('sha256', '$5/mo patron'),
                'limit' => 20,
            ],
            'https://www.patreon.com/posts/login-to-get-8672339' => [
                'hash' => hash('sha256', '$10/mo patron'),
                'limit' => 30,
            ],
            'https://www.patreon.com/posts/login-to-get-8672341' => [
                'hash' => hash('sha256', '$20/mo patron'),
                'limit' => 50,
            ],
            'https://www.patreon.com/posts/login-to-get-8672344' => [
                'hash' => hash('sha256', '$50/mo patron'),
                'limit' => 1000,
            ],
        ];

        if ($request->get('patreon_auth')) {
            $referrer = $request->header('HTTP_REFERER');
            $response = response()->redirectTo('https://crimeisdown.com/audio?patreon_auth=1');
            if ($referrer && isset($tiers[$referrer])) {
                $response = $response->cookie('tier', $tiers[$referrer]['hash'], Carbon::now()->addMonth()->diffInMinutes(Carbon::now()));
            }
            return $response;
        }

        $limit = 5;

        if ($request->cookie('tier')) {
            foreach ($tiers as $tier) {
                if ($request->cookie('tier') === $tier['hash']) {
                    $limit = $tier['limit'];
                    break;
                }
            }
        }

        try {
            $this->validate($request, [
                'feed'     => 'required|in:zone1,zone2,zone3,zone4,zone5,zone6,zone7,zone8,zone9,zone10,zone11,zone12,zone13,citywide1,citywide2,citywide6',
                'datetime' => 'required|date'
            ]);
        } catch (ValidationException $e) {
            return response('Make sure you have selected a valid feed, and that your datetime is in the format '.Carbon::now()->minute(0)->second(0)->timezone('America/Chicago')->toDateTimeString(), 400);
        }

        $file = $request->get('feed').'_'.date('Ymd_His', strtotime($request->get('datetime'))).'.aac';

        preg_match('/(.*?)_([0-9]{4})([0-9]{2})([0-9]{2})_([0-9]{2})([0-9]{2})([0-9]{2})\.aac/', $file, $matches);
        $path = "$matches[2]/$matches[3]/$matches[4]/$matches[5]";
        $filename = "$matches[1]_$matches[2]$matches[3]$matches[4]_$matches[5]0000.aac";

        // check if it exists in the temp bucket
        if (!Storage::disk('recordings-temp')->exists($filename)) {
            // if it does not, download from main bucket, decompress, upload to temp bucket
            if (!Storage::disk('recordings')->exists("$path/$filename.xz")) {
                return response('Error: No recording found at that time. Please try a different hour.', 404);
            }
            Storage::put("recordings/$filename.xz", Storage::disk('recordings')->get("$path/$filename.xz"));
            shell_exec('/usr/bin/xz -d ' .storage_path("app/recordings/$filename.xz"));
            if (Storage::exists('recordings/'.$filename)) {
                Storage::disk('recordings-temp')->put($filename, Storage::get('recordings/'.$filename));
                Storage::delete(["recordings/$filename", "recordings/$filename.xz"]);
            } else {
                return response('Error: Could not decompress recording. Contact eric@crimeisdown.com for assistance.', 500);
            }
        }

        // generate URL
        $url = Storage::disk('recordings-temp')
            ->getAdapter()
            ->getBucket()
            ->object($file)
            ->signedUrl(now()->addDay());
        return response()->redirectTo($url);
    }
}
