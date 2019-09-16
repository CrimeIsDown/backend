<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use GitWrapper\GitException;
use GitWrapper\GitWorkingCopy;
use GitWrapper\GitWrapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ScrapeDirectives extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'directives:scrape {--generate-diff}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrapes the CPD directives website and commits the current state to a repo';

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
        try {
            $git = $gitWrapper->cloneRepository(Config::get('custom.directives.repository'),
                Config::get('custom.directives.clone_path'));
        } catch (GitException $e) {
            // We must already have it cloned
            $git = $gitWrapper->workingCopy(Config::get('custom.directives.clone_path'));
        }
        $this->scrapeDirectives();
        $this->commitChanges($git);
        if ($this->option('generate-diff')) {
            Artisan::call('directives:diff');
        }
    }

    private function scrapeDirectives()
    {
        chdir(Config::get('custom.directives.clone_path'));
        passthru('rm -Rf directives');
        passthru('wget --recursive -A "*.html*" --no-parent http://directives.chicagopolice.org/directives/');
        passthru('mv directives.chicagopolice.org/directives directives');
        passthru('rmdir directives.chicagopolice.org');
        // Remove query string from filenames
        shell_exec('for file in directives/data/*.html\?*; do mv "$file" "${file%%\?*}"; done');
    }

    private function commitChanges(GitWorkingCopy $git)
    {
        $git->config('user.name', Config::get('custom.git.user.name'));
        $git->config('user.email', Config::get('custom.git.user.email'));
        $git->add('directives');
        try {
            $git->commit('Directives as of ' . Carbon::now()->toFormattedDateString());
        } catch (GitException $e) {
            if (Str::contains($e->getMessage(), 'nothing to commit, working tree clean')) {
                $this->warn('No directives have changed since the last commit.');
                return;
            }
        }
        $git->push();
    }
}
