<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_portal_assets')) {
            return;
        }

        $map = [
            'logos' => 'brand_assets',
            'packaging' => 'inner_box_designs',
            'marketing' => 'brand_assets',
            'documents' => 'brand_assets',
        ];

        foreach ($map as $from => $to) {
            DB::table('supplier_portal_assets')
                ->where('category', $from)
                ->update(['category' => $to]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('supplier_portal_assets')) {
            return;
        }

        DB::table('supplier_portal_assets')
            ->where('category', 'brand_assets')
            ->update(['category' => 'logos']);
        DB::table('supplier_portal_assets')
            ->where('category', 'inner_box_designs')
            ->update(['category' => 'packaging']);
    }
};
