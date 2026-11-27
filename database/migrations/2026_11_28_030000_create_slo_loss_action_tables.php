<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only history of what was decided for a SKU in the "Products selling at a loss" modal.
        // The newest row of a SKU is its current state.
        if (! Schema::hasTable('slo_loss_actions')) {
            Schema::create('slo_loss_actions', function (Blueprint $table) {
                $table->id();
                $table->string('sku_key', 191)->index()->comment('UPPER(TRIM(sku))');
                $table->string('sku', 191);
                $table->string('action', 16)->comment('fixed | ignored | open');
                $table->string('reason', 191)->nullable()->comment('only for ignored');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_name', 191)->nullable();
                $table->timestamps();
            });
        }

        // Reasons offered when a SKU is ignored (users can add their own).
        if (! Schema::hasTable('slo_ignore_reasons')) {
            Schema::create('slo_ignore_reasons', function (Blueprint $table) {
                $table->id();
                $table->string('name', 191)->unique();
                $table->unsignedInteger('sort')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (DB::table('slo_ignore_reasons')->where('name', 'Clearance')->doesntExist()) {
            DB::table('slo_ignore_reasons')->insert([
                'name' => 'Clearance',
                'sort' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('slo_loss_actions');
        Schema::dropIfExists('slo_ignore_reasons');
    }
};
