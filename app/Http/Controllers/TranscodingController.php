<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TranscodingController extends Controller
{
    /**
     * Generate a video from an audio file
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     * @throws ValidationException
     */
    public function generateVideo(Request $request)
    {
        $this->validate($request, [
            'title'     => 'max:50',
            'audioFile' => 'required|file'
        ]);

        $title = $request->get('title', ' ');
        $audioFile = $request->file('audioFile');
        $audioPath = $audioFile->hashName().($audioFile->guessExtension() ?: $audioFile->getClientOriginalExtension());
        $audioFile->storeAs('recordings', $audioPath);
        $audioPath = 'recordings/'.$audioPath;

        $videoPath = 'recordings/'.str_random(40).'.mp4';

        $hwaccelArgs = '-init_hw_device vaapi=intel:/dev/dri/renderD128 -hwaccel vaapi -hwaccel_device intel -filter_hw_device intel';

        $waveformPath = 'recordings/'.str_random(40).'.png';

        $waveformCommand = '/usr/sbin/ffmpeg '.$hwaccelArgs.' -y -i '.escapeshellarg(storage_path('app').DIRECTORY_SEPARATOR.$audioPath).' -filter_complex "showwavespic=s=1280x300:split_channels=1:colors=00ff00|00ff00" -frames:v 1 '.escapeshellarg(storage_path('app').DIRECTORY_SEPARATOR.$waveformPath).' -v error';
        $result = exec($waveformCommand);

        $durationCommand = '/usr/sbin/ffprobe -show_entries format=duration -v error -of default=noprint_wrappers=1:nokey=1 '.escapeshellarg(storage_path('app').DIRECTORY_SEPARATOR.$audioPath);
        $result = exec($durationCommand);
        $duration = round((float) $result, 2);

        $captionPath = 'recordings/'.str_random(40).'.txt';
        Storage::put($captionPath, $title);

        $command = '/usr/sbin/ffmpeg '.$hwaccelArgs.' -y -i '.escapeshellarg(storage_path('app').DIRECTORY_SEPARATOR.$audioPath).' -loop 1 -i '.storage_path('videoassets/audio_bg_transparent.png').' -i '.escapeshellarg(storage_path('app').DIRECTORY_SEPARATOR.$waveformPath).' -i '.storage_path('videoassets/audio_progress.png').' -filter_complex "[1][2]overlay=x=0:y=H-h:eval=init[over];[over]drawtext=fontfile='.storage_path('videoassets/HelveticaNeue-Light.otf').':fontsize=72:textfile='.escapeshellarg(storage_path('app').DIRECTORY_SEPARATOR.$captionPath).':x=(w-text_w)/2:y=(h-325-text_h):fontcolor=white:shadowy=2:shadowx=2:shadowcolor=black[text];[text][3] overlay=x=\'-w+W*t/'.$duration.'\':y=H-h:format=yuv420" -shortest -c:a aac -b:a 128k -c:v libx264 -pix_fmt yuv420p -preset ultrafast '.escapeshellarg(storage_path('app').DIRECTORY_SEPARATOR.$videoPath);

        passthru($command);

        Storage::delete([$waveformPath, $captionPath, $audioPath]);

        return Storage::download($videoPath, basename($audioFile->getClientOriginalName(), $audioFile->getClientOriginalExtension()).'mp4');
    }

    /**
     * Form for generating a video
     *
     * @return \Illuminate\Http\Response
     */
    public function generateVideoForm()
    {
        return view('generate-video');
    }
}
