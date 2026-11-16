<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('container_quote_received')) {
            Schema::table('container_quote_received', function (Blueprint $table) {
                if (! Schema::hasColumn('container_quote_received', 'shipping_line')) {
                    $table->string('shipping_line')->nullable()->after('forwarder');
                }
                if (! Schema::hasColumn('container_quote_received', 'est_dt_sail')) {
                    $table->date('est_dt_sail')->nullable()->after('shipping_line');
                }
            });
        }

        if (Schema::hasTable('container_quotations')) {
            Schema::table('container_quotations', function (Blueprint $table) {
                if (! Schema::hasColumn('container_quotations', 'via_port')) {
                    $table->string('via_port')->nullable()->after('loading_port');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('container_quote_received')) {
            Schema::table('container_quote_received', function (Blueprint $table) {
                foreach (['shipping_line', 'est_dt_sail'] as $col) {
                    if (Schema::hasColumn('container_quote_received', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        if (Schema::hasTable('container_quotations') && Schema::hasColumn('container_quotations', 'via_port')) {
            Schema::table('container_quotations', function (Blueprint $table) {
                $table->dropColumn('via_port');
            });
        }
    }
};