<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_care_health_channel_data')) {
            Schema::create('customer_care_health_channel_data', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('channel_id')->unique();
                $table->string('link', 2048)->nullable();
                $table->decimal('required_parameter', 8, 2)->nullable();
                $table->decimal('current_parameter', 8, 2)->nullable();
                $table->text('summary_issues')->nullable();
                $table->text('root_cause_found')->nullable();
                $table->text('action_to_fix')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();

                $table->foreign('channel_id')
                    ->references('id')
                    ->on('channel_master')
                    ->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('customer_care_health_status_histories')) {
            Schema::create('customer_care_health_status_histories', function (Blueprint $table) {
                $table->id();
                $table->date('snapshot_date')->unique();
                $table->unsignedInteger('red_count')->default(0);
                $table->unsignedInteger('yellow_count')->default(0);
                $table->unsignedInteger('green_count')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_care_health_status_histories');
        Schema::dropIfExists('customer_care_health_channel_data');
    }
};
