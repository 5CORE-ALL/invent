<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supplier default currency (USD / RMB), remembered for the next purchase contract.
        if (Schema::hasTable('suppliers') && ! Schema::hasColumn('suppliers', 'currency')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->string('currency', 3)->nullable();
            });
        }

        // Purchase contract currency (USD / RMB).
        if (Schema::hasTable('purchase_orders') && ! Schema::hasColumn('purchase_orders', 'currency')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->string('currency', 3)->default('USD');
            });

            // Existing POs: Yuan when their first priced line is in RMB.
            DB::table('purchase_orders')->orderBy('id')->select('id', 'items')->chunkById(200, function ($pos) {
                foreach ($pos as $po) {
                    $items = json_decode((string) $po->items, true);
                    if (! is_array($items)) {
                        continue;
                    }
                    foreach ($items as $item) {
                        $cur = strtoupper(trim((string) ($item['currency'] ?? '')));
                        if ($cur === '') {
                            continue;
                        }
                        if (in_array($cur, ['RMB', 'CNY', 'CNH', 'YUAN'], true)) {
                            DB::table('purchase_orders')->where('id', $po->id)->update(['currency' => 'RMB']);
                        }
                        break;
                    }
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_orders') && Schema::hasColumn('purchase_orders', 'currency')) {
            Schema::table('purchase_orders', fn (Blueprint $t) => $t->dropColumn('currency'));
        }
        if (Schema::hasTable('suppliers') && Schema::hasColumn('suppliers', 'currency')) {
            Schema::table('suppliers', fn (Blueprint $t) => $t->dropColumn('currency'));
        }
    }
};
