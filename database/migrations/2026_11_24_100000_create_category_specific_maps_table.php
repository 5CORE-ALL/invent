<?php

use App\Models\CategorySpecificMap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        CategorySpecificMap::ensureTable();
    }

    public function down(): void
    {
        Schema::dropIfExists('category_specific_maps');
    }
};
