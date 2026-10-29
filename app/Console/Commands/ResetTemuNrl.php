<?php

namespace App\Console\Commands;

use App\Models\TemuDataView;
use App\Models\TemuListingStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Temu 1 only: there is no NRL on Temu 1, so every SKU that is still flagged NRL / NR is set back to REQ
 * (temu_data_view and the Temu 1 listing status). Temu 2 and Temu 3 are not touched.
 */
class ResetTemuNrl extends Command
{
    protected $signature = 'temu:reset-nrl {--dry-run : Only list the SKUs that would be set back to REQ}';

    protected $description = 'Temu 1: set every NRL / NR flag back to REQ';

    private const FLAG_KEYS = ['NRL', 'NR', 'NRP'];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $changed = [];

        foreach ([TemuDataView::class => 'DataView', TemuListingStatus::class => 'Listing status'] as $model => $label) {
            $model::query()->whereNotNull('value')->orderBy('id')->chunkById(500, function ($rows) use ($model, $label, $dry, &$changed) {
                foreach ($rows as $row) {
                    $before = $row->value;
                    $data = is_array($before) ? $before : json_decode((string) $before, true);
                    if (! is_array($data)) {
                        $text = strtoupper(trim((string) $before));
                        if (! in_array($text, ['NR', 'NRL'], true)) {
                            continue;
                        }
                        $after = ['nr_req' => 'REQ'];
                    } else {
                        $after = $data;
                        $hit = false;
                        foreach (self::FLAG_KEYS as $key) {
                            if (array_key_exists($key, $after) && $this->isNrl($after[$key])) {
                                unset($after[$key]);
                                $hit = true;
                            }
                        }
                        if (array_key_exists('nr_req', $after) && $this->isNrl($after['nr_req'])) {
                            $hit = true;
                        }
                        if (! $hit) {
                            continue;
                        }
                        $after['nr_req'] = 'REQ';
                    }

                    $changed[] = ['table' => $label, 'id' => $row->id, 'sku' => (string) $row->sku, 'before' => $before];
                    if (! $dry) {
                        $row->value = $after;
                        $row->save();
                    }
                }
            });
        }

        foreach ($changed as $c) {
            $this->line(sprintf('%-15s %s', $c['table'], $c['sku']));
        }
        $this->info(($dry ? 'Would set back to REQ: ' : 'Set back to REQ: ').count($changed).' row(s).');

        if (! $dry && $changed !== []) {
            Storage::disk('local')->put('temu_cache/temu_nrl_reset_'.now()->format('Ymd_His').'.json', json_encode($changed, JSON_UNESCAPED_UNICODE));
            $this->line('Previous values saved in storage/app/temu_cache/.');
        }

        return self::SUCCESS;
    }

    private function isNrl(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        return in_array(strtoupper(trim((string) $value)), ['NRL', 'NR'], true);
    }
}
