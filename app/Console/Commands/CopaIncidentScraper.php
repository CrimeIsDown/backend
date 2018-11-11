<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use GitWrapper\GitException;
use GitWrapper\GitWorkingCopy;
use GitWrapper\GitWrapper;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use League\Csv\EncloseField;
use League\Csv\Writer;
use Symfony\Component\DomCrawler\Crawler;

class CopaIncidentScraper extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'copa:scrape';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Saves a CSV of all cases stored on the COPA (formerly IPRA) Chicago website and commits the changes to a repo';

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
     * @param Client $guzzleClient
     * @return void
     * @throws \League\Csv\CannotInsertRecord
     */
    public function handle(GitWrapper $gitWrapper, Client $guzzleClient)
    {
        $git = $this->getRepoWorkingCopy($gitWrapper);

        $this->comment('Requesting the cases...');

        $html = $this->getHtml($guzzleClient);

        $this->comment('Parsing HTML and scraping the data...');

        $rows = $this->parseHtml($html);

        $this->comment('Formatting and writing the cases to a CSV...');

        $this->saveCsv($rows);

        $this->info('New cases CSV written successfully.');

        $this->commitChanges($git);
    }

    private function getHtml(Client $client)
    {
        $response = $client->get('https://www.chicagocopa.org/wp-content/themes/copa/DynamicSearch.php', ['query' => [
            'ss' => '',
            'alt-ipracats' => '',
            'notificationStartDate' => '',
            'alt-notificationStartDate' => '',
            'notificationEndDate' => '',
            'alt-notificationEndDate' => '',
            'incidentStartDate' => '',
            'alt-incidentStartDate' => '',
            'incidentEndDate' => '',
            'alt-incidentEndDate' => '',
            'district' => ''
        ]]);
        $response = json_decode($response->getBody()->getContents());
        return $response->caseSearch->items;
    }

    private function parseHtml(string $html)
    {
        $crawler = new Crawler($html);

        // Pull out column headers
        $headers = $crawler->filter('table thead th')
            ->extract(['_text']);
        array_unshift($headers, 'URL'); // add URL to the beginning of the array

        $rows = collect();
        $crawler->filter('table tbody tr')->each(function (Crawler $node) use ($headers, $rows) {
            $row = [];
            // URL
            $row[$headers[0]] = trim($node->children()
                ->filter('th a')->extract(['href'])[0]);
            // Log#
            $row[$headers[1]] = trim($node->children()
                ->filter('th')->extract(['_text'])[0]);

            $cells = $node->children()
                ->filter('td')->extract(['_text']);
            // Incident Types
            $row[$headers[2]] = trim($cells[0]);
            // IPRA/COPA Notification Date
            $row[$headers[3]] = trim(substr(trim($cells[1]), 10)); // hide the hidden date
            if ('' === $row[$headers[3]]) {
                $row[$headers[3]] = '-';
            }
            // Incident Date & Time
            $row[$headers[4]] = trim(substr(trim($cells[2]), 10)); // hide the hidden date
            if ('' === $row[$headers[4]]) {
                $row[$headers[4]] = '-';
            }
            // District of Occurrence
            $row[$headers[5]] = trim($cells[3]);

            $rows->push($row);
        });

        return $rows->sortBy($headers[1]); // Sort by log number ascending
    }

    /**
     * @param $rows
     * @throws \League\Csv\CannotInsertRecord
     */
    private function saveCsv($rows)
    {
        $csv = Writer::createFromString('');
        EncloseField::addTo($csv, "\t\x1f"); // Force quoting all cells

        $csv->insertOne(array_keys($rows->first()));
        $csv->insertAll($rows->values()->toArray());

        Storage::put(str_replace(storage_path('app').'/', '', Config::get('custom.copa.clone_path').'cases.csv'), $csv->getContent());
    }

    private function getRepoWorkingCopy(GitWrapper $gitWrapper)
    {
        try {
            return $gitWrapper->cloneRepository(Config::get('custom.copa.repository'),
                Config::get('custom.copa.clone_path'));
        } catch (GitException $e) {
            // We must already have it cloned
            return $gitWrapper->workingCopy(Config::get('custom.copa.clone_path'));
        }
    }

    private function commitChanges(GitWorkingCopy $git)
    {
        $git->config('user.name', Config::get('custom.git.user.name'));
        $git->config('user.email', Config::get('custom.git.user.email'));
        $git->add('cases.csv');
        try {
            $this->comment($git->commit('Incidents as of ' . Carbon::now()->toFormattedDateString()));
        } catch (GitException $e) {
            if (str_contains($e->getMessage(), 'nothing to commit, working tree clean')) {
                $this->warn('No cases have changed since the last commit.');
                return;
            }
        }
        $git->push();
        $this->info('Updated repository with changes.');
    }
}
