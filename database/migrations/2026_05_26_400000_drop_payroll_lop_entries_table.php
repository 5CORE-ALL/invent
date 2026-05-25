<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('payroll_lop_entries');
    }

    public function down(): void
    {
        // LOP feature removed — no restore
    }
};
