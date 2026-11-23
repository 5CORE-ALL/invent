<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comparison_specifications')) {
            return;
        }

        Schema::create('comparison_specifications', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('created_by', 191)->nullable();
            $table->timestamps();
        });

        $this->seedFromExistingSheets();
    }

    /**
     * Seed the master list with the labels already used in the Spec column of existing sheets
     * (only labels used on 10+ sheets, to skip one-off typos). Most-used first.
     */
    private function seedFromExistingSheets(): void
    {
        if (! Schema::hasTable('comparison_data')) {
            return;
        }

        $counts = [];
        $display = [];

        foreach (DB::table('comparison_data')->select('sheet_data')->orderBy('id')->cursor() as $row) {
            $data = json_decode((string) $row->sheet_data, true);
            $cells = is_array($data) ? ($data['cells'] ?? []) : [];
            if (! is_array($cells)) {
                continue;
            }
            foreach ($cells as $cellRow) {
                if (! is_array($cellRow)) {
                    continue;
                }
                $label = trim((string) ($cellRow[2] ?? ''));
                if ($label === '' || mb_strlen($label) > 60 || preg_match('/^(https?:|data:|\[)/i', $label) || is_numeric($label)) {
                    continue;
                }
                $key = mb_strtolower($label);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
                $display[$key] ??= $label;
            }
        }

        arsort($counts);
        $now = now();
        $order = 0;
        $insert = [];
        foreach ($counts as $key => $count) {
            if ($count < 10) {
                continue;
            }
            $insert[] = [
                'name' => $display[$key],
                'sort_order' => ++$order,
                'created_by' => 'seed',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($insert, 200) as $chunk) {
            DB::table('comparison_specifications')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comparison_specifications');
    }
};
