<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('channel_metric_dot_trends')) {
            return;
        }

        Schema::create('channel_metric_dot_trends', function (Blueprint $table) {
            $table->unsignedSmallInteger('window')->primary();
            $table->longText('payload');
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_metric_dot_trends');
    }
};
