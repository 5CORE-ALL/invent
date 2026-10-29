<?php

use App\Support\AutomatedTaskArchive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        AutomatedTaskArchive::ensureTable();
    }

    public function down(): void
    {
        Schema::dropIfExists(AutomatedTaskArchive::TABLE);
    }
};
