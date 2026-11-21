<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meeting_participants') && ! Schema::hasColumn('meeting_participants', 'postponed_until')) {
            Schema::table('meeting_participants', function (Blueprint $table) {
                // "RS": the invitee moved this request to the bottom ("Scheduled" section) until this day (company time zone).
                $table->date('postponed_until')->nullable()->after('rsvp_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('meeting_participants', 'postponed_until')) {
            Schema::table('meeting_participants', function (Blueprint $table) {
                $table->dropColumn('postponed_until');
            });
        }
    }
};
