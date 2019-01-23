<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Jenssegers\Agent\Facades\Agent;
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
                'feed'     => 'required|in:zone1,zone2,zone3,zone4,zone5,zone6,zone7,zone8,zone9,zone10,zone11,zone12,zone13,citywide1,citywide2,citywide4,citywide5,citywide6',
                'datetime' => 'required|date'
            ]);
        } catch (ValidationException $e) {
            return response('Make sure you have selected a valid feed, and that your datetime is in the format '.Carbon::now()->minute(0)->second(0)->timezone('America/Chicago')->toDateTimeString(), 400);
        }

        $file_prefix = $request->get('feed').'_'.date('Ymd_His', strtotime($request->get('datetime')));

        preg_match('/(.*?)_([0-9]{4})([0-9]{2})([0-9]{2})_([0-9]{2})([0-9]{2})([0-9]{2})/', $file_prefix, $matches);
        $path = "$matches[2]/$matches[3]/$matches[4]/$matches[5]";
        $filename = "$matches[1]_$matches[2]$matches[3]$matches[4]_$matches[5]0000";
        $file = null; // the file we want to return

        // check if it exists in the temp bucket
        foreach (Storage::disk('recordings-temp')->files() as $tempFile) {
            if (starts_with($tempFile, $filename)) {
                $file = $tempFile;
                break;
            }
        }

        if (!$file) {
            foreach (Storage::disk('recordings')->files($path) as $audioFile) {
                $audioFile = str_replace("$path/", '', $audioFile);
                if (starts_with($audioFile, $filename)) {
                    $file = $this->convertFile($path, $audioFile, $filename);
                    break;
                }
            }
            if (!$file) {
                // We searched both buckets and can't find it
                return response('Error: No recording found at that time. Please try a different hour.', 404);
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

    private function convertFile($path, $filename, $file_prefix)
    {
        $extension = '';
        if (ends_with($filename, '.aac.xz')) {
            $extension = '.aac';
            Storage::put("recordings/$filename", Storage::disk('recordings')->get("$path/$filename"));
            shell_exec('/usr/bin/xz -d ' . storage_path("app/recordings/$filename"));
            if (Storage::exists("recordings/$file_prefix$extension")) {
                Storage::disk('recordings-temp')->put("$file_prefix$extension", Storage::get("recordings/$file_prefix$extension"));
                Storage::delete(["recordings/$file_prefix$extension", "recordings/$filename"]);
            } else {
                return response('Error: Could not decompress recording. Contact eric@crimeisdown.com for assistance.',
                    500);
            }
        } else if (ends_with($filename, '.ogg')) {
            // See https://caniuse.com/#feat=opus
            $opusSupported = !(Agent::is('Safari') || Agent::is('iPhone'));
            if ($opusSupported) {
                $extension = '.ogg';
                Storage::disk('recordings-temp')->put($filename, Storage::disk('recordings')->get("$path/$filename"));
            } else {
                $extension = '.mp3';
                Storage::put("recordings/$filename", Storage::disk('recordings')->get("$path/$filename"));
                $ffmpegPath = trim(shell_exec('which ffmpeg'));
                shell_exec($ffmpegPath . ' -i ' . storage_path("app/recordings/$filename") . ' ' . storage_path("app/recordings/$file_prefix$extension"));
                if (Storage::exists("recordings/$file_prefix$extension")) {
                    Storage::disk('recordings-temp')->put("$file_prefix$extension", Storage::get("recordings/$file_prefix$extension"));
                    Storage::delete(["recordings/$filename", "recordings/$file_prefix$extension"]);
                } else {
                    return response('Error: Could not convert recording to '.$extension.'. Contact eric@crimeisdown.com for assistance.',
                        500);
                }
            }
        }
        return $file_prefix.$extension;
    }
}
