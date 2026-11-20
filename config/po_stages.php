<?php

/**
 * PO Stage (shared by /list-all-purchase-orders and /ledger-master/advance-and-payments).
 * Stored once on purchase_orders.po_stage, so a change on one page shows on the other.
 * key => [label, background, text colour]
 */
return [
    'stages' => [
        'in_process'       => ['label' => 'In Process',        'bg' => '#6c757d', 'text' => '#ffffff'],
        'in_approval'      => ['label' => 'In Approval',       'bg' => '#f59e0b', 'text' => '#1f2937'],
        'approved'         => ['label' => 'Approved',          'bg' => '#198754', 'text' => '#ffffff'],
        'advance_required' => ['label' => 'Advance Required',  'bg' => '#fd7e14', 'text' => '#ffffff'],
        'under_production' => ['label' => 'Under Production',  'bg' => '#6f42c1', 'text' => '#ffffff'],
        'shipping_required' => ['label' => 'Shipping Required', 'bg' => '#0dcaf0', 'text' => '#1f2937'],
        'balance_required' => ['label' => 'Balance Required',  'bg' => '#dc3545', 'text' => '#ffffff'],
    ],
];
