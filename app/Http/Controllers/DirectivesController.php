<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class DirectivesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $directives = collect(json_decode(Storage::get(Config::get('custom.directives.public_path').'/diff_list.json')))
            ->sortByDesc('issue_timestamp');
        return view('directives.index', ['directives' => $directives]);
    }

    /**
     * Display the specified resource.
     *
     * @param  string  $commit
     * @param  string  $uuid
     * @return \Illuminate\Http\Response
     */
    public function show(string $commit, string $uuid)
    {
        $htmlPath = Config::get('custom.directives.public_path')."/diff/$commit/directives/data/$uuid.html";
        if (!Storage::exists($htmlPath)) {
            // If we don't have a diff of the file, pull the relevant version from the git repo
            // @TODO: Use a git command to fetch the file instead of using master

            $htmlPath = "cpd-directives/directives/data/$uuid.html";
        }

        // Check if we have the file
        if (Storage::exists($htmlPath)) {
            $html = Storage::get($htmlPath);
        } else {
            // We must not have the file at all, return 404
            return response('', 404);
        }

        if (preg_match('/<title>(.*?)<\/title>/', $html, $matches)) {
            $title = $matches[1];
        } else {
            $diffs = collect(json_decode(Storage::get(Config::get('custom.directives.public_path') . '/diff_list.json')));
            $metadata = $diffs->where('path', "$commit/directives/data/$uuid.html")->first();
            if ($metadata) {
                $title = $metadata->title;
            } else {
                $title = '';
            }
        }

        // If one directive links to another, go to the version of it we had at the time of scraping
        $html = preg_replace('/<a href="([a-z0-9\-]{45})\.html" target="new">/i', '<a href="'.str_replace('uuid', '$1', route('directives.diff', ['commit' => $commit, 'uuid' => 'uuid'])).'" target="new">', $html);

        return view('directives.view', ['html' => $html, 'title' => $title, 'uuid' => $uuid]);
    }
}
