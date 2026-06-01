<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_exec_options')) {
            Schema::create('purchase_exec_options', function (Blueprint $table) {
                $table->id();
                $table->string('name', 64)->unique();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('purchase_page_exec_assignments')) {
            Schema::create('purchase_page_exec_assignments', function (Blueprint $table) {
                $table->id();
                $table->string('page_key', 32)->unique();
                $table->string('assigned_exec', 64)->nullable();
                $table->timestamps();
            });
        }

        $defaults = ['Ajay', 'Atin', 'Nitish', 'Sruti', 'Candy'];
        foreach ($defaults as $i => $name) {
            DB::table('purchase_exec_options')->insertOrIgnore([
                'name' => $name,
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (['to_order', 'mip', 'r2s'] as $pageKey) {
            DB::table('purchase_page_exec_assignments')->insertOrIgnore([
                'page_key' => $pageKey,
                'assigned_exec' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_page_exec_assignments');
        Schema::dropIfExists('purchase_exec_options');
    }
};
