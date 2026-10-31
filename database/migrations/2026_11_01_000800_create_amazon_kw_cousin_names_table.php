<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Names for KW cousin groups. The name is stored once per member campaign (all members of a
     * group share the same name), so it follows the campaign whatever the group looks like later.
     */
    public function up(): void
    {
        if (Schema::hasTable('amazon_kw_cousin_names')) {
            return;
        }

        Schema::create('amazon_kw_cousin_names', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_norm', 191)->unique();
            $table->string('campaign', 512);
            $table->string('name', 191);
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->index('name', 'amz_kw_cousin_name_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_kw_cousin_names');
    }
};
