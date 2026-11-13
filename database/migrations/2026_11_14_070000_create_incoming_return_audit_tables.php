<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Shared, editable checklist template for auditing claimed incentives.
        if (! Schema::hasTable('incoming_return_audit_points')) {
            Schema::create('incoming_return_audit_points', function (Blueprint $table) {
                $table->id();
                $table->text('description');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });

            $defaults = [
                'Recovered Cost $ matches LP × quantity for the returned items.',
                'Incentive Rs. equals Recovered Cost $ (rounded off) — the claim is calculated correctly.',
                'Order ID is entered and matches the marketplace order / return.',
                'Channel is correct for the order.',
                'Item was actually received and restocked (warehouse / physical check).',
                'Restock Fees entered are reasonable and supported.',
                'No duplicate claim for the same order / SKU.',
            ];
            $now = now();
            foreach ($defaults as $i => $text) {
                DB::table('incoming_return_audit_points')->insert([
                    'description' => $text,
                    'sort_order' => $i + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // One row per completed audit (history).
        if (! Schema::hasTable('incoming_return_audits')) {
            Schema::create('incoming_return_audits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_name', 191)->nullable();
                $table->decimal('recovered_usd', 14, 2)->nullable();
                $table->decimal('incentive_rs', 14, 2)->nullable();
                $table->unsignedInteger('passed_count')->default(0);
                $table->unsignedInteger('total_count')->default(0);
                $table->json('items');
                $table->json('sample')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_return_audits');
        Schema::dropIfExists('incoming_return_audit_points');
    }
};
