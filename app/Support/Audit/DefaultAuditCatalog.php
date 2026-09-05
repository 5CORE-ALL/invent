<?php

namespace App\Support\Audit;

/**
 * Default grade bands + checklist parameters for each CC audit module.
 * Used when a module has no rows yet so the audit modal always shows data.
 */
class DefaultAuditCatalog
{
    public static function grades(): array
    {
        return [
            ['grade' => 'A+', 'min_score' => 95, 'max_score' => 100,   'color' => '#198754', 'description' => 'Outstanding', 'sort_order' => 1],
            ['grade' => 'A',  'min_score' => 90, 'max_score' => 94.99, 'color' => '#28a745', 'description' => 'Excellent',  'sort_order' => 2],
            ['grade' => 'B',  'min_score' => 80, 'max_score' => 89.99, 'color' => '#0d6efd', 'description' => 'Good',       'sort_order' => 3],
            ['grade' => 'C',  'min_score' => 70, 'max_score' => 79.99, 'color' => '#ffc107', 'description' => 'Average',    'sort_order' => 4],
            ['grade' => 'D',  'min_score' => 60, 'max_score' => 69.99, 'color' => '#fd7e14', 'description' => 'Below Avg',  'sort_order' => 5],
            ['grade' => 'F',  'min_score' => 0,  'max_score' => 59.99, 'color' => '#dc3545', 'description' => 'Fail',       'sort_order' => 6],
        ];
    }

    /**
     * @return list<array{0:string,1:string,2:string,3:int,4:float,5:bool,6:string}>
     *         [code, label, description, max, weight, critical, category]
     */
    public static function parameters(string $module): array
    {
        return match ($module) {
            'cc_return' => self::returnParameters(),
            'cc_replacement' => self::replacementParameters(),
            'cc_shipping' => self::shippingParameters(),
            'cc_messages' => self::messagesParameters(),
            default => [],
        };
    }

    public static function criticalReasons(string $module): array
    {
        return match ($module) {
            'cc_return' => [
                'Return accepted outside policy window',
                'Refund issued without receiving the item',
                'Wrong RMA / return label sent',
                'Return reason misclassified',
                'Marketplace return policy violation',
                'Customer not updated on return status',
                'Returned item not inspected',
                'Confidential data in return notes',
            ],
            'cc_replacement' => [
                'Wrong replacement SKU sent',
                'Duplicate replacement created',
                'Replacement shipped to wrong address',
                'Inventory not deducted',
                'Approved against policy',
                'Tracking not uploaded',
                'Replacement not QC checked',
                'Confidential data leak',
            ],
            'cc_shipping' => [
                'Wrong shipping carrier',
                'Wrong shipping address',
                'Wrong package weight (charge adjustment)',
                'Wrong product shipped',
                'Duplicate shipment created',
                'Invalid / missing tracking upload',
                'SLA breach (label generated late)',
                'International compliance violation',
                'Hazardous goods mishandled',
                'Customs documentation missing',
            ],
            default => [
                'No response within 24 hours',
                'Marketplace policy violation',
                'Rude or unprofessional communication',
                'False promises made to customer',
                'Review manipulation attempt',
                'Confidential data leak',
            ],
        };
    }

