<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Jenssegers\Agent\Facades\Agent;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Config, Crypt, Log, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AudioArchiveController extends Controller
{
    private $limits;
    private $limiter;

    private $key;
    private $limit;

    /**
     * AudioArchiveController constructor.
     * @param RateLimiter $limiter
     */
    public function __construct(RateLimiter $limiter)
    {
        $this->limits = [
            // https://www.patreon.com/posts/login-to-get-8672312
            10 => '$1/mo patron',
            // https://www.patreon.com/posts/login-to-get-8672332
            20 => '$5/mo patron',
            // https://www.patreon.com/posts/login-to-get-8672339
            30 => '$10/mo patron',
            // https://www.patreon.com/posts/login-to-get-8672341
            50 => '$20/mo patron',
            // https://www.patreon.com/posts/login-to-get-8672344
            1000 => '$50/mo patron'
        ];

        $this->limiter = $limiter;
    }

    /**
     * @param string $token
     * @return int
     */
    private function getLimit(string $token): int
    {
        try {
            $token = Crypt::decryptString($token);
        } catch (DecryptException $e) {
            abort(403, 'Invalid token.');
        }

        $limit = array_search($token, $this->limits, true);
        if (!$limit) {
            abort(403, 'Unknown patron tier.');
        }
        return (int) $limit;
    }

    /**
     * @return array
     */
    private function getLimitLinks()
    {
        $limits = [];
        foreach ($this->limits as $limit => $string) {
            $limits[$limit] = route('patreon-login', ['token' => Crypt::encryptString($string)]);
        }
        return $limits;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function patreonLogin(Request $request)
    {
        if (filter_var(Config::get('app.debug'), FILTER_VALIDATE_BOOLEAN)) {
            Log::info($this->getLimitLinks());
        }

        // Patreon links do not send referrer info
        // if (!Str::startsWith($request->header('HTTP_REFERER'), 'https://www.patreon.com/')) {
        //     abort(403, 'You must open this link from Patreon.');
        // }

        $limit = $this->getLimit($request->get('token'));

        $request->session()->put('limit', $limit);
        return response()->redirectTo('https://crimeisdown.com/audio?download_limit='.$limit);
    }

    /**
     * Download an audio archive
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function download(Request $request)
    {
        $this->key = sha1($request->route()->getDomain().'|'.$request->ip());
        $this->limit = $request->session()->get('limit', 5);

        if ($this->limiter->tooManyAttempts($this->key, $this->limit)) {
            $retryAfter = $this->limiter->availableIn($this->key);
            $date = Carbon::now('America/Chicago')->addRealSeconds($retryAfter)->toDayDateTimeString();
            return response("<p>You have reached your daily download limit of $this->limit. You can download another file on $date (Central).</p><p>Want to increase your download limit? <a href='https://www.patreon.com/EricTendian'>Become a patron, or re-login if you are one.</a>", 429, [
                'X-RateLimit-Limit' => $this->limit,
                'X-RateLimit-Remaining' => $this->limiter->retriesLeft($this->key, $this->limit),
                'Retry-After' => $retryAfter,
                'X-RateLimit-Reset' => Carbon::now()->addRealSeconds($retryAfter)->getTimestamp()
            ]);
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
        $basename = "$matches[1]_$matches[2]$matches[3]$matches[4]_$matches[5]0000";


        $extension = '.';
        if (strlen($request->get('format'))) {
            $extension .= $request->get('format');
        } else {
            // See https://caniuse.com/#feat=opus
            $extension .= Agent::is('iPhone') ? 'aac' : 'ogg';
        }
        if (!in_array($extension, ['.ogg', '.aac', '.caf'])) {
            return response('Unsupported audio format.', 400);
        }

        // Return file from cache
        if (Storage::disk('recordings-temp')->exists($basename.$extension)) {
            return $this->generateRedirect($basename.$extension, false);
        }

        $sourceFilename = null;
        // Look for the source file
        foreach (Storage::disk('recordings')->files($path) as $sourceFile) {
            $sourceFilename = str_replace("$path/", '', $sourceFile);
            if (Str::startsWith($sourceFilename, $basename)) {
                break;
            }
        }
        if (!$sourceFilename) {
            // We searched both buckets and can't find it
            return response('Error: No recording found at that time. If this is a very recent recording, it may not have been uploaded yet (it can take up to an hour to upload). Otherwise, please try a different hour.', 404);
        }

        $filename = $this->saveToCache($path, $sourceFilename, $basename, $extension);
        return $this->generateRedirect($filename, true);
    }

    /**
     * @param string $filename
     * @param bool $incrementDownloads
     * @return \Illuminate\Http\RedirectResponse
     */
    private function generateRedirect(string $filename, bool $incrementDownloads): \Illuminate\Http\RedirectResponse
    {
        // generate URL
        $url = Storage::disk('recordings-temp')
            ->getAdapter()
            ->getBucket()
            ->object($filename)
            ->signedUrl(now()->addDay());
        Log::debug("Generated download URL: $url");

        if ($incrementDownloads) {
            $dayInSeconds = 86400;
            $this->limiter->hit($this->key, $dayInSeconds);
            Log::debug(request()->ip().' now has '.$this->limiter->retriesLeft($this->key, $this->limit).' downloads left');
        }

        return response()->redirectTo($url, 302, [
            'X-RateLimit-Limit' => $this->limit,
            'X-RateLimit-Remaining' => $this->limiter->retriesLeft($this->key, $this->limit),
        ]);
    }

    /**
     * @param string $path
     * @param string $sourceFilename
     * @param string $basename
     * @param string $extension
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function saveToCache(string $path, string $sourceFilename, string $basename, string $extension)
    {
        $filePath = "recordings/$sourceFilename";
        // Download source file
        Log::debug("Downloading file from recordings $path/$sourceFilename");
        Storage::put($filePath, Storage::disk('recordings')->get("$path/$sourceFilename"));

        if (Str::endsWith($sourceFilename, '.xz')) {
            $filePath = $this->decompressXz($filePath);
        }

        $filename = $basename.$extension;

        // If the file does not already end with the extension we want, convert it
        if (!Str::endsWith($filePath, $extension)) {
            $filePath = $this->convertFile($filePath, "recordings/$filename", $extension);
        }

        // Upload final file
        Log::debug("Uploading $filePath to $filename in recordings-temp");
        $result = Storage::disk('recordings-temp')->put($filename, Storage::get($filePath));
        Storage::delete($filePath);
        if (!$result) {
            abort(500, 'Error: Could not upload file for download, please try again later.');
        }

        return $filename;
    }

    /**
     * @param string $sourceFilePath
     * @return string
     */
    private function decompressXz(string $sourceFilePath): string
    {
        $command = '/usr/bin/xz -d ' . storage_path("app/$sourceFilePath");
        Log::debug("Running command: $command");
        shell_exec($command);
        $decompressedFilePath = str_replace('.xz', '', $sourceFilePath);
        if (!Storage::exists($decompressedFilePath)) {
            abort(500, 'Error: Could not decompress recording. Contact eric@crimeisdown.com for assistance.');
        }
        return $decompressedFilePath;
    }

    /**
     * @param string $sourceFilePath
     * @param string $convertedFilePath
     * @param string $extension
     * @return string
     */
    private function convertFile(string $sourceFilePath, string $convertedFilePath, string $extension): string
    {
        if (is_readable('/var/run/docker.sock')) {
            $basePath = base_path();
            $ffmpegPath = "docker run --rm --device /dev/dri:/dev/dri -v $basePath:/var/www/html jrottenberg/ffmpeg:4.2-vaapi";
        } else {
            $ffmpegPath = trim(shell_exec('which ffmpeg'));
        }

        $input = storage_path("app/$sourceFilePath");
        $output = storage_path("app/$convertedFilePath");

        $args = '-b:a 32k -ac 1';
        switch ($extension) {
            case '.ogg':
            case '.caf':
                $args .= ' -c:a libopus -ar 24000';
                break;
            case '.aac':
                $args .= ' -c:a libfdk_aac -ar 22050';
                break;
        }

        $command = "$ffmpegPath -y -i $input $args $output";
        Log::debug("Running command: $command");
        shell_exec($command);
        if (
            // File does not exist
            !Storage::exists($convertedFilePath) ||
            // File under 2MB
            Storage::size($convertedFilePath) < (1024 ** 2)
        ) {
            abort(500, 'Error: Could not convert recording to a '.$extension.' file. Please try again, or contact eric@crimeisdown.com for assistance.');
        }
        Storage::delete($sourceFilePath);
        return $convertedFilePath;
    }
}
