<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tiktok_sheet_data');
    }

    public function down(): void
    {
        // Sheet sync removed — table is not recreated.
    }
};
