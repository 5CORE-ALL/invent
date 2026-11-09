<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('feedback_ag_rules')) {
            return;
        }

        Schema::create('feedback_ag_rules', function (Blueprint $table) {
            $table->id();
            // Column the rule looks at: status | avg | reviews | ratings
            $table->string('field', 20)->default('status');
            // = != < <= > >=
            $table->string('operator', 4)->default('=');
            // status: "given" / "none"; other fields: a number.
            $table->string('value', 40);
            // Message text sent to the user (placeholders such as {name} are allowed).
            $table->text('message');
            $table->boolean('allow_reply')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_ag_rules');
    }
};
