<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_followups')) {
            return;
        }

        if (!Schema::hasTable('channel_master')) {
            return;
        }

        Schema::create('customer_followups', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_id')->unique();
            $table->string('order_id')->nullable();
            $table->foreignId('channel_master_id')->nullable()->constrained('channel_master')->nullOnDelete();
            $table->string('customer_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('issue_type', 32);
            $table->string('status', 32);
            $table->string('priority', 32);
            $table->date('followup_date');
            $table->time('followup_time')->nullable();
            $table->dateTime('next_followup_at')->nullable();
            $table->string('assigned_executive')->nullable();
            $table->text('comments')->nullable();
            $table->text('internal_remarks')->nullable();
            $table->string('reference_link', 512)->nullable();
            $table->timestamps();
            $table->index(['status', 'priority']);
            $table->index('next_followup_at');
        });

        $chId = DB::table('channel_master')
            ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
            ->orderBy('id')
            ->value('id');

        if ($chId && !DB::table('customer_followups')->where('ticket_id', 'TKT-DEMO-001')->exists()) {
            $now = now();
            DB::table('customer_followups')->insert([
                [
                    'ticket_id' => 'TKT-DEMO-001',
                    'order_id' => 'ORD-1001',
                    'channel_master_id' => $chId,
                    'customer_name' => 'Sample Customer',
                    'email' => 'sample@example.com',
                    'phone' => '555-0100',
                    'issue_type' => 'Refund',
                    'status' => 'Pending',
                    'priority' => 'High',
                    'followup_date' => $now->toDateString(),
                    'followup_time' => '10:00:00',
                    'next_followup_at' => $now->copy()->addDay(),
                    'assigned_executive' => 'Executive A',
                    'comments' => 'Customer waiting for refund status.',
                    'internal_remarks' => 'Check with finance.',
                    'reference_link' => 'https://example.com/ticket/1',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'ticket_id' => 'TKT-DEMO-002',
                    'order_id' => 'ORD-1002',
                    'channel_master_id' => $chId,
                    'customer_name' => 'Jane Doe',
                    'email' => 'jane@example.com',
                    'phone' => '555-0200',
                    'issue_type' => 'Delivery',
                    'status' => 'In Progress',
                    'priority' => 'Medium',
                    'followup_date' => $now->copy()->subDay()->toDateString(),
                    'followup_time' => '14:30:00',
                    'next_followup_at' => $now->copy()->subHours(2),
                    'assigned_executive' => 'Executive B',
                    'comments' => 'Shipment delayed.',
                    'internal_remarks' => null,
                    'reference_link' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_followups');
    }
};
