<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('active_channels')) {
            Schema::create('active_channels', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedTinyInteger('status')->default(1); // 1 = active
                $table->timestamps();
            });
            foreach (['Amazon', 'Flipkart', 'Shopify', 'Website', 'WhatsApp'] as $name) {
                DB::table('active_channels')->insert([
                    'name' => $name,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('active_channels');
    }
};
