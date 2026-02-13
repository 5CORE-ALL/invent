<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_escalations', function (Blueprint $table) {
            $table->timestamp('junior_read_at')->nullable()->after('answered_at');
            $table->boolean('email_notification_sent')->default(false)->after('junior_read_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_escalations', function (Blueprint $table) {
            $table->dropColumn(['junior_read_at', 'email_notification_sent']);
        });
    }
};
