<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_escalations')) {
            return;
        }

        if (! Schema::hasColumn('ai_escalations', 'junior_read_at')) {
            Schema::table('ai_escalations', function (Blueprint $table) {
                $col = $table->timestamp('junior_read_at')->nullable();
                if (Schema::hasColumn('ai_escalations', 'answered_at')) {
                    $col->after('answered_at');
                }
            });
        }
        if (! Schema::hasColumn('ai_escalations', 'email_notification_sent')) {
            Schema::table('ai_escalations', function (Blueprint $table) {
                $col = $table->boolean('email_notification_sent')->default(false);
                if (Schema::hasColumn('ai_escalations', 'junior_read_at')) {
                    $col->after('junior_read_at');
                } elseif (Schema::hasColumn('ai_escalations', 'answered_at')) {
                    $col->after('answered_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_escalations')) {
            return;
        }

        $columns = ['junior_read_at', 'email_notification_sent'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('ai_escalations', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('ai_escalations', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
