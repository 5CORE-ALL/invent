<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_parameters')) {
            Schema::create('audit_parameters', function (Blueprint $table) {
                $table->id();
                $table->string('module', 64)->index();              // e.g. cc_messages, cc_returns, cc_replacements
                $table->string('code', 80)->index();                // stable key e.g. response_within_sla
                $table->string('label', 255);
                $table->text('description')->nullable();
                $table->string('category', 32)->default('core_qa'); // core_qa | channel_compliance
                $table->unsignedSmallInteger('max_score')->default(10);
                $table->decimal('weight', 6, 2)->default(1.00);
                $table->boolean('is_critical')->default(false);     // failing this triggers Critical Failure
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['module', 'code']);
            });
        }

        if (! Schema::hasTable('audit_grades')) {
            Schema::create('audit_grades', function (Blueprint $table) {
                $table->id();
                $table->string('grade', 4);
                $table->decimal('min_score', 6, 2);
                $table->decimal('max_score', 6, 2);
                $table->string('color', 16)->nullable();   // hex / bootstrap variant
                $table->string('description', 255)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('audit_results')) {
            Schema::create('audit_results', function (Blueprint $table) {
                $table->id();
                $table->string('module', 64)->index();
                $table->string('channel', 191)->index();             // matches channel_master.channel
                $table->unsignedBigInteger('channel_master_id')->nullable()->index();
                $table->string('executive_name', 191)->nullable();   // free-text fallback
                $table->unsignedBigInteger('executive_id')->nullable();
                $table->unsignedBigInteger('auditor_id')->nullable();
                $table->string('message_reference', 500)->nullable(); // ticket id / thread url
                $table->date('audit_date')->nullable();
                $table->decimal('core_qa_score', 6, 2)->default(0);          // raw 0-100 within core_qa
                $table->decimal('channel_compliance_score', 6, 2)->default(0); // raw 0-100 within channel_compliance
                $table->decimal('bonus_points', 5, 2)->default(0);            // 0-20
                $table->decimal('total_score', 6, 2)->default(0);             // 0-120
                $table->string('grade', 4)->nullable();
                $table->boolean('has_critical_failure')->default(false);
                $table->text('critical_failure_reasons')->nullable();
                $table->text('auditor_notes')->nullable();
                $table->string('status', 24)->default('submitted');           // draft | submitted
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('audit_result_items')) {
            Schema::create('audit_result_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('audit_result_id')->index();
                $table->unsignedBigInteger('audit_parameter_id')->index();
                $table->string('parameter_code', 80);
                $table->string('parameter_label', 255);
                $table->string('category', 32);
                $table->decimal('score', 6, 2)->default(0);
                $table->unsignedSmallInteger('max_score')->default(10);
                $table->decimal('weight', 6, 2)->default(1.00);
                $table->boolean('is_critical')->default(false);
                $table->boolean('is_critical_failed')->default(false);
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('audit_attachments')) {
            Schema::create('audit_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('audit_result_id')->index();
                $table->string('file_path', 500);
                $table->string('original_name', 255)->nullable();
                $table->string('mime_type', 191)->nullable();
                $table->unsignedInteger('size_bytes')->nullable();
                $table->timestamps();
            });
        }

        $this->seedGradesAndParameters();
    }

    /**
     * Seed default grade bands and CC Messages audit parameters.
     * Safe to re-run: only inserts rows that don't already exist.
     */
    private function seedGradesAndParameters(): void
    {
        $now = now();

        // ---- Grade bands ----
        $grades = [
            ['grade' => 'A+', 'min_score' => 110, 'max_score' => 120, 'color' => '#198754', 'description' => 'Outstanding',  'sort_order' => 1],
            ['grade' => 'A',  'min_score' => 100, 'max_score' => 109.99, 'color' => '#28a745', 'description' => 'Excellent',   'sort_order' => 2],
            ['grade' => 'B',  'min_score' => 90,  'max_score' => 99.99,  'color' => '#0d6efd', 'description' => 'Good',        'sort_order' => 3],
            ['grade' => 'C',  'min_score' => 75,  'max_score' => 89.99,  'color' => '#ffc107', 'description' => 'Average',     'sort_order' => 4],
            ['grade' => 'D',  'min_score' => 60,  'max_score' => 74.99,  'color' => '#fd7e14', 'description' => 'Below Avg',   'sort_order' => 5],
            ['grade' => 'F',  'min_score' => 0,   'max_score' => 59.99,  'color' => '#dc3545', 'description' => 'Fail',        'sort_order' => 6],
        ];

        foreach ($grades as $g) {
            $exists = \DB::table('audit_grades')->where('grade', $g['grade'])->exists();
            if (! $exists) {
                \DB::table('audit_grades')->insert(array_merge($g, [
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        // ---- Default CC Messages parameters ----
        $module = 'cc_messages';

        $coreQa = [
            // [code, label, description, max, weight, critical?]
            ['response_within_sla',     'Response within SLA',                    'First response delivered within the channel SLA window.',                10, 1.5, true],
            ['twentyfour_hr_compliance','24-hour compliance',                     'No customer left waiting beyond 24 hours.',                              10, 1.5, true],
            ['correct_resolution',      'Correct resolution provided',            'Issue actually solved (not just acknowledged).',                          10, 1.5, false],
            ['professional_tone',       'Professional & empathetic tone',         'Polite, respectful, no defensive or rude language.',                      10, 1.0, false],
            ['grammar_quality',         'Grammar, spelling & formatting',         'Clear sentences, no typos, scan-friendly formatting.',                    10, 0.5, false],
            ['ownership',               'Took ownership end-to-end',              'Agent owned the case until closure, no ping-ponging.',                    10, 1.0, false],
            ['follow_up_quality',       'Follow-up quality',                       'Promised callbacks / updates were delivered on time.',                    10, 1.0, false],
            ['customer_satisfaction',   'Likely customer satisfaction',           "Customer's last message indicates they're satisfied.",                    10, 1.0, false],
        ];

        $compliance = [
            ['amazon_safe_communication','Marketplace-safe communication',         'No off-platform contact, links, or violations of marketplace rules.',     10, 1.5, true],
            ['no_review_manipulation',   'No review manipulation',                  'Did not ask, hint, or pressure for reviews/feedback.',                    10, 1.5, true],
            ['proper_escalation',        'Proper escalation handling',              'Escalated to right team / supervisor when needed.',                       10, 1.0, false],
            ['proper_documentation',     'Proper documentation & tagging',          'Notes, dispositions, and tags were applied correctly.',                   10, 1.0, false],
            ['data_privacy',             'Data privacy maintained',                 'No leakage of customer or internal confidential data.',                   10, 1.5, true],
        ];

        $sort = 0;
        foreach ($coreQa as [$code, $label, $desc, $max, $weight, $critical]) {
            $sort++;
            $exists = \DB::table('audit_parameters')->where(['module' => $module, 'code' => $code])->exists();
            if (! $exists) {
                \DB::table('audit_parameters')->insert([
                    'module'      => $module,
                    'code'        => $code,
                    'label'       => $label,
                    'description' => $desc,
                    'category'    => 'core_qa',
                    'max_score'   => $max,
                    'weight'      => $weight,
                    'is_critical' => $critical,
                    'is_active'   => true,
                    'sort_order'  => $sort,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }

        $sort = 0;
        foreach ($compliance as [$code, $label, $desc, $max, $weight, $critical]) {
            $sort++;
            $exists = \DB::table('audit_parameters')->where(['module' => $module, 'code' => $code])->exists();
            if (! $exists) {
                \DB::table('audit_parameters')->insert([
                    'module'      => $module,
                    'code'        => $code,
                    'label'       => $label,
                    'description' => $desc,
                    'category'    => 'channel_compliance',
                    'max_score'   => $max,
                    'weight'      => $weight,
                    'is_critical' => $critical,
                    'is_active'   => true,
                    'sort_order'  => $sort,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_attachments');
        Schema::dropIfExists('audit_result_items');
        Schema::dropIfExists('audit_results');
        Schema::dropIfExists('audit_grades');
        Schema::dropIfExists('audit_parameters');
    }
};
