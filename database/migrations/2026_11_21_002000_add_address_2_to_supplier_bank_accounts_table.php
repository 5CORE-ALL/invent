<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_bank_accounts') && ! Schema::hasColumn('supplier_bank_accounts', 'address_2')) {
            Schema::table('supplier_bank_accounts', function (Blueprint $table) {
                $table->string('address_2', 50)->nullable()->after('address');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('supplier_bank_accounts') && Schema::hasColumn('supplier_bank_accounts', 'address_2')) {
            Schema::table('supplier_bank_accounts', function (Blueprint $table) {
                $table->dropColumn('address_2');
            });
        }
    }
};
