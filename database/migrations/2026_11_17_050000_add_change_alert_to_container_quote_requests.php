<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('container_quote_requests')) {
            return;
        }

        Schema::table('container_quote_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('container_quote_requests', 'quote_changed_at')) {
                $table->timestamp('quote_changed_at')->nullable()->after('submitted_at');
            }
            if (! Schema::hasColumn('container_quote_requests', 'quote_changes')) {
                $table->text('quote_changes')->nullable()->after('quote_changed_at');
            }
            if (! Schema::hasColumn('container_quote_requests', 'change_ack_by')) {
                $table->string('change_ack_by')->nullable()->after('quote_changes');
            }
            if (! Schema::hasColumn('container_quote_requests', 'change_ack_at')) {
                $table->timestamp('change_ack_at')->nullable()->after('change_ack_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('container_quote_requests')) {
            return;
        }

        Schema::table('container_quote_requests', function (Blueprint $table) {
            foreach (['quote_changed_at', 'quote_changes', 'change_ack_by', 'change_ack_at'] as $col) {
                if (Schema::hasColumn('container_quote_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};