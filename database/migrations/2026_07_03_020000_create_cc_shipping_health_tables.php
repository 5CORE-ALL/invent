<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipping_health_parameters')) {
            Schema::create('shipping_health_parameters', function (Blueprint $table) {
                $table->id();
                $table->string('code', 80)->unique();
                $table->string('label');
                $table->text('description')->nullable();
                $table->string('value_type', 20)->default('boolean'); // boolean | number | text
                $table->string('required_value', 255)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('shipping_health_assessments')) {
            Schema::create('shipping_health_assessments', function (Blueprint $table) {
                $table->id();
                $table->string('channel', 191)->index();
                $table->unsignedBigInteger('channel_id')->nullable()->index();
                $table->decimal('health_score', 5, 2)->nullable();
                $table->text('notes')->nullable();
                $table->dateTime('assessed_at');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                if (Schema::hasTable('channel_master')) {
                    $table->foreign('channel_id')
                        ->references('id')->on('channel_master')
                        ->nullOnDelete();
                }
            });
        }

        if (! Schema::hasTable('shipping_health_assessment_items')) {
            Schema::create('shipping_health_assessment_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('assessment_id');
                $table->unsignedBigInteger('parameter_id')->nullable();
                $table->string('parameter_label');
                $table->string('value_type', 20)->default('boolean');
                $table->string('required_value', 255)->nullable();
                $table->string('current_value', 255)->nullable();
                $table->boolean('meets_required')->default(false);
                $table->timestamps();

                $table->foreign('assessment_id')
                    ->references('id')->on('shipping_health_assessments')
                    ->cascadeOnDelete();

                $table->foreign('parameter_id')
                    ->references('id')->on('shipping_health_parameters')
                    ->nullOnDelete();
            });
        }

        $this->seedDefaultParameters();
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_health_assessment_items');
        Schema::dropIfExists('shipping_health_assessments');
        Schema::dropIfExists('shipping_health_parameters');
    }

    private function seedDefaultParameters(): void
    {
        if (! Schema::hasTable('shipping_health_parameters')) {
            return;
        }

        $now = now();
        $rows = [
            ['all_shipping_cleared', 'All Shipping cleared', 'All pending shipping tasks cleared for the platform.', 'percent', '100%', 1],
            ['cancelled_not_shipped', 'Cancelled orders were not shipped', 'No cancelled orders were shipped out.', 'percent', '100%', 2],
            ['weight_dimensions_declared', 'Required weight and dimensions declared', 'Package weight and dimensions entered correctly.', 'percent', '100%', 3],
            ['lowest_label_cost', 'Correct and lowest possible label cost purchased', 'Best eligible carrier/service rate used.', 'percent', '100%', 4],
            ['combined_shipment_message', 'Combined shipment message sent to buyers', 'Buyers notified when orders are combined.', 'percent', '100%', 5],
            ['split_shipment_tracking', 'Split shipment message and tracking updated', 'Split shipments communicated with tracking.', 'percent', '100%', 6],
        ];

        foreach ($rows as [$code, $label, $desc, $type, $required, $sort]) {
            $exists = DB::table('shipping_health_parameters')->where('code', $code)->exists();
            if ($exists) {
                continue;
            }
            DB::table('shipping_health_parameters')->insert([
                'code'           => $code,
                'label'          => $label,
                'description'    => $desc,
                'value_type'     => $type,
                'required_value' => $required,
                'sort_order'     => $sort,
                'is_active'      => true,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }
    }
};
