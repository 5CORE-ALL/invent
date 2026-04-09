<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('review_alerts')) {
            return;
        }

        Schema::create('review_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 100)->index();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->enum('alert_type', ['high_negative_rate', 'top_issue', 'spike_detected'])->index();
            $table->text('message');
            $table->enum('status', ['open', 'closed'])->default('open')->index();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_alerts');
    }
};
