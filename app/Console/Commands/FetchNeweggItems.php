<?php

namespace App\Console\Commands;

use App\Models\NeweggItem;
use App\Services\NeweggApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class FetchNeweggItems extends Command
{
    /**
     * Pull the seller's full listed-item catalog from Newegg via the async
     * Item Basic Information Report, store it in newegg_items, and (optionally)
     * fetch each listed SKU's price + inventory into newegg_pricing.
     *
     *   php artisan newegg:items --save
     *   php artisan newegg:items --save --with-price        (also fill newegg_pricing)
     *   php artisan newegg:items --status=1 --save          (active listings only)
     */
    protected $signature = 'newegg:items
        {--status=0 : 0 All, 1 Active, 2 Inactive}
        {--save : Persist the catalog into newegg_items}
        {--with-price : After saving the catalog, run newegg:item-data --save for all listed SKUs}
        {--poll=10 : Seconds between report status polls}
        {--max-wait=600 : Maximum seconds to wait for the report to be ready}
        {--raw : Dump API responses}';

    protected $description = 'Fetch the listed-item catalog (all SKUs) from Newegg and store it';

    public function handle(NeweggApiService $newegg): int
    {
        $status = (int) $this->option('status');

        $this->info('Submitting Item Basic Information report request...');
        $this->line('  SellerID: ' . (config('services.newegg.seller_id') ?: '(not set)'));

        $submit = $newegg->submitItemBasicInfoReport($status, 'CSV');
        if ($submit['blocked_by_cloudflare']) {
            $this->error('Blocked by Cloudflare. Run this from a Newegg-whitelisted server.');
            return self::FAILURE;
        }
        if ($this->option('raw')) {
            $this->line(json_encode($submit['json'], JSON_UNESCAPED_SLASHES));
        }

        $requestId = $this->findValue($submit['json'], [
            'ResponseBody.ResponseList.0.RequestId',
            'NeweggAPIResponse.ResponseBody.ResponseList.0.RequestId',
            'ResponseBody.RequestId',
            'NeweggAPIResponse.ResponseBody.RequestId',
        ]);

        if (!$requestId) {
            $this->error('Could not get a RequestID from the submit response:');
            $this->line(json_encode($submit['json'] ?? $submit['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::FAILURE;
        }

        $this->line('  RequestID: ' . $requestId);
        $this->newLine();

        // Poll for the report file URL.
        $poll    = max((int) $this->option('poll'), 2);
        $maxWait = max((int) $this->option('max-wait'), $poll);
        $waited  = 0;
        $fileUrl = null;

        $this->info('Waiting for the report to be generated...');
        while ($waited <= $maxWait) {
            $res = $newegg->getReportResult($requestId, 'ItemBasicInfoReportRequest');
            if ($res['blocked_by_cloudflare']) {
                $this->error('Blocked by Cloudflare while polling.');
                return self::FAILURE;
            }
            if ($this->option('raw')) {
                $this->line(json_encode($res['json'], JSON_UNESCAPED_SLASHES));
            }

            $fileUrl = $this->findValue($res['json'], [
                'NeweggAPIResponse.ResponseBody.ReportFileURL',
                'ResponseBody.ReportFileURL',
                'ReportFileURL',
            ]);

            if ($fileUrl) {
                break;
            }

            $this->line("  ...not ready yet ({$waited}s elapsed), waiting {$poll}s");
            sleep($poll);
            $waited += $poll;
        }

        if (!$fileUrl) {
            $this->error("Report not ready after {$maxWait}s. Re-run later (the RequestID stays valid): {$requestId}");
            return self::FAILURE;
        }

        $this->line('  ReportFileURL: ' . preg_replace('#://[^@]+@#', '://***:***@', $fileUrl));
        $this->newLine();

        // Download + unpack the report file.
        $content = $this->downloadReport($fileUrl);
        if ($content === null) {
            $this->error('Failed to download or unpack the report file.');
            return self::FAILURE;
        }

        $rows = $this->parseReport($content);
        if (empty($rows)) {
            $this->warn('Report downloaded but no item rows were parsed.');
            return self::FAILURE;
        }

        $this->info('Parsed ' . count($rows) . ' listed item(s).');

        $saved = 0;
        if ($this->option('save')) {
            foreach ($rows as $row) {
                $sku = trim((string) ($row['sellerpart'] ?? ''));
                if ($sku === '') {
                    continue;
                }

                NeweggItem::updateOrCreate(
                    ['seller_part_number' => $sku],
                    [
                        'newegg_item_number'       => $row['neitem'] ?? null,
                        'title'                    => $row['websiteshorttitle'] ?? ($row['title'] ?? null),
                        'manufacturer_part_number' => $row['manufacturerpart'] ?? null,
                        'upc'                      => $row['upc'] ?? null,
                        'status'                   => $row['status'] ?? null,
                        'platform'                 => $row['platform'] ?? null,
                        'item_weight'              => is_numeric($row['itemweight'] ?? null) ? (float) $row['itemweight'] : null,
                        'date_created'             => $row['datecreated'] ?? null,
                        'raw_json'                 => $row,
                    ]
                );
                $saved++;
            }
            $this->info("Saved/updated {$saved} rows in newegg_items.");
        } else {
            $this->comment('Use --save to persist into newegg_items.');
            $this->table(
                ['Seller Part #', 'NE Item #', 'Status', 'Title'],
                collect($rows)->take(20)->map(fn ($r) => [
                    $r['sellerpart'] ?? '',
                    $r['neitem'] ?? '',
                    $r['status'] ?? '',
                    \Illuminate\Support\Str::limit($r['websiteshorttitle'] ?? '', 40),
                ])->all()
            );
        }

        if ($this->option('save') && $this->option('with-price')) {
            $this->newLine();
            $this->info('Fetching price + inventory for all listed SKUs...');
            Artisan::call('newegg:item-data', ['--save' => true, '--source' => 'catalog'], $this->getOutput());
        }

        return self::SUCCESS;
    }

    /**
     * Find the first non-empty value among several candidate dot-paths.
     *
     * @param  array<mixed>|null  $json
     * @param  list<string>  $paths
     */
    private function findValue(?array $json, array $paths): ?string
    {
        if (!is_array($json)) {
            return null;
        }
        foreach ($paths as $path) {
            $val = data_get($json, $path);
            if (is_string($val) && $val !== '') {
                return $val;
            }
        }
        return null;
    }

    /**
     * Download the report file (ftp:// or http(s)://) and return its text content.
     * Handles ZIP archives transparently.
     */
    private function downloadReport(string $url): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'negg_rpt_');

        $ch = curl_init($url);
        $fp = fopen($tmp, 'w');
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FTP_USE_EPSV   => false,
        ]);
        $ok    = curl_exec($ch);
        $errNo = curl_errno($ch);
        $err   = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($ok === false || $errNo !== 0) {
            $this->error("Download error: {$err}");
            @unlink($tmp);
            return null;
        }

        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        if ($bytes === false || $bytes === '') {
            return null;
        }

        // Detect ZIP (PK\x03\x04 magic) or .zip extension.
        $isZip = str_starts_with($bytes, "PK\x03\x04")
            || str_ends_with(strtolower(parse_url($url, PHP_URL_PATH) ?? ''), '.zip');

        if ($isZip && class_exists(\ZipArchive::class)) {
            return $this->extractFirstFileFromZip($bytes);
        }

        return $bytes;
    }

    private function extractFirstFileFromZip(string $zipBytes): ?string
    {
        $tmpZip = tempnam(sys_get_temp_dir(), 'negg_zip_') . '.zip';
        file_put_contents($tmpZip, $zipBytes);

        $zip = new \ZipArchive();
        if ($zip->open($tmpZip) !== true) {
            @unlink($tmpZip);
            return null;
        }

        $content = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name && !str_ends_with($name, '/')) {
                $content = $zip->getFromIndex($i);
                break;
            }
        }
        $zip->close();
        @unlink($tmpZip);

        return $content !== false ? $content : null;
    }

    /**
     * Parse a delimited report (CSV or TAB) into rows keyed by normalized headers.
     *
     * @return list<array<string,?string>>
     */
    private function parseReport(string $content): array
    {
        // Strip a UTF-8 BOM if present.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines   = preg_split('/\r\n|\r|\n/', trim($content));

        if (!$lines || count($lines) < 2) {
            return [];
        }

        $delimiter = substr_count($lines[0], "\t") > substr_count($lines[0], ',') ? "\t" : ',';
        $headers   = array_map([$this, 'normalizeHeader'], str_getcsv($lines[0], $delimiter));

        $rows = [];
        $count = count($lines);
        for ($i = 1; $i < $count; $i++) {
            if (trim($lines[$i]) === '') {
                continue;
            }
            $cols = str_getcsv($lines[$i], $delimiter);
            $row  = [];
            foreach ($headers as $idx => $h) {
                if ($h === '') {
                    continue;
                }
                $row[$h] = isset($cols[$idx]) ? trim((string) $cols[$idx]) : null;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function normalizeHeader(string $header): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $header));
    }
}
