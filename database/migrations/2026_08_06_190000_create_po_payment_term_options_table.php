<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('po_payment_term_options')) {
            Schema::create('po_payment_term_options', function (Blueprint $table) {
                $table->id();
                $table->string('value', 500);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
                $table->unique('value');
            });
        }

        $defaults = [
            '20% deposit, balance before shipping.',
            '20% deposit, balance before Release of BL.',
            '10% deposit, balance before Release of BL.',
            '30% deposit, balance before Release of BL.',
            'Each item includes 2% additional free goods for damages.',
        ];
        $now = now();
        foreach ($defaults as $value) {
            DB::table('po_payment_term_options')->updateOrInsert(
                ['value' => $value],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('po_payment_term_options');
    }
};
