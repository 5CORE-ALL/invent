<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('spare_part_packagings')) {
            Schema::create('spare_part_packagings', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255); // name of part / packaging
                $table->text('description')->nullable();
                $table->string('image_path')->nullable();
                $table->unsignedInteger('moq')->nullable();
                $table->unsignedInteger('msl')->nullable();
                $table->string('parent')->nullable(); // product_master.parent
                $table->string('sku', 128)->nullable(); // product_master.sku
                $table->string('supplier')->nullable(); // suppliers.name
                $table->string('created_by')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamps();
                $table->index('sku');
                $table->index('parent');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('spare_part_packagings');
    }
};
