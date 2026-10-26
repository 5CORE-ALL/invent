<?php

namespace App\Console\Commands;

use App\Services\Veeqo\ChargebackOsizeSync;
use App\Services\Veeqo\VeeqoChargebackCsvImport;
use Illuminate\Console\Command;

class ImportVeeqoChargebacksCsvCommand extends Command
{
    protected $signature = 'veeqo:import-chargebacks {file : Veeqo shipping transactions CSV (reports-*.csv)}';

    protected $description = 'Import Veeqo chargeback debit/credit rows from the Veeqo transactions CSV';

    public function handle(VeeqoChargebackCsvImport $import): int
    {
        $result = $import->import((string) $this->argument('file'));
        $this->{$result['ok'] ? 'info' : 'error'}($result['message']);
        if ($result['ok']) {
            $this->line(ChargebackOsizeSync::summary(app(ChargebackOsizeSync::class)->runAfterNewData()));
        }

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
