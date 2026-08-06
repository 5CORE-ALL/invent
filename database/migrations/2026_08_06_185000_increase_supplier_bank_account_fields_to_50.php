<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_bank_accounts')) {
            return;
        }

        $fields = [
            'supplier_name',
            'nick_name',
            'company_name',
            'swift',
            'address',
            'city',
            'province',
            'country',
            'account_number',
        ];

        foreach ($fields as $field) {
            if (Schema::hasColumn('supplier_bank_accounts', $field)) {
                DB::statement("ALTER TABLE supplier_bank_accounts MODIFY {$field} VARCHAR(50) NULL");
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('supplier_bank_accounts')) {
            return;
        }

        $fields = [
            'supplier_name',
            'nick_name',
            'company_name',
            'swift',
            'address',
            'city',
            'province',
            'country',
            'account_number',
        ];

        foreach ($fields as $field) {
            if (Schema::hasColumn('supplier_bank_accounts', $field)) {
                DB::statement("ALTER TABLE supplier_bank_accounts MODIFY {$field} VARCHAR(30) NULL");
            }
        }
    }
};
