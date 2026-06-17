<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * - Adds optional `module` column to audit_grades (so each module can have
     *   its own grade bands; when null the row is treated as a global default).
     * - Seeds shipping-specific grade bands (A+ 95–100 down to F).
     * - Seeds the full Shipping Label Executive audit parameter set.
     *
     * Idempotent — only inserts/alters what is missing.
     */
    public function up(): void
    {
        if (! Schema::hasTable('audit_grades') || ! Schema::hasTable('audit_parameters')) {
            return;
        }

        if (! Schema::hasColumn('audit_grades', 'module')) {
            Schema::table('audit_grades', function (Blueprint $table) {
                $table->string('module', 64)->nullable()->after('id')->index();
            });
        }

        $this->seedShippingGrades();
        $this->seedShippingParameters();
    }

    public function down(): void
    {
        // Remove the seeded shipping rows (keeps any operator-created data).
        DB::table('audit_grades')->where('module', 'cc_shipping')->delete();
        DB::table('audit_parameters')->where('module', 'cc_shipping')->delete();

        if (Schema::hasColumn('audit_grades', 'module')) {
            Schema::table('audit_grades', function (Blueprint $table) {
                $table->dropColumn('module');
            });
        }
    }

    private function seedShippingGrades(): void
    {
        $now = now();

        $grades = [
            ['grade' => 'A+', 'min_score' => 95, 'max_score' => 100,   'color' => '#198754', 'description' => 'Outstanding', 'sort_order' => 1],
            ['grade' => 'A',  'min_score' => 90, 'max_score' => 94.99, 'color' => '#28a745', 'description' => 'Excellent',  'sort_order' => 2],
            ['grade' => 'B',  'min_score' => 80, 'max_score' => 89.99, 'color' => '#0d6efd', 'description' => 'Good',       'sort_order' => 3],
            ['grade' => 'C',  'min_score' => 70, 'max_score' => 79.99, 'color' => '#ffc107', 'description' => 'Average',    'sort_order' => 4],
            ['grade' => 'D',  'min_score' => 60, 'max_score' => 69.99, 'color' => '#fd7e14', 'description' => 'Below Avg',  'sort_order' => 5],
            ['grade' => 'F',  'min_score' => 0,  'max_score' => 59.99, 'color' => '#dc3545', 'description' => 'Fail',       'sort_order' => 6],
        ];

        foreach ($grades as $g) {
            $exists = DB::table('audit_grades')
                ->where('module', 'cc_shipping')
                ->where('grade', $g['grade'])
                ->exists();
            if (! $exists) {
                DB::table('audit_grades')->insert(array_merge($g, [
                    'module'     => 'cc_shipping',
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    private function seedShippingParameters(): void
    {
        $now    = now();
        $module = 'cc_shipping';

        // Each row: [code, label, description, max, weight, is_critical?, category]
        // Categories collapse the 7 spec subgroups into the existing 2-bucket
        // model used by the scoring formula:
        //   core_qa            = label/order/product/cost/process accuracy
        //   channel_compliance = carrier rules, marketplace, customs, docs, SOP
        $core = [
            // ----- Label Accuracy -----
            ['correct_platform_selected',     'Correct shipping platform selected',          'UPS / FedEx / USPS / Amazon Shipping / DHL etc. matches the order.', 10, 1.5, true],
            ['correct_service_selected',      'Correct shipping service selected',           'Ground / Express / 2-Day / Overnight matches buyer SLA.',           10, 1.2, false],
            ['correct_weight',                'Correct package weight entered',              'Weight matches actual; protects against carrier surcharges.',       10, 1.5, true],
            ['correct_dimensions',            'Correct package dimensions entered',          'L × W × H matches actual; avoids dimensional weight charges.',     10, 1.0, false],
            ['cost_optimization',             'Correct shipping cost optimization',          'Cheapest eligible service used.',                                    10, 1.0, false],
            ['correct_package_type',          'Correct package type selected',               'Box / envelope / poly mailer / pak as per item.',                    10, 0.8, false],
            ['hazmat_handling',               'Correct hazardous / non-hazardous handling',  'HazMat flag, label, and surcharge applied if needed.',               10, 1.5, true],
            ['residential_commercial',        'Correct residential / commercial classification', 'Avoids surcharge & wrong delivery profile.',                     10, 0.6, false],
            ['signature_requirement',         'Correct signature requirement',               'Signature required vs. not, per order/policy.',                      10, 0.6, false],

            // ----- Customer & Order Validation -----
            ['correct_customer_name',         'Correct customer name on label',              'Matches buyer / ship-to name.',                                      10, 0.6, false],
            ['correct_address',               'Correct shipping address',                    'Street, city, state, country exactly as on order.',                  10, 1.5, true],
            ['correct_zip',                   'Correct ZIP / postal code',                   'Postal code matches address; carrier accepts it.',                   10, 1.0, false],
            ['address_verification',          'Address verification completed',              'Carrier address validation run; corrections applied.',               10, 0.8, false],
            ['correct_phone',                 'Correct phone number',                        'Required for premium / international / signature shipments.',        10, 0.4, false],
            ['order_matched',                 'Correct order matched',                       'Tracking is bound to the right order ID.',                           10, 1.0, false],
            ['no_duplicate_shipment',         'Duplicate shipment prevented',                'No second label generated for an already-shipped order.',            10, 1.5, true],

            // ----- Inventory & Product Validation -----
            ['correct_sku',                   'Correct SKU shipped',                         'SKU on label matches what was actually packed.',                     10, 1.5, true],
            ['correct_quantity',              'Correct quantity shipped',                    'Qty matches order; not under or over.',                              10, 1.0, false],
            ['serial_batch_verified',         'Correct serial / batch verification',         'Where applicable, serial / lot recorded.',                            10, 0.6, false],
            ['correct_warehouse',             'Correct warehouse selected',                  'Picked from optimal / assigned warehouse.',                          10, 0.6, false],
            ['correct_category_handling',     'Correct product category handling',           'Fragile / chilled / battery handling rules followed.',               10, 0.6, false],

            // ----- Cost & Efficiency -----
            ['cheapest_eligible_carrier',     'Cheapest eligible carrier selected',          'No avoidable premium service used.',                                 10, 1.0, false],
            ['dim_weight_correct',            'Correct dimensional weight usage',            'DIM weight calculated where billable.',                              10, 0.6, false],
            ['packaging_optimized',           'Packaging optimization followed',             'Right-sized box; fill material kept lean.',                          10, 0.4, false],
            ['shipping_zone_optimized',       'Shipping zone optimization',                  'Closest fulfilment zone where multi-warehouse.',                     10, 0.4, false],
            ['no_unnecessary_surcharge',      'Avoided unnecessary surcharge',               'No avoidable AHS, residential, or fuel surcharge.',                  10, 0.6, false],

            // ----- Professional & Process Compliance -----
            ['sop_followed',                  'SOP followed correctly',                      'Standard operating procedure adhered to.',                           10, 1.0, false],
            ['no_unjustified_override',       'No manual override without reason',           'Manual rate / address override is justified and noted.',             10, 0.6, false],
            ['no_repeated_errors',            'No repeated errors',                          'Same mistake not repeated within the audit window.',                 10, 0.6, false],
            ['communication_with_team',       'Proper communication with warehouse / team',  'Held, on-hold, or special-handling cases communicated.',             10, 0.4, false],
        ];

        $compliance = [
            // ----- Operational Compliance -----
            ['label_within_sla',              'Shipping label generated within SLA',         'Label generated before the order SLA cutoff.',                       10, 1.5, true],
            ['carrier_rules_followed',        'Proper carrier rules followed',               'Carrier-specific rules (size, prohibited items) honored.',           10, 1.0, false],
            ['marketplace_compliance',        'Marketplace compliance followed',             'Marketplace shipping policy (cutoff, carrier, scan) honored.',       10, 1.2, false],
            ['dangerous_goods_compliance',    'Dangerous goods compliance',                  'DG class, UN number, packaging spec, declaration correct.',          10, 1.5, true],
            ['international_compliance',      'International shipment compliance',           'Restricted countries, denied parties, trade rules followed.',        10, 1.5, true],
            ['customs_documentation',         'Customs documentation attached',              'Commercial invoice, HS codes, AES/EEI as required.',                 10, 1.2, false],

            // ----- Documentation & System Handling -----
            ['tracking_uploaded',             'Tracking uploaded correctly',                 'Tracking pushed to marketplace / CRM accurately and on time.',       10, 1.5, true],
            ['internal_notes_updated',        'Internal notes updated',                      'Notes / dispositions added for traceability.',                       10, 0.6, false],
            ['label_attached_properly',       'Label attached properly to package',          'Label stuck flat, scannable, on the correct face.',                  10, 0.8, false],
            ['invoice_attached',              'Invoice attached',                            'Where required (intl, B2B), invoice present in pouch.',              10, 0.6, false],
            ['packing_slip_attached',         'Packing slip attached',                       'Packing slip visible / inside as policy.',                            10, 0.4, false],
            ['crm_updated',                   'CRM updated',                                  'CRM / OMS reflects shipment status.',                                10, 0.4, false],
            ['escalation_when_needed',        'Escalation done when needed',                 'Issues escalated to supervisor / lead promptly.',                    10, 0.6, false],
        ];

        $sort = 0;
        foreach ($core as [$code, $label, $desc, $max, $weight, $critical]) {
            $sort++;
            $exists = DB::table('audit_parameters')
                ->where('module', $module)
                ->where('code', $code)
                ->exists();
            if (! $exists) {
                DB::table('audit_parameters')->insert([
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
            $exists = DB::table('audit_parameters')
                ->where('module', $module)
                ->where('code', $code)
                ->exists();
            if (! $exists) {
                DB::table('audit_parameters')->insert([
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
};
