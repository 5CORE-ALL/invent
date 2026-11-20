<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qc_summary_common_issues')) {
            Schema::create('qc_summary_common_issues', function (Blueprint $table) {
                $table->id();
                $table->string('mode', 10); // sku | parent
                $table->char('key_hash', 32)->index(); // md5(mode|row_key)
                $table->text('row_key'); // SUPPLIER||SKU-or-PARENT, same key the summary pages use
                $table->string('text', 255); // common issue added by hand
                $table->string('created_by')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_summary_common_issues');
    }
};
