<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_records', function (Blueprint $table) {
            $table->decimal('refund_amt', 12, 2)->default(0)->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('refund_records', function (Blueprint $table) {
            $table->dropColumn('refund_amt');
        });
    }
};
