<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('container_quotations')) {
            return;
        }

        Schema::table('container_quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('container_quotations', 'approved_by')) {
                $table->string('approved_by')->nullable();
            }
            if (! Schema::hasColumn('container_quotations', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('container_quotations')) {
            return;
        }

        Schema::table('container_quotations', function (Blueprint $table) {
            foreach (['approved_by', 'approved_at'] as $col) {
                if (Schema::hasColumn('container_quotations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
