<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('grandparents')) {
            Schema::create('grandparents', function (Blueprint $table) {
                $table->id();
                $table->string('grandparent');
                $table->timestamps();
                $table->unique('grandparent', 'grandparents_name_unique');
            });
        }

        $names = $this->namesFromSpendFile();
        $now = now();

        foreach ($names as $name) {
            DB::table('grandparents')->updateOrInsert(
                ['grandparent' => $name],
                [
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        // Do not drop — table may already be used by Grandparent Master / product_master.
    }

    /**
     * Unique grandparent names from project-root /spend (header GRANDPARENT skipped).
     *
     * @return list<string>
     */
    private function namesFromSpendFile(): array
    {
        $path = base_path('spend');
        if (! is_readable($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $names = [];
        foreach ($lines as $line) {
            $name = preg_replace('/\s+/', ' ', trim((string) $line));
            if ($name === '' || strtoupper($name) === 'GRANDPARENT') {
                continue;
            }
            $names[strtoupper($name)] = $name;
        }
        ksort($names);

        return array_values($names);
    }
};
