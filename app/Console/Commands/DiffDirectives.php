<?php

namespace App\Console\Commands;

use App\Directives\DirectiveDiffer;
use GitWrapper\GitException;
use GitWrapper\GitWorkingCopy;
use GitWrapper\GitWrapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class DiffDirectives extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'directives:diff';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * @var GitWorkingCopy
     */
    private $git;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @param GitWrapper $gitWrapper
     * @return void
     */
    public function handle(GitWrapper $gitWrapper)
    {
        $this->info('Initializing...');

        $diffListPath = Config::get('custom.directives.public_path').'/diff_list.json';

        $finishedDiffs = Storage::exists($diffListPath) ? json_decode(Storage::get($diffListPath)) : [];
        $finishedDiffs = collect($finishedDiffs); // convert to collection so we can do sorting

        try {
            $this->git = $gitWrapper->cloneRepository(Config::get('custom.directives.repository'),
                Config::get('custom.directives.clone_path'));
        } catch (GitException $e) {
            // We must already have it cloned
            $this->git = $gitWrapper->workingCopy(Config::get('custom.directives.clone_path'));
        }

        // find the commits that we have not checked for diffs
        $commits = $this->getCommitsToDiff();

        $changedFiles = [];
        foreach ($commits as $commit) {
            $this->getFilesChangedInCommit($commit, $changedFiles);
        }

        $this->info('Need to calculate diffs for '.\count($changedFiles).' files');

        foreach ($changedFiles as $i => $file) {
            $this->info('Calculating diff '.($i+1).' of '.\count($changedFiles));
            $diffPath = Config::get('custom.directives.public_path')."/diff/{$file['commit']}/{$file['path']}";
            $this->info($diffPath);
            if (Storage::exists($diffPath)) {
                if (!$this->confirm("The diff at $diffPath already exists, should we regenerate it?")) {
                    // Skip to the next file
                    continue;
                }
            }
            $result = $this->generateDiff($file['commit'], $file['path']);
            if ($result) {
                $finishedDiffs->push($result);
                Storage::put($diffListPath, json_encode($finishedDiffs->sortByDesc('issue_timestamp')->values()->toArray(), JSON_PRETTY_PRINT));
            }
        }

        $this->info('Finished diffing.');
    }

    private function getCommitsToDiff()
    {
        $commits = explode("\n", trim($this->git->log('--format=%H')));
        // Remove the first commit from the array since we can't diff it
        array_pop($commits);
        $commitsToCheck = [];

        foreach ($commits as $i => $commit) {
            if (
                // Check for changed files if we don't have a directory made for this commit
                !Storage::exists(Config::get('custom.directives.public_path')."/diff/$commit") ||
                // Or if we don't have any files in the directory, which may indicate the process failed
                \count(Storage::files(Config::get('custom.directives.public_path')."/diff/$commit")) === 0
            ) {
                $commitsToCheck[] = $commit;
            }
        }

        $this->info('Need to diff '.max(0, \count($commitsToCheck)-1).' commits out of a total '.(\count($commits)-1));

        return $commitsToCheck;
    }

    private function getFilesChangedInCommit($commit, array &$directivesChanged): array
    {
        $filesChanged = explode("\n", trim($this->git->diff($commit.'^1', $commit, '--numstat', '-w', '--no-abbrev')));

        $changedInThisCommit = 0;
        foreach ($filesChanged as $i => $file) {
            $fileStat = explode("\t", $file);
            $linesAdded = (int) $fileStat[0];
            $linesDeleted = (int) $fileStat[1];
            $filePath = $fileStat[2];
            if (
                // Check if the lines added/deleted are different, which indicates a real change to the directive contents
                $linesAdded !== $linesDeleted &&
                // Verify this matches the directive filename format
                preg_match('/\w{8}-\w{8}-\w{5}-\w{4}-\w{16}/', $filePath) === 1
            ) {
                $directivesChanged[] = ['commit' => $commit, 'path' => $filePath];
                $changedInThisCommit++;
            }
        }

        // We will be storing diffs here
        if ($changedInThisCommit) {
            Storage::makeDirectory(Config::get('custom.directives.public_path')."/diff/$commit/directives/data/");
        }

        return $directivesChanged;
    }

    private function generateDiff($commit, $file)
    {
        return (new DirectiveDiffer)($commit, $file, $this->git);
    }
}
