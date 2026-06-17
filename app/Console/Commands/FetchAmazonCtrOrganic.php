<?php

namespace App\Console\Commands;

use App\Services\AmazonCtrFetcher;
use Illuminate\Console\Command;

class FetchAmazonCtrOrganic extends Command
{
    protected $signature = 'amazon:fetch-ctr-organic
        {--period=WEEK : WEEK | MONTH | QUARTER — Brand Analytics report period}
        {--async : Just request the report and print the reportId; don\'t wait for it}
        {--report-id= : Resume an already-requested report (skip the create call)}';

    protected $description = 'Pull organic search CTR per ASIN from SP-API Brand Analytics Search Catalog Performance Report (source=organic).';

    public function handle(AmazonCtrFetcher $fetcher): int
    {
        $period = strtoupper((string) $this->option('period'));
        $async = (bool) $this->option('async');
        $resumeId = trim((string) $this->option('report-id'));

        // Pipe live progress from the service straight into the console.
        // We write to the underlying output writer directly so each line is
        // flushed immediately (Laravel's $this->info buffers across sleep()).
        $writer = $this->getOutput();
        $fetcher->onProgress(function (string $msg) use ($writer) {
            $writer->writeln('<info>• '.$msg.'</info>');
        });

        try {
            if ($async) {
                $resp = $fetcher->requestOrganicReport($period);
                $this->info('Report queued. reportId='.$resp['report_id']);
                $this->line('Resume later with:');
                $this->line('  php artisan amazon:fetch-ctr-organic --period='.$period.' --report-id='.$resp['report_id']);

                return self::SUCCESS;
            }

            $result = $fetcher->fetchOrganicCtr($period, $resumeId !== '' ? $resumeId : null);
        } catch (\Throwable $e) {
            $this->error('Brand Analytics CTR fetch failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Done. report_id=%s window=%s..%s rows_upserted=%d',
            $result['report_id'] ?? 'n/a',
            $result['start_date'],
            $result['end_date'],
            $result['rows_upserted']
        ));

        return self::SUCCESS;
    }
}
