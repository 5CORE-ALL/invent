<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('outgoing_reasons')) {
            return;
        }

        Schema::create('outgoing_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('name');
        });

        $defaults = [
            'Issue(Dispatch)', 'Issue(Label)', 'Issue(Quality)', 'Issue(Packaging)',
            'Issue(Carrier)', 'Issue(Lost)', 'Issue(WAC)', 'Issue(listing)', 'Issue(pricing)',
            'Gift', 'Promotion', 'FBA', 'Issue(Other)',
        ];
        foreach ($defaults as $i => $name) {
            DB::table('outgoing_reasons')->insert([
                'name' => $name,
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outgoing_reasons');
    }
};
