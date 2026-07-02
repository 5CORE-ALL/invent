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
    // One table row per issue on this board; edits update in place and history
    // is tracked on that row only (no dept-split sibling rows).
    'lockedDepartment' => 'Carrier Issue',
    'hideDepartmentFieldInModal' => true,
    'singleEntryIssueBoard' => true,
    'hideDepartmentColumnAndFilter' => true,
    'showDepartmentColumnAfterCreatedBy' => true,
    'hideRootCauseAndInstructionsCtnColumns' => true,
    'requireRootCauseFound' => false,
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
    // Show Carrier immediately after Action so it is visible on the homepage.
    'carrierColumnAfterAction' => true,
    // Read-only "Details" magnifier column (after Action) that opens a
    // modal listing every relevant field for the row.
    'showDetailsColumn' => true,
    // Per-row edit history button (clock icon) opens audit trail modal.
    'showRowHistoryColumn' => true,
    'rowHistoryBaseUrl' => url('/customer-care/all-issues/issues'),
    // Hide Tracking / Track R / Img / Link from the table — carrier column
    // stays visible; other fields are available via the Details modal.
    'hideCarrierTrackingMediaColumns' => true,
    // Merge "Created At" into the "Created By" cell so the user name and
    // short date sit in a single column (Carrier Claims style).
    'mergeCreatedAtIntoCreatedBy' => true,
    'hideLossDollarInput' => true,
    'hideActionRemark' => true,
    // Widen the SKU column on this page (the default of 80px clipped most
    // SKUs after a few characters). Other pages using qc_and_packing.blade.php
    // are unaffected because the default is preserved when this is omitted.
    'skuColumnMaxPx' => 180,
    // Quick-search input in the toolbar: matches against SKU, order #,
    // tracking, carrier, created-by, issue/action text, marketplace and
    // department.
    'showSearchBar' => true,
    'searchBarPlaceholder' => 'Search SKU, order, tracking, carrier…',
])->render() !!}
