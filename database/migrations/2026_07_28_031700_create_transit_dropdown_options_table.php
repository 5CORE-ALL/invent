<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transit_dropdown_options')) {
            Schema::create('transit_dropdown_options', function (Blueprint $table) {
                $table->id();
                $table->string('field', 32); // imp_name | hsn_code
                $table->string('value', 191);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
                $table->unique(['field', 'value']);
                $table->index(['field', 'last_used_at']);
            });
        }

        $now = now();
        foreach (['5 core', 'K cube'] as $imp) {
            DB::table('transit_dropdown_options')->updateOrInsert(
                ['field' => 'imp_name', 'value' => $imp],
                ['updated_at' => $now, 'created_at' => $now]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transit_dropdown_options');
    }
};
