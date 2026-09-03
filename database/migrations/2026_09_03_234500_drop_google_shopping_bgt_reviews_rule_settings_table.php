<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('google_shopping_bgt_reviews_rule_settings');
    }

    public function down(): void
    {
        // Reviews BGT was removed from Google Shopping; do not recreate.
    }
};
