<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
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
    protected $description = 'Command description';

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
     * @param Client $guzzleClient
     * @return void
     * @throws \League\Csv\CannotInsertRecord
     */
    public function handle(Client $guzzleClient)
    {
        $this->info('Requesting the cases...');
        $response = $guzzleClient->get('https://www.chicagocopa.org/wp-content/themes/copa/DynamicSearch.php', ['query' => [
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
        $html = $response->caseSearch->items;
        $crawler = new Crawler($html);

        $this->info('Parsing HTML and scraping the data...');

        // pull out column headers
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

        $this->info('Formatting and writing the cases to a CSV...');

        $rows = $rows->sortBy($headers[1]);

        $csv = Writer::createFromString('');
        EncloseField::addTo($csv, "\t\x1f"); // Force quoting all cells

        $csv->insertOne($headers);
        $csv->insertAll($rows->values()->toArray());

        Storage::put('cases.csv', $csv->getContent());

        $this->info('All cases written successfully');
    }
}
