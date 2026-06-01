<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shopify_html_description_templates')) {
            return;
        }

        Schema::create('shopify_html_description_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->longText('html_content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_html_description_templates');
    }
};
