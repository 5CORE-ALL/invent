{!! view('customer-care.qc_and_packing', [
    'pageTitle' => 'Carrier Claims',
    'addIssueButtonText' => 'Carrier Claims',
    'hideIntroBanner' => true,
    'recordsTitle' => '',
    'modalTitle' => 'Carrier Claims',
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
    // One table row per issue; in-place edits + row-scoped history (no dept split).
    'lockedDepartment' => 'Carrier',
    'hideDepartmentFieldInModal' => true,
    'singleEntryIssueBoard' => true,
    'hideDepartmentColumnAndFilter' => true,
    // Render the Department column AFTER the Created By column instead of
    // its default position, so the user can see which department(s) the
    // ticket belongs to without bringing back the dropdown filter.
    'showDepartmentColumnAfterCreatedBy' => true,
    'hideRootCauseAndInstructionsCtnColumns' => true,
    'requireRootCauseFound' => false,
    'createdAtColumnAfterTrack' => true,
    'showClaimsSummaryBadges' => true,
    'showClaimableColumn' => true,
    // Notes beside Claimable: why the case is not claimable (50 words max).
    'showClaimableRemarkColumn' => true,
    'showClaimFiledColumn' => true,
    'showAmpUsdColumn' => true,
    // "Amt Rec" (Amount Received) — inline-editable text input that mirrors
    // AMT $; rendered immediately after the AMT $ column.
    'showAmtRecColumn' => true,
    'showClaimReceivedColumn' => true,
    'showCarrierColumn' => true,
    // Show Carrier immediately after Action so it is visible on the homepage
    // without scrolling past Details / History.
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
    // Merge the standalone "Created At" date column into the "Created By"
    // cell (name on top, short "21 JUN" date underneath, full timestamp
    // surfaced via hover tooltip).
    'mergeCreatedAtIntoCreatedBy' => true,
    // Quick-search input in the toolbar: matches against SKU, order #,
    // tracking, carrier, created-by, issue/action text, marketplace, AMP $,
    // Amt Rec, not-claimable notes and department.
    'showSearchBar' => true,
    'searchBarPlaceholder' => 'Search SKU, order, tracking, carrier…',
    // Ord column: clipboard icon only (order # via button title); no hover expand.
    'orderNumberIconOnly' => true,
])->render() !!}
