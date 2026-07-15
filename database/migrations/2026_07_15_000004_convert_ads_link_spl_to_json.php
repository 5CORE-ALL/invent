<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ads_link_sku_fields')) {
            return;
        }

        $rows = DB::table('ads_link_sku_fields')->select(['id', 'plus_kw_spl', 'pt_spl'])->get();
        $converted = [];
        foreach ($rows as $row) {
            $plusKwSpl = [];
            $ptSpl = [];
            if ($row->plus_kw_spl !== null && $row->plus_kw_spl !== '') {
                $decoded = json_decode((string) $row->plus_kw_spl, true);
                if (is_array($decoded)) {
                    $plusKwSpl = $decoded;
                } else {
                    $plusKwSpl[] = (string) $row->plus_kw_spl;
                }
            }
            if ($row->pt_spl !== null && $row->pt_spl !== '') {
                $decoded = json_decode((string) $row->pt_spl, true);
                if (is_array($decoded)) {
                    $ptSpl = $decoded;
                } else {
                    $ptSpl[] = (string) $row->pt_spl;
                }
            }
            $converted[$row->id] = [
                'plus_kw_spl' => json_encode(array_values($plusKwSpl)),
                'pt_spl' => json_encode(array_values($ptSpl)),
            ];
        }

        Schema::table('ads_link_sku_fields', function (Blueprint $table) {
            if (Schema::hasColumn('ads_link_sku_fields', 'plus_kw_spl')) {
                $table->dropColumn('plus_kw_spl');
            }
            if (Schema::hasColumn('ads_link_sku_fields', 'pt_spl')) {
                $table->dropColumn('pt_spl');
            }
        });

        Schema::table('ads_link_sku_fields', function (Blueprint $table) {
            $table->json('plus_kw_spl')->nullable()->after('minus_pt');
            $table->json('pt_spl')->nullable()->after('plus_kw_spl');
        });

        foreach ($converted as $id => $payload) {
            DB::table('ads_link_sku_fields')->where('id', $id)->update($payload);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ads_link_sku_fields')) {
            return;
        }

        $rows = DB::table('ads_link_sku_fields')->select(['id', 'plus_kw_spl', 'pt_spl'])->get();
        $converted = [];
        foreach ($rows as $row) {
            $plus = json_decode((string) ($row->plus_kw_spl ?? '[]'), true);
            $pt = json_decode((string) ($row->pt_spl ?? '[]'), true);
            $converted[$row->id] = [
                'plus_kw_spl' => is_array($plus) && isset($plus[0]) && is_numeric($plus[0]) ? $plus[0] : null,
                'pt_spl' => is_array($pt) && isset($pt[0]) && is_numeric($pt[0]) ? $pt[0] : null,
            ];
        }

        Schema::table('ads_link_sku_fields', function (Blueprint $table) {
            $table->dropColumn(['plus_kw_spl', 'pt_spl']);
        });

        Schema::table('ads_link_sku_fields', function (Blueprint $table) {
            $table->decimal('plus_kw_spl', 12, 2)->nullable()->after('minus_pt');
            $table->decimal('pt_spl', 12, 2)->nullable()->after('plus_kw_spl');
        });

        foreach ($converted as $id => $payload) {
            DB::table('ads_link_sku_fields')->where('id', $id)->update($payload);
        }
    }
};
