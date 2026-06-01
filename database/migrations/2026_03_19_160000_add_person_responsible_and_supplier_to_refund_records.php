<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_records', function (Blueprint $table) {
            if (!Schema::hasColumn('refund_records', 'person_responsible')) {
                $table->string('person_responsible', 100)->nullable()->after('comment');
            }
            if (!Schema::hasColumn('refund_records', 'supplier_id')) {
                $table->unsignedBigInteger('supplier_id')->nullable()->after('person_responsible');
                $table->index('supplier_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('refund_records', function (Blueprint $table) {
            if (Schema::hasColumn('refund_records', 'supplier_id')) {
                $table->dropIndex(['supplier_id']);
                $table->dropColumn('supplier_id');
            }
            if (Schema::hasColumn('refund_records', 'person_responsible')) {
                $table->dropColumn('person_responsible');
            }
        });
    }
};