    private static function returnParameters(): array
    {
        return [
            ['return_logged_sla', 'Return request logged within SLA', 'Case opened inside the channel SLA window.', 10, 1.5, true, 'core_qa'],
            ['correct_return_reason', 'Correct return reason captured', 'Reason matches customer statement and marketplace code.', 10, 1.2, false, 'core_qa'],
            ['rma_issued', 'RMA / return authorization issued', 'RMA number created and shared with the customer.', 10, 1.5, true, 'core_qa'],
            ['return_label_correct', 'Return label / carrier correct', 'Label, carrier, and ship-from instructions are right.', 10, 1.0, false, 'core_qa'],
            ['decision_correct', 'Refund vs replacement decision correct', 'Resolution matches policy and the inspected condition.', 10, 1.5, true, 'core_qa'],
            ['inspection_complete', 'Returned item inspected', 'Condition, accessories, and photos checked on receipt.', 10, 1.2, false, 'core_qa'],
            ['restock_decision', 'Restock / scrap decision correct', 'Sellable units restocked; damaged units scrapped or vendor-returned.', 10, 1.0, false, 'core_qa'],
            ['customer_updates', 'Customer kept updated', 'Status updates sent at request, receipt, and close.', 10, 1.0, false, 'core_qa'],
            ['return_tat', 'Return closed within TAT', 'Request-to-close time meets the returns TAT target.', 10, 1.0, false, 'core_qa'],
            ['photos_evidence', 'Photos / evidence attached', 'Required photos or unboxing proof are on the case.', 10, 0.8, false, 'core_qa'],

            ['marketplace_return_policy', 'Marketplace return policy followed', 'Window, eligibility, and refund rules honored.', 10, 1.5, true, 'channel_compliance'],
            ['no_unauthorized_refund', 'No unauthorized refund or credit', 'Money movement matches the approved decision.', 10, 1.5, true, 'channel_compliance'],
            ['return_tracking_recorded', 'Return tracking recorded', 'Inbound tracking is on the case and marketplace.', 10, 1.2, false, 'channel_compliance'],
            ['window_eligible', 'Return window / eligibility verified', 'Purchase date, category, and reason are eligible.', 10, 1.2, false, 'channel_compliance'],
            ['documentation_complete', 'Notes, tags, and documentation complete', 'CRM notes and dispositions are complete.', 10, 1.0, false, 'channel_compliance'],
            ['data_privacy', 'Customer data privacy maintained', 'No leak of address, payment, or internal notes.', 10, 1.5, true, 'channel_compliance'],
        ];
    }

    private static function replacementParameters(): array
    {
        return [
            ['replacement_approved_policy', 'Replacement approved per policy', 'Approval matches defect / wrong-item / missing-part rules.', 10, 1.5, true, 'core_qa'],
            ['correct_replacement_sku', 'Correct replacement SKU selected', 'SKU matches the approved replacement, not a guess.', 10, 1.5, true, 'core_qa'],
            ['correct_qty_sent', 'Correct replacement quantity sent', 'Qty matches the approved line, not the full order by default.', 10, 1.2, false, 'core_qa'],
            ['shipped_within_sla', 'Replacement shipped within SLA', 'Label created and handed off before the cutoff.', 10, 1.5, true, 'core_qa'],
            ['tracking_shared', 'Tracking shared with customer', 'Tracking posted on the case and marketplace thread.', 10, 1.0, false, 'core_qa'],
            ['defect_confirmed', 'Defect / wrong-item confirmed', 'Photos or order proof reviewed before sending a new unit.', 10, 1.2, false, 'core_qa'],
            ['inventory_deducted', 'Outgoing inventory deducted', 'Warehouse stock reduced for the replacement SKU.', 10, 1.2, false, 'core_qa'],
            ['warehouse_correct', 'Correct warehouse / origin used', 'Picked from the assigned or nearest eligible warehouse.', 10, 0.8, false, 'core_qa'],
            ['quality_checked', 'Replacement unit QC checked', 'Unit inspected before ship so the same defect is not resent.', 10, 1.0, false, 'core_qa'],
            ['customer_receipt', 'Delivery / receipt followed up', 'Agent confirmed delivery or left a follow-up if still in transit.', 10, 0.8, false, 'core_qa'],

            ['marketplace_replacement_policy', 'Marketplace replacement policy followed', 'Channel replacement window and rules honored.', 10, 1.5, true, 'channel_compliance'],
            ['no_duplicate_replacement', 'No duplicate replacement sent', 'No second unit for an already-replaced line.', 10, 1.5, true, 'channel_compliance'],
            ['label_address_correct', 'Label and ship-to address correct', 'Name, street, city, ZIP match the approved ship-to.', 10, 1.5, true, 'channel_compliance'],
            ['documentation_complete', 'Case notes and tags complete', 'Reason, SKU, qty, tracking, and warehouse are documented.', 10, 1.0, false, 'channel_compliance'],
            ['serial_recorded', 'Serial / IMEI recorded when required', 'Serialized items have outbound serial on the case.', 10, 0.8, false, 'channel_compliance'],
            ['data_privacy', 'Customer data privacy maintained', 'No leak of address, payment, or internal notes.', 10, 1.5, true, 'channel_compliance'],
        ];
    }

