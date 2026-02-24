<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topdawg_products', function (Blueprint $table) {
            $table->string('image_src')->nullable()->after('tdid');
        });
    }

    public function down(): void
    {
        Schema::table('topdawg_products', function (Blueprint $table) {
            $table->dropColumn('image_src');
        });
    }
};
