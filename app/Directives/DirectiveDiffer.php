<?php

namespace App\Directives;

use Carbon\Carbon;
use Caxy\HtmlDiff\HtmlDiff;
use GitWrapper\GitException;
use GitWrapper\GitWorkingCopy;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DirectiveDiffer
{
    private $commit;
    /**
     * @var GitWorkingCopy
     */
    private $git;

    public function __invoke(string $commit, string $file, GitWorkingCopy $git)
    {
        $this->commit = $commit;
        $this->git = $git;

        if (Str::contains($file, '=>')) {
            // This is a rename
            $matches = [];
            preg_match('/(.*?)\{([\w-]{45}\.html) => ([\w-]{45}\.html)\}/', $file, $matches);
            $oldFile = $matches[1].$matches[2];
            $newFile = $matches[1].$matches[3];
        } else {
            $oldFile = $file;
            $newFile = $file;
        }

        $new = $this->getNewFile($newFile);
        if (!$new) {
            return null;
        }
        $difftext = $new;
        $old = $this->getOldFile($oldFile);
        if ($old) {
            $difftext = $this->generateDiff($old, $new);
        }
        Storage::put(Config::get('custom.directives.public_path')."/diff/$this->commit/$newFile", $difftext);
        return $this->getMetadata($new, $this->commit.'/'.$newFile);
    }

    private function getNewFile(string $file): ?string
    {
        try {
            return $this->git->show($this->commit.':'.$file);
        } catch (GitException $e) {
            return null;
        }
    }

    private function getOldFile(string $file): ?string
    {
        try {
            return $this->git->show($this->commit.'^1:'.$file);
        } catch (GitException $e) {
            return null;
        }
    }

    private function generateDiff($old, $new): string
    {
        $diff = new HtmlDiff($old, $new);
        $diff->build();
        return $diff->getDifference();
    }

    private function getMetadata($html, $path)
    {
        $metadata = ['path' => $path];

        preg_match('/<title>(.*?)<\/title>/', $html, $matches);
        if (\count($matches)) {
            $metadata['title'] = trim($matches[1]);
        }

        preg_match('/<td class="td1">Issue Date:<\/td><td class="td2">(.*?)<\/td>/', $html, $matches);
        if (\count($matches)) {
            $metadata['issue_date'] = $matches[1];
            $metadata['issue_timestamp'] = Carbon::parse($matches[1])->timestamp;
        }

        preg_match('/<td class="td1">Effective Date:<\/td><td class="td2">(.*?)<\/td>/', $html, $matches);
        if (\count($matches)) {
            $metadata['effective_date'] = $matches[1];
        }

        preg_match('/<td class="td1">Rescinds:<\/td><td class="td3" colspan="3">(.*?)<\/td>/', $html, $matches);
        if (\count($matches)) {
            $metadata['rescinds'] = $matches[1];
        }

        preg_match('/<td class="CPDDirectiveTypeAndNumber">(.*?)&nbsp;(.*?)<\/td>/', $html, $matches);
        if (\count($matches)) {
            $metadata['index_category'] = $matches[1];
        }

        preg_match('/<td class="td1">Index Category:<\/td><td class="td3" colspan="3">(.*?)<\/td>/', $html, $matches);
        if (\count($matches)) {
            $metadata['index_category'] .= ' - ' . $matches[1];
        }

        if (\count($metadata) >= 7) {
            return (object) $metadata;
        }
    }
}
