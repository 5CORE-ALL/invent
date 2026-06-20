{!! view('customer-care.qc_and_packing', [
    'pageTitle' => 'Carrier Scan Issues',
    'addIssueButtonText' => 'Carrier Scan Issues',
    'hideIntroBanner' => true,
    'recordsTitle' => '',
    'modalTitle' => 'Carrier Scan Issues',
    'skuDetailsUrl' => route('customer.care.dispatch.issues.sku.details'),
    'recordsListUrl' => route('customer.care.dispatch.issues.list.index'),
    'recordsStoreUrl' => route('customer.care.dispatch.issues.list.store'),
    'recordsUpdateBaseUrl' => route('customer.care.dispatch.issues.list.index', [], false),
    'historyListUrl' => route('customer.care.dispatch.issues.history.index'),
    'dropdownOptionsListUrl' => route('customer.care.dispatch.issues.dropdown.options.index'),
    'dropdownOptionsStoreUrl' => route('customer.care.dispatch.issues.dropdown.options.store'),
    'dropdownOptionsDeleteUrl' => route('customer.care.dispatch.issues.dropdown.options.delete'),
    'importUrl' => route('customer.care.dispatch.issues.import'),
    'claimsStatsUrl' => route('customer.care.dispatch.issues.claims.stats', ['department' => 'Carrier Issue']),
    'marketplaces' => $marketplaces ?? collect(),
    'showDispatchExtras' => true,
    'defaultDepartmentFilter' => 'Carrier Issue',
    // Department field is shown in the modal as a multi-select (same UX as
    // /customer-care/all-issues). The page-level filter still defaults to
    // "Carrier Issue" via `defaultDepartmentFilter`, but records can be
    // tagged with multiple departments.
    'hideDepartmentColumnAndFilter' => true,
    'showDepartmentColumnAfterCreatedBy' => true,
    'hideRootCauseAndInstructionsCtnColumns' => true,
    'createdAtColumnAfterTrack' => true,
    // Claims summary badges (Claims Filed / Pending Claims / Claims Recd) and
    // the Claim Filed / AMT $ / Claim Recd table columns are all hidden on
    // this page (Carrier Scan Issues). The underlying data is still
    // accessible via the Details magnifier modal.
    'showClaimsSummaryBadges' => false,
    'showClaimFiledColumn' => false,
    'showAmpUsdColumn' => false,
    'showClaimReceivedColumn' => false,
    'showCarrierColumn' => true,
    // Read-only "Details" magnifier column (after Action) that opens a
    // modal listing every relevant field for the row.
    'showDetailsColumn' => true,
    // Hide the wide "Carrier / Tracking / Track R / Img 1 / Img 2 / Link"
    // group from the table — the same data is available via the Details
    // magnifier modal.
    'hideCarrierTrackingMediaColumns' => true,
    // Merge "Created At" into the "Created By" cell so the user name and
    // short date sit in a single column (Carriers Claims style).
    'mergeCreatedAtIntoCreatedBy' => true,
    'hideLossDollarInput' => true,
    'hideActionRemark' => true,
])->render() !!}
