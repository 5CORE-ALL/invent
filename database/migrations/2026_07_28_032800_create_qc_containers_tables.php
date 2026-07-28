<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qc_containers')) {
            Schema::create('qc_containers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('transit_container_id')->nullable()->index();
                $table->unsignedBigInteger('arrived_container_id')->nullable()->index();
                $table->string('tab_name')->nullable();
                $table->string('supplier_name')->nullable();
                $table->string('company_name')->nullable();
                $table->string('hsn_code', 32)->nullable();
                $table->string('our_sku')->nullable();
                $table->string('parent')->nullable();
                $table->integer('no_of_units')->nullable();
                $table->integer('total_ctn')->nullable();
                $table->decimal('rate', 10, 2)->nullable();
                $table->string('unit')->nullable();
                $table->string('status')->nullable();
                $table->string('changes')->nullable();
                $table->string('package_size')->nullable();
                $table->string('product_size_link')->nullable();
                $table->string('comparison_link')->nullable();
                $table->string('order_link')->nullable();
                $table->string('image_src')->nullable();
                $table->text('photos')->nullable();
                $table->longText('specification')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('qc_container_history')) {
            Schema::create('qc_container_history', function (Blueprint $table) {
                $table->id();
                $table->string('action_type', 50);
                $table->unsignedBigInteger('qc_container_id')->nullable()->index();
                $table->string('from_tab')->nullable();
                $table->string('to_tab')->nullable();
                $table->string('our_sku')->nullable();
                $table->text('details')->nullable();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();

                $table->index(['action_type', 'created_at']);
                $table->index(['our_sku', 'created_at']);
                $table->index(['to_tab', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_container_history');
        Schema::dropIfExists('qc_containers');
    }
};
