<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('upcoming_containers');
    }

    public function down(): void
    {
        // Table was removed with the Coming Container page; no restore.
    }
};
