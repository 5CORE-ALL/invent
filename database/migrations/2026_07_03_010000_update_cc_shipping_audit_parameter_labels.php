<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename cc_shipping audit parameter labels: "Correct" → "Required"
     * for package weight and dimensions fields.
     */
    public function up(): void
    {
        if (! Schema::hasTable('audit_parameters')) {
            return;
        }

        $updates = [
            'correct_weight'     => 'Required package weight entered',
            'correct_dimensions' => 'Required package dimensions entered',
        ];

        foreach ($updates as $code => $label) {
            DB::table('audit_parameters')
                ->where('module', 'cc_shipping')
                ->where('code', $code)
                ->update(['label' => $label, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('audit_parameters')) {
            return;
        }

        $reverts = [
            'correct_weight'     => 'Correct package weight entered',
            'correct_dimensions' => 'Correct package dimensions entered',
        ];

        foreach ($reverts as $code => $label) {
            DB::table('audit_parameters')
                ->where('module', 'cc_shipping')
                ->where('code', $code)
                ->update(['label' => $label, 'updated_at' => now()]);
        }
    }
};
