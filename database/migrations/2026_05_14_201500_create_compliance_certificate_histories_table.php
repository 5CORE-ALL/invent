<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_certificate_histories', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->unsignedBigInteger('certificate_id')->nullable()->index();
            $table->string('action'); // updated, uploaded, deleted
            $table->text('description')->nullable(); // human-readable summary
            $table->json('changes')->nullable(); // detailed before/after diff
            $table->text('files_uploaded')->nullable(); // comma-separated original file names
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_certificate_histories');
    }
};