    private static function shippingParameters(): array
    {
        return [
            ['correct_platform_selected', 'Correct shipping platform selected', 'Carrier / platform matches the order.', 10, 1.5, true, 'core_qa'],
            ['correct_service_selected', 'Correct shipping service selected', 'Ground / Express / 2-Day matches buyer SLA.', 10, 1.2, false, 'core_qa'],
            ['correct_weight', 'Required package weight entered', 'Weight matches actual and avoids carrier surcharges.', 10, 1.5, true, 'core_qa'],
            ['correct_dimensions', 'Required package dimensions entered', 'L × W × H matches the packed carton.', 10, 1.0, false, 'core_qa'],
            ['correct_address', 'Correct shipping address', 'Street, city, state, ZIP match the order.', 10, 1.5, true, 'core_qa'],
            ['correct_sku', 'Correct SKU shipped', 'SKU on the label matches what was packed.', 10, 1.5, true, 'core_qa'],
            ['correct_quantity', 'Correct quantity shipped', 'Qty matches the order.', 10, 1.0, false, 'core_qa'],
            ['no_duplicate_shipment', 'Duplicate shipment prevented', 'No second label for an already-shipped order.', 10, 1.5, true, 'core_qa'],
            ['cost_optimization', 'Cheapest eligible service used', 'No avoidable premium service.', 10, 1.0, false, 'core_qa'],
            ['sop_followed', 'SOP followed correctly', 'Shipping SOP steps were completed.', 10, 1.0, false, 'core_qa'],

            ['label_within_sla', 'Label generated within SLA', 'Label created before the order cutoff.', 10, 1.5, true, 'channel_compliance'],
            ['tracking_uploaded', 'Tracking uploaded correctly', 'Tracking pushed to marketplace / CRM on time.', 10, 1.5, true, 'channel_compliance'],
            ['marketplace_compliance', 'Marketplace shipping policy followed', 'Cutoff, carrier, and scan rules honored.', 10, 1.2, false, 'channel_compliance'],
            ['carrier_rules_followed', 'Carrier rules followed', 'Size, weight, and prohibited-item rules honored.', 10, 1.0, false, 'channel_compliance'],
            ['dangerous_goods_compliance', 'Dangerous goods compliance', 'DG class, packaging, and declaration are correct.', 10, 1.5, true, 'channel_compliance'],
            ['customs_documentation', 'Customs documentation attached', 'Invoice / HS codes present when required.', 10, 1.2, false, 'channel_compliance'],
        ];
    }

    private static function messagesParameters(): array
    {
        return [
            ['response_within_sla', 'Response within SLA', 'First response delivered within the channel SLA window.', 10, 1.5, true, 'core_qa'],
            ['twentyfour_hr_compliance', '24-hour compliance', 'No customer left waiting beyond 24 hours.', 10, 1.5, true, 'core_qa'],
            ['correct_resolution', 'Correct resolution provided', 'Issue actually solved, not just acknowledged.', 10, 1.5, false, 'core_qa'],
            ['professional_tone', 'Professional & empathetic tone', 'Polite, respectful, no defensive language.', 10, 1.0, false, 'core_qa'],
            ['grammar_quality', 'Grammar, spelling & formatting', 'Clear sentences and scan-friendly formatting.', 10, 0.5, false, 'core_qa'],
            ['ownership', 'Took ownership end-to-end', 'Agent owned the case until closure.', 10, 1.0, false, 'core_qa'],
            ['follow_up_quality', 'Follow-up quality', 'Promised callbacks / updates were delivered.', 10, 1.0, false, 'core_qa'],
            ['customer_satisfaction', 'Likely customer satisfaction', 'Last customer message indicates they are satisfied.', 10, 1.0, false, 'core_qa'],

            ['amazon_safe_communication', 'Marketplace-safe communication', 'No off-platform contact or marketplace-rule violations.', 10, 1.5, true, 'channel_compliance'],
            ['no_review_manipulation', 'No review manipulation', 'Did not ask or pressure for reviews.', 10, 1.5, true, 'channel_compliance'],
            ['proper_escalation', 'Proper escalation handling', 'Escalated to the right team when needed.', 10, 1.0, false, 'channel_compliance'],
            ['proper_documentation', 'Proper documentation & tagging', 'Notes, dispositions, and tags applied correctly.', 10, 1.0, false, 'channel_compliance'],
            ['data_privacy', 'Data privacy maintained', 'No leakage of customer or internal data.', 10, 1.5, true, 'channel_compliance'],
        ];
    }
}
