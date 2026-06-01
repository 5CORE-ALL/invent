<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipping_platforms')) {
            Schema::create('shipping_platforms', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('shipping_report_lines')) {
            Schema::create('shipping_report_lines', function (Blueprint $table) {
                $table->id();
                $table->date('report_date');
                $table->string('time_slot', 20);
                $table->foreignId('shipping_platform_id')->constrained('shipping_platforms')->cascadeOnDelete();
                $table->boolean('is_cleared')->default(false);
                $table->string('order_number', 191)->nullable();
                $table->string('sku', 191)->nullable();
                $table->text('reason')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['report_date', 'time_slot', 'shipping_platform_id'], 'shipping_report_slot_platform_unique');
            });
        }

        if (! Schema::hasTable('shipping_followups')) {
            Schema::create('shipping_followups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shipping_report_line_id')->nullable()->constrained('shipping_report_lines')->nullOnDelete();
                $table->foreignId('shipping_platform_id')->nullable()->constrained('shipping_platforms')->nullOnDelete();
                $table->date('report_date')->nullable();
                $table->string('time_slot', 20)->nullable();
                $table->string('order_number', 191);
                $table->string('sku', 191);
                $table->text('reason')->nullable();
                $table->string('status', 20)->default('open');
                $table->timestamp('resolved_at')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['status', 'created_at']);
            });
        }

        if (! Schema::hasTable('shipping_followup_archives')) {
            Schema::create('shipping_followup_archives', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('original_followup_id')->nullable();
                $table->unsignedBigInteger('shipping_report_line_id')->nullable();
                $table->unsignedBigInteger('shipping_platform_id')->nullable();
                $table->date('report_date')->nullable();
                $table->string('time_slot', 20)->nullable();
                $table->string('order_number', 191);
                $table->string('sku', 191);
                $table->text('reason')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('created_at_followup')->nullable();
                $table->timestamp('archived_at')->useCurrent();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('shipping_platforms')) {
            return;
        }

        if (DB::table('shipping_platforms')->count() > 0) {
            return;
        }

        $names = config('shipping.default_platforms', []);
        $order = 0;
        foreach ($names as $name) {
            DB::table('shipping_platforms')->insert([
                'name' => $name,
                'sort_order' => $order++,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_followup_archives');
        Schema::dropIfExists('shipping_followups');
        Schema::dropIfExists('shipping_report_lines');
        Schema::dropIfExists('shipping_platforms');
    }
};
