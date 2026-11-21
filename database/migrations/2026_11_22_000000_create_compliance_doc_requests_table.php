<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved copy of every compliance-document request mail (Compliance <Badge> pages -> Mail badge): the mail itself,
 * who it went to, and the files attached to it. status: draft (saved only) | sent | failed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('compliance_doc_requests')) {
            return;
        }

        Schema::create('compliance_doc_requests', function (Blueprint $table) {
            $table->id();
            $table->string('parent', 255)->index();
            $table->string('field', 64)->index();
            $table->string('supplier', 255)->nullable();
            $table->text('to_emails')->nullable();
            $table->string('from_email', 255)->nullable();
            $table->string('from_name', 255)->nullable();
            $table->string('subject', 500);
            $table->longText('body_html');
            $table->text('note')->nullable();
            $table->json('attachments')->nullable(); // [{name, path, size, mime, generated}]
            $table->string('status', 16)->default('draft')->index();
            $table->text('error')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 255)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_doc_requests');
    }
};
