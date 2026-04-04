<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('shopify_html_templates');
    }

    public function down(): void
    {
        // Intentionally empty — Description Master 2.0 replaces template library.
    }
};
