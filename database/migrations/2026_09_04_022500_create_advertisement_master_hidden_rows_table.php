<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('advertisement_master_hidden_rows')) {
            Schema::create('advertisement_master_hidden_rows', function (Blueprint $table) {
                $table->id();
                $table->string('channel_key', 191)->unique();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('advertisement_master_hidden_rows')) {
            $key = 'Shopify · Facebook';
            $exists = DB::table('advertisement_master_hidden_rows')->where('channel_key', $key)->exists();
            if (! $exists) {
                DB::table('advertisement_master_hidden_rows')->insert([
                    'channel_key' => $key,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisement_master_hidden_rows');
    }
};
