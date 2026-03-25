<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ScrapeJustdial extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:justdial {url}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape data from a Justdial URL';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $url = $this->argument('url');

        $this->info("Fetching data from: {$url}");

        // Justdial often blocks simple requests. We need a browser-like User-Agent.
        $response = Http::withoutVerifying()->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Referer' => 'https://www.justdial.com/',
        ])->get($url);

        if ($response->failed()) {
            $this->error("Failed to fetch the page. Status: " . $response->status());
            return 1;
        }

        $html = $response->body();

        // Use DOMDocument for better parsing
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        
        // Justdial structure (example based on current common patterns)
        // Names are often in <span> with lng_cont_name class or within <h2> tags
        $nodes = $xpath->query("//span[contains(@class, 'lng_cont_name')] | //h2[contains(@class, 'result-list-item-title')]");
        
        $results = [];
        foreach ($nodes as $node) {
            $name = trim($node->textContent);
            if ($name) {
                $results[] = [
                    'name' => html_entity_decode($name),
                    'address' => 'Check website for details',
                    'phone' => 'Obfuscated by Justdial'
                ];
            }
        }

        if (empty($results)) {
            // Try another common pattern
            $nodes = $xpath->query("//div[contains(@class, 'content_box')]//h2");
            foreach ($nodes as $node) {
                $name = trim($node->textContent);
                if ($name) {
                    $results[] = [
                        'name' => html_entity_decode($name),
                        'address' => 'N/A',
                        'phone' => 'N/A'
                    ];
                }
            }
        }

        if (empty($results)) {
            $this->warn("No results found. Justdial's HTML structure may have changed or the request was blocked.");
        } else {
            $this->table(['Name', 'Address', 'Phone'], $results);
            $this->info("Found " . count($results) . " items.");
        }

        return 0;
    }
}
