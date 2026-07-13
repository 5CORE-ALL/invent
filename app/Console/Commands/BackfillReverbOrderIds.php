<?php

namespace App\Console\Commands;

use App\Models\ReverbOrderMetric;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillReverbOrderIds extends Command
{
    protected $signature = 'reverb:backfill-order-ids {--dry-run : Show counts without writing}';

    protected $description = 'Copy order_number into empty order_id and remove duplicate Reverb order rows';

    public function handle(): int
    {
        if (! Schema::hasTable('reverb_order_metrics')) {
            $this->error('reverb_order_metrics table missing.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        $empty = ReverbOrderMetric::query()
            ->where(function ($q) {
                $q->whereNull('order_id')->orWhere('order_id', '');
            })
            ->whereNotNull('order_number')
            ->where('order_number', '!=', '')
            ->count();

        $this->info("Rows with empty order_id but filled order_number: {$empty}");

        if (! $dry && $empty > 0) {
            $updated = ReverbOrderMetric::query()
                ->where(function ($q) {
                    $q->whereNull('order_id')->orWhere('order_id', '');
                })
                ->whereNotNull('order_number')
                ->where('order_number', '!=', '')
                ->update([
                    'order_id' => DB::raw('order_number'),
                ]);
            $this->info("Backfilled order_id on {$updated} row(s).");
        }

        // Also sync order_number from order_id when number is empty.
        $emptyNumber = ReverbOrderMetric::query()
            ->where(function ($q) {
                $q->whereNull('order_number')->orWhere('order_number', '');
            })
            ->whereNotNull('order_id')
            ->where('order_id', '!=', '')
            ->count();
        $this->info("Rows with empty order_number but filled order_id: {$emptyNumber}");
        if (! $dry && $emptyNumber > 0) {
            $updated = ReverbOrderMetric::query()
                ->where(function ($q) {
                    $q->whereNull('order_number')->orWhere('order_number', '');
                })
                ->whereNotNull('order_id')
                ->where('order_id', '!=', '')
                ->update([
                    'order_number' => DB::raw('order_id'),
                ]);
            $this->info("Backfilled order_number on {$updated} row(s).");
        }

        $dupGroups = DB::table('reverb_order_metrics')
            ->select('order_id', 'sku', DB::raw('COUNT(*) as c'), DB::raw('GROUP_CONCAT(id ORDER BY id) as ids'))
            ->whereNotNull('order_id')
            ->where('order_id', '!=', '')
            ->groupBy('order_id', 'sku')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $this->info('Duplicate (order_id, sku) groups: '.$dupGroups->count());

        $deleted = 0;
        foreach ($dupGroups as $group) {
            $ids = array_values(array_filter(array_map('intval', explode(',', (string) $group->ids))));
            if (count($ids) < 2) {
                continue;
            }

            $rows = ReverbOrderMetric::query()->whereIn('id', $ids)->orderByDesc('id')->get();
            $keeper = $rows->first(fn ($r) => ! empty($r->shopify_order_id))
                ?? $rows->first(fn ($r) => ! empty($r->raw_payload))
                ?? $rows->first();

            if (! $keeper) {
                continue;
            }

            foreach ($rows as $row) {
                if ((int) $row->id === (int) $keeper->id) {
                    continue;
                }
                // Prefer keeping shopify / import status on the survivor.
                if (empty($keeper->shopify_order_id) && ! empty($row->shopify_order_id)) {
                    $keeper->shopify_order_id = $row->shopify_order_id;
                    $keeper->pushed_to_shopify_at = $row->pushed_to_shopify_at;
                    $keeper->import_status = $row->import_status;
                }
                if (empty($keeper->raw_payload) && ! empty($row->raw_payload)) {
                    $keeper->raw_payload = $row->raw_payload;
                }
                if (! $dry) {
                    $row->delete();
                }
                $deleted++;
            }

            if (! $dry) {
                $keeper->order_id = (string) $group->order_id;
                $keeper->order_number = (string) $group->order_id;
                $keeper->save();
            }
        }

        $this->info(($dry ? 'Would delete' : 'Deleted')." {$deleted} duplicate row(s).");
        $remainingEmpty = ReverbOrderMetric::query()
            ->where(function ($q) {
                $q->whereNull('order_id')->orWhere('order_id', '');
            })
            ->count();
        $this->info("Remaining empty order_id rows: {$remainingEmpty}");

        return self::SUCCESS;
    }
}
