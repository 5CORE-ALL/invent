<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pricing_errors_fix_calculated_data');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('lmps');
    }

    public function down(): void
    {
        // Intentionally empty — PEF cache / promo tables removed.
    }
};
