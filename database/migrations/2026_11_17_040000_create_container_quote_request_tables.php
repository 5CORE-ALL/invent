<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('container_quote_requests')) {
            Schema::create('container_quote_requests', function (Blueprint $table) {
                $table->id();
                $table->string('container_number')->index();
                $table->unsignedBigInteger('forwarder_id')->nullable();
                $table->string('forwarder_name');
                $table->string('forwarder_email');
                $table->string('token', 64)->unique();
                $table->string('sent_by')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();
                $table->unique(['container_number', 'forwarder_email'], 'cqr_container_email_unique');
            });
        }

        if (! Schema::hasTable('container_quote_settings')) {
            Schema::create('container_quote_settings', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->longText('value')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('container_quote_received')) {
            Schema::table('container_quote_received', function (Blueprint $table) {
                if (! Schema::hasColumn('container_quote_received', 'request_id')) {
                    $table->unsignedBigInteger('request_id')->nullable()->index()->after('container_number');
                }
                if (! Schema::hasColumn('container_quote_received', 'source')) {
                    $table->string('source', 20)->default('internal')->after('request_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('container_quote_received')) {
            Schema::table('container_quote_received', function (Blueprint $table) {
                foreach (['request_id', 'source'] as $col) {
                    if (Schema::hasColumn('container_quote_received', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        Schema::dropIfExists('container_quote_settings');
        Schema::dropIfExists('container_quote_requests');
    }
};