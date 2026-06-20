{!! view('customer-care.qc_and_packing', [
    'pageTitle' => 'Carriers Claims',
    'addIssueButtonText' => 'Carriers Claims',
    'hideIntroBanner' => true,
    'recordsTitle' => '',
    'modalTitle' => 'Carriers Claims',
    'skuDetailsUrl' => route('customer.care.dispatch.issues.sku.details'),
    'recordsListUrl' => route('customer.care.dispatch.issues.list.index'),
    'recordsStoreUrl' => route('customer.care.dispatch.issues.list.store'),
    'recordsUpdateBaseUrl' => route('customer.care.dispatch.issues.list.index', [], false),
    'historyListUrl' => route('customer.care.dispatch.issues.history.index'),
    'dropdownOptionsListUrl' => route('customer.care.dispatch.issues.dropdown.options.index'),
    'dropdownOptionsStoreUrl' => route('customer.care.dispatch.issues.dropdown.options.store'),
    'dropdownOptionsDeleteUrl' => route('customer.care.dispatch.issues.dropdown.options.delete'),
    'importUrl' => route('customer.care.dispatch.issues.import'),
    'claimsStatsUrl' => route('customer.care.dispatch.issues.claims.stats', ['department' => 'Carrier']),
    'marketplaces' => $marketplaces ?? collect(),
    'showDispatchExtras' => true,
    'defaultDepartmentFilter' => 'Carrier',
    'hideDepartmentColumnAndFilter' => true,
    // Render the Department column AFTER the Created By column instead of
    // its default position, so the user can see which department(s) the
    // ticket belongs to without bringing back the dropdown filter.
    'showDepartmentColumnAfterCreatedBy' => true,
    'hideRootCauseAndInstructionsCtnColumns' => true,
    'createdAtColumnAfterTrack' => true,
    'showClaimsSummaryBadges' => true,
    'showClaimFiledColumn' => true,
    'showAmpUsdColumn' => true,
    // "Amt Rec" (Amount Received) — inline-editable text input that mirrors
    // AMT $; rendered immediately after the AMT $ column.
    'showAmtRecColumn' => true,
    'showClaimReceivedColumn' => true,
    'showCarrierColumn' => true,
    // Read-only "Details" magnifier column (after Action) that opens a
    // modal listing every relevant field for the row.
    'showDetailsColumn' => true,
    // Hide the wide "Carrier / Tracking / Track R / Img 1 / Img 2 / Link"
    // group from the table — the same data is available via the Details
    // magnifier modal.
    'hideCarrierTrackingMediaColumns' => true,
    // Merge the standalone "Created At" date column into the "Created By"
    // cell (name on top, short "21 JUN" date underneath, full timestamp
    // surfaced via hover tooltip).
    'mergeCreatedAtIntoCreatedBy' => true,
])->render() !!}
