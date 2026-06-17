<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the editable SPRICE column to depop_pricing so the Depop Analytics
     * page can support inline pricing modes (Decrease / Increase / Same Price)
     * in line with the Macys / Shopify B2C / Amazon Analytics pages.
     */
    public function up(): void
    {
        if (!Schema::hasTable('depop_pricing')) {
            return;
        }
        if (Schema::hasColumn('depop_pricing', 'sprice')) {
            return;
        }

        Schema::table('depop_pricing', function (Blueprint $table) {
            $table->decimal('sprice', 12, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('depop_pricing')) {
            return;
        }
        if (!Schema::hasColumn('depop_pricing', 'sprice')) {
            return;
        }

        Schema::table('depop_pricing', function (Blueprint $table) {
            $table->dropColumn('sprice');
        });
    }
};
