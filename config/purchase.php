<?php

return [
    /*
    | Maps Laravel route names to purchase page info keys (stored in purchase_page_info_notes).
    */
    'page_info_keys' => [
        'category.list' => 'categories',
        'supplier.list' => 'suppliers',
        'rfq-form.index' => 'rfq_form',
        'rfq-form.reports' => 'rfq_form_reports',
        'claim.reimbursement' => 'claim_reimbursement',
        'approval.required' => 'approval_required',
        'forecast.analysis' => 'forecast',
        'to.order.analysis' => 'to_order',
        'to.order.analysis.new' => 'to_order',
        'list-all-purchase-orders' => 'purchase_contract',
        'purchase.index' => 'purchase',
        'ledger.advance.payments' => 'advance_payments',
        'supplier.ledger' => 'supplier_ledger',
        'mfrg.in.progress' => 'mip',
        'mfrg.in.progress.new' => 'mip',
        'ready.to.ship' => 'r2s',
        'transit' => 'transit',
        'china.load' => 'china_load',
        'upcoming.container' => 'upcoming_container',
        'transit.container.details' => 'transit_container_inv',
        'transit.container.new' => 'transit_container_new',
        'transit.container.changes' => 'transit_container_changes',
        'arrived.container' => 'arrived_container',
        'container.summary' => 'container_summary',
        'on.sea.transit' => 'on_sea_transit',
        'on.road.transit' => 'on_road_transit',
        'container.planning' => 'container_planning',
        'quality.enhance' => 'quality_enhance',
        'follow.up.history' => 'follow_up_history',
        'comparison.index' => 'comparison',
    ],
];
