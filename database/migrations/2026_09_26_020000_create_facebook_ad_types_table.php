<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User-created Ad Type options for /facebook-all-ads-sheet.
 * Built-in types (GROUP VIDEO, WHOLESALE, …) stay in code; this table
 * only stores extras added from the "Add Type" control.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_ad_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 32)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_ad_types');
    }
};
