<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Approved" tick on a received forwarder quote (L1 / L2 / L3 popup): who approved it and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('container_quote_received')) {
            return;
        }

        Schema::table('container_quote_received', function (Blueprint $table) {
            if (! Schema::hasColumn('container_quote_received', 'approved_by')) {
                $table->string('approved_by')->nullable();
            }
            if (! Schema::hasColumn('container_quote_received', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('container_quote_received')) {
            return;
        }

        Schema::table('container_quote_received', function (Blueprint $table) {
            foreach (['approved_by', 'approved_at'] as $col) {
                if (Schema::hasColumn('container_quote_received', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
