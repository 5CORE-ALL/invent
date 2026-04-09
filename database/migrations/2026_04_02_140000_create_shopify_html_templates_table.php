<?php

use App\Support\ShopifyHtmlDefaultTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shopify_html_templates')) {
            return;
        }

        Schema::create('shopify_html_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 191)->nullable()->index();
            $table->string('marketplace', 32)->default('all')->index();
            $table->string('template_name', 255);
            $table->longText('html_content');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        $now = now();
        foreach (ShopifyHtmlDefaultTemplates::definitions() as $row) {
            DB::table('shopify_html_templates')->insert([
                'user_id' => null,
                'sku' => null,
                'marketplace' => $row['marketplace'],
                'template_name' => $row['template_name'],
                'html_content' => $row['html_content'],
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_html_templates');
    }
};
