<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comparison_quote_requests')) {
            return;
        }

        Schema::create('comparison_quote_requests', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->string('parent')->nullable();
            $table->string('supplier_name');
            $table->string('supplier_key', 191);
            $table->string('supplier_email')->nullable();
            $table->string('token', 64)->unique();
            $table->string('sent_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->longText('submitted_values')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['sku', 'supplier_key'], 'cqr_sku_supplier_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comparison_quote_requests');
    }
};
