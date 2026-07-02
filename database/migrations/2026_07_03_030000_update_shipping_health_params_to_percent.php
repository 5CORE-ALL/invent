<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shipping health parameters use plain-text required targets (e.g. "100%")
     * and percent current values entered at each audit.
     */
    public function up(): void
    {
        if (! Schema::hasTable('shipping_health_parameters')) {
            return;
        }

        $rows = DB::table('shipping_health_parameters')->orderBy('id')->get();

        foreach ($rows as $row) {
            $required = trim((string) ($row->required_value ?? ''));
            if ($row->value_type === 'boolean') {
                $required = in_array(strtolower($required), ['1', 'true', 'yes', 'on'], true) ? '100%' : '0%';
            } elseif ($required !== '' && is_numeric($required) && ! str_contains($required, '%')) {
                $required = $required.'%';
            } elseif ($required === '') {
                $required = '100%';
            }

            DB::table('shipping_health_parameters')->where('id', $row->id)->update([
                'value_type'     => 'percent',
                'required_value' => $required,
                'updated_at'     => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No rollback — prior boolean values cannot be restored reliably.
    }
};
