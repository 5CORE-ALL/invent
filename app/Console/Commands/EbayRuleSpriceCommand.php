<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\EbayController;
use App\Http\Controllers\MarketPlace\EbayThreeController;
use App\Http\Controllers\MarketPlace\EbayTwoController;
use App\Support\EbayRuleSprice;
use App\Support\StdPrcVsDilPricer;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Prices eBay 1 / 2 / 3 with the Amazon Std prc vs dil chain from the same rows the tabulator loads.
 * Dry run by default. --save writes changed S PRC through the channel's save endpoint. Never pushes to eBay.
 */
class EbayRuleSpriceCommand extends Command
{
    protected $signature = 'ebay:rule-sprice
        {channel : ebay1, ebay2 or ebay3}
        {--sku=* : Only these SKUs}
        {--limit=25 : Rows to print}
        {--save : Save changed S PRC (no eBay push)}';

    protected $description = 'eBay 1/2/3 S PRC from Std prc vs dil + Min NPFT + LMP cap + Std cap (dry run unless --save)';

    public function handle(): int
    {
        $channel = strtolower((string) $this->argument('channel'));
        if (! in_array($channel, EbayRuleSprice::CHANNELS, true)) {
            $this->error('channel must be one of: '.implode(', ', EbayRuleSprice::CHANNELS));

            return self::FAILURE;
        }
        $only = array_map(static fn ($s) => strtoupper(trim((string) $s)), (array) $this->option('sku'));
        $pricer = StdPrcVsDilPricer::forChannel($channel);

        $rows = [];
        $changes = [];
        $unchanged = 0;
        $skipped = 0;
        foreach ($this->tabulatorRows($channel) as $row) {
            $input = EbayRuleSprice::inputFromTabulatorRow($row);
            if ($input === null) {
                continue;
            }
            if ($only !== [] && ! in_array(strtoupper($input['sku']), $only, true)) {
                continue;
            }
            $priced = EbayRuleSprice::price($pricer, $input);
            if ($priced === null) {
                $skipped++;

                continue;
            }
            $saved = round((float) ($row['SPRICE'] ?? 0), 2);
            if (abs($saved - $priced['sprice']) <= 0.009) {
                $unchanged++;
            } else {
                $changes[] = ['sku' => $input['sku'], 'sprice' => $priced['sprice']];
            }
            $rows[] = [
                $input['sku'],
                $input['std'],
                $priced['rule_price'],
                $input['lmp'] > 0 ? round($input['lmp'], 2) : '',
                $priced['sprice'],
                $saved > 0 ? $saved : '',
                ($priced['lmp_capped'] ? 'LMP ' : '').($priced['std_capped'] ? 'Std' : ''),
            ];
        }

        $this->table(['SKU', 'Std', 'Rule', 'LMP', 'S PRC', 'Saved', 'Cap'], array_slice($rows, 0, max(1, (int) $this->option('limit'))));
        $this->info(sprintf('%s: %d priced, %d changed, %d unchanged, %d skipped (INV 0 / no Std).',
            $channel, count($rows), count($changes), $unchanged, $skipped));

        if (! $this->option('save')) {
            $this->comment('Dry run. Nothing saved. Use --save to write changed S PRC.');

            return self::SUCCESS;
        }
        foreach (array_chunk($changes, 200) as $chunk) {
            $this->saveChunk($channel, $chunk);
        }
        $this->info('Saved '.count($changes).' S PRC (no eBay push).');

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tabulatorRows(string $channel): array
    {
        // Row builders only: the *DataJson wrappers also write the daily summary.
        $request = new Request();
        $request->attributes->set('skip_daily_summary', true);
        $level = ob_get_level();
        ob_start();
        try {
            $response = match ($channel) {
                'ebay1' => app(EbayController::class)->getViewEbayData($request),
                'ebay2' => app(EbayTwoController::class)->getViewEbayData($request),
                'ebay3' => app(EbayThreeController::class)->getViewEbay3DataTabulator($request),
            };
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
        $payload = json_decode((string) $response->getContent(), true);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : (is_array($payload) ? $payload : []);

        return $this->flatten($data);
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private function flatten(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = $row;
            if (is_array($row['_children'] ?? null)) {
                array_push($out, ...$this->flatten($row['_children']));
            }
        }

        return $out;
    }

    /**
     * @param  list<array{sku:string,sprice:float}>  $chunk
     */
    private function saveChunk(string $channel, array $chunk): void
    {
        $request = Request::create('/', 'POST', ['updates' => $chunk, 'skip_push' => 1]);
        match ($channel) {
            'ebay1' => app(EbayController::class)->saveSpriceToDatabase($request),
            'ebay2' => app(EbayTwoController::class)->saveSpriceToDatabase($request),
            'ebay3' => app(EbayThreeController::class)->saveSpriceToDatabase($request),
        };
    }
}
