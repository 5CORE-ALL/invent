<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_faqs')) {
            return;
        }

        Schema::create('customer_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('group_name')->nullable();
            $table->text('faq');
            $table->text('answers')->nullable();
            $table->string('customer_type')->nullable(); // Retail / Wholesale / B2B / Marketplace / Shopify / Amazon ...
            $table->json('dept')->nullable(); // owning departments
            $table->string('severity', 20)->default('medium'); // low | medium | high | critical
            $table->string('status', 30)->default('open'); // open | in_progress | escalated | resolved | closed
            $table->text('type_variant')->nullable();
            $table->text('what')->nullable();
            $table->string('link')->nullable();
            $table->string('link2')->nullable();
            $table->string('sop')->nullable();
            $table->string('video')->nullable();
            $table->text('action')->nullable();
            $table->text('ca')->nullable();
            $table->text('plus_action')->nullable();
            $table->text('messages')->nullable();

            // Escalation matrix — who to escalate to and when (per FAQ row).
            $table->string('escalation_l1_role')->nullable();
            $table->string('escalation_l1_name')->nullable();
            $table->string('escalation_l1_email')->nullable();
            $table->string('escalation_l1_sla')->nullable(); // e.g. "2h", "Same day"
            $table->string('escalation_l2_role')->nullable();
            $table->string('escalation_l2_name')->nullable();
            $table->string('escalation_l2_email')->nullable();
            $table->string('escalation_l2_sla')->nullable();
            $table->string('escalation_l3_role')->nullable();
            $table->string('escalation_l3_name')->nullable();
            $table->string('escalation_l3_email')->nullable();
            $table->string('escalation_l3_sla')->nullable();

            // Live escalation state (the single most-recent escalation for the row).
            $table->unsignedTinyInteger('current_escalation_level')->default(0); // 0 = not escalated
            $table->timestamp('escalated_at')->nullable();
            $table->string('escalated_by_email')->nullable();
            $table->string('escalated_to_email')->nullable();
            $table->text('escalation_reason')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by_email')->nullable();
            $table->text('resolution_note')->nullable();

            // Append-only escalation timeline.
            $table->json('escalation_log')->nullable();

            // Audit / history.
            $table->string('created_by_email')->nullable();
            $table->string('updated_by_email')->nullable();
            $table->json('edit_history')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'current_escalation_level']);
            $table->index('customer_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_faqs');
    }
};
