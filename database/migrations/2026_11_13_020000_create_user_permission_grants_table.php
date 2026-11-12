<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra module access for named people, added on top of their role's permissions.
 * One row per (user, module); `actions` is a JSON list of view / create / edit / delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_permission_grants')) {
            return;
        }

        Schema::create('user_permission_grants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('module', 191);
            $table->json('actions');
            $table->unsignedBigInteger('granted_by')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'module']);
            $table->index('module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission_grants');
    }
};
