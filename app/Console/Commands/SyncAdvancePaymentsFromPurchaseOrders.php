<?php

namespace App\Console\Commands;

use App\Models\AdvancePayment;
use App\Models\PurchaseOrder;
use Illuminate\Console\Command;

class SyncAdvancePaymentsFromPurchaseOrders extends Command
{
    protected $signature = 'advance-payments:sync-from-pos {--dry-run : Only report what would change}';

    protected $description = 'Make Advance & Payments match Purchase Contracts: shared archive state, and a row for every PO';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        // 1) Archive state is shared. Archived on either page => archived on both.
        $poIdsArchivedOnPaymentPage = AdvancePayment::where('is_archived', true)
            ->whereNotNull('purchase_contract_id')
            ->pluck('purchase_contract_id')
            ->unique()
            ->all();

        $posToArchive = PurchaseOrder::whereIn('id', $poIdsArchivedOnPaymentPage)
            ->where(function ($q) {
                $q->whereNull('is_archived')->orWhere('is_archived', false);
            })
            ->count();

        $paymentsToArchive = AdvancePayment::where('is_archived', false)
            ->whereIn('purchase_contract_id', PurchaseOrder::where('is_archived', true)->select('id'))
            ->count();

        if (! $dry) {
            // Query-builder updates on purpose (no model events) - both sides are fixed here.
            PurchaseOrder::whereIn('id', $poIdsArchivedOnPaymentPage)
                ->where(function ($q) {
                    $q->whereNull('is_archived')->orWhere('is_archived', false);
                })
                ->update(['is_archived' => true]);

            AdvancePayment::where('is_archived', false)
                ->whereIn('purchase_contract_id', PurchaseOrder::where('is_archived', true)->select('id'))
                ->update(['is_archived' => true, 'archived_at' => now()]);
        }

        // 2) Every PO gets a payment row (archived POs get an archived one, so "Archived" lists match).
        $created = 0;
        $skipped = 0;

        PurchaseOrder::query()
            ->whereNotNull('supplier_id')
            ->orderBy('id')
            ->chunkById(200, function ($pos) use (&$created, &$skipped, $dry) {
                foreach ($pos as $po) {
                    if (AdvancePayment::where('purchase_contract_id', $po->id)->exists()) {
                        $skipped++;
                        continue;
                    }
                    if (! $dry) {
                        AdvancePayment::syncFromPurchaseOrder($po);
                    }
                    $created++;
                }
            });

        $prefix = $dry ? 'Would ' : '';
        $this->info("{$prefix}archive {$posToArchive} PO(s) already archived on the payments page; "
            . "{$prefix}archive {$paymentsToArchive} payment row(s) of archived POs; "
            . "{$prefix}create {$created} missing row(s); {$skipped} PO(s) already had one.");

        return self::SUCCESS;
    }
}
