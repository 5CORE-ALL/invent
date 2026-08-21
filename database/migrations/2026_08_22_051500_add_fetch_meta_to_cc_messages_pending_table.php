<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cc_messages_pending')) {
            return;
        }

        Schema::table('cc_messages_pending', function (Blueprint $table) {
            if (! Schema::hasColumn('cc_messages_pending', 'fetch_status')) {
                $table->string('fetch_status', 32)->nullable()->after('messages_link');
            }
            if (! Schema::hasColumn('cc_messages_pending', 'fetch_note')) {
                $table->string('fetch_note', 512)->nullable();
            }
            if (! Schema::hasColumn('cc_messages_pending', 'source')) {
                $table->string('source', 64)->nullable();
            }
            if (! Schema::hasColumn('cc_messages_pending', 'last_fetched_at')) {
                $table->timestamp('last_fetched_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cc_messages_pending')) {
            return;
        }

        Schema::table('cc_messages_pending', function (Blueprint $table) {
            foreach (['fetch_status', 'fetch_note', 'source', 'last_fetched_at'] as $col) {
                if (Schema::hasColumn('cc_messages_pending', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
