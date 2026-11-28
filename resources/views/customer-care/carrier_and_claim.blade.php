{!! view('customer-care.qc_and_packing', [
    'pageTitle' => 'Carrier Claims',
    // Modern Gen-Z look (gradients, glass cards, pill buttons) for the page and its modals.
    'genzUi' => true,
    // SOP icon beside the page title, opens the Google Doc in a new tab.
    'sopUrl' => 'https://docs.google.com/document/d/1w1cE0w5QDZzMeV2-p1Tj2A3EfloB418Pvxh0HUdr_Vs/edit?tab=t.0',
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
    'showDepartmentColumnAfterCreatedBy' => false,
    'hideRootCauseAndInstructionsCtnColumns' => true,
    'requireRootCauseFound' => false,
    'createdAtColumnAfterTrack' => true,
    'showClaimsSummaryBadges' => true,
    // Incentive badge (rupees = claim received amount x 1) with a monthly L30 history dot.
    'incentiveHistoryUrl' => route('customer.care.dispatch.issues.incentive.history', ['department' => 'Carrier']),
    'showClaimableColumn' => true,
    // Notes beside Claimable: why the case is not claimable (50 words max).
    'showClaimableRemarkColumn' => true,
    // Notes live in the edit modal instead of a table column.
    'claimableRemarkInModal' => true,
    'showClaimFiledColumn' => true,
    'showAmpUsdColumn' => true,
    // "Amt Rec" (Amount Received) — inline-editable text input that mirrors
    // AMT $; rendered immediately after the AMT $ column.
    'showAmtRecColumn' => true,
    // AMT $ and Amt Rec: read-only in the table, edited from the edit modal.
    'claimAmountsInModal' => true,
    'showClaimReceivedColumn' => true,
    'showCarrierColumn' => true,
    // MKT column shows the marketplace logo (name in the tooltip).
    'marketplaceLogoOnly' => true,
    // Carrier column shows logo + name only (read-only); change it from the edit modal.
    'carrierLogoReadOnly' => true,
    // Show Carrier immediately after Action so it is visible on the homepage
    // without scrolling past Details / History.
    'carrierColumnAfterAction' => true,
    // No Details magnifier column on this board (edit modal has every field).
    'showDetailsColumn' => false,
    // Per-row edit history button (clock icon) opens audit trail modal.
    'showRowHistoryColumn' => true,
    'hideCloseColumn' => true,
    // No Action (Replacement / Refund) column on this board.
    'hideActionColumn' => true,
    // Columns hug their content; SKU and Issue absorb the leftover width.
    'autofitTableColumns' => true,
    // Empty cells show as a red dash so missing data stands out.
    'highlightMissing' => true,
    // SKU column at half its previous width.
    'skuColumnHalfWidth' => true,
    // History (clock) column sits right before Created By; both are frozen at the right edge.
    'historyBeforeCreatedBy' => true,
    // Oldest issue first (Created By date ascending).
    'oldestFirst' => true,
    'rowHistoryBaseUrl' => url('/customer-care/all-issues/issues'),
    // Hide Tracking / Track R / Img / Link from the table — carrier column
    // stays visible; other fields are available via the Details modal.
    'hideCarrierTrackingMediaColumns' => true,
    // Tracking ID from All Issues (tracking_number). Replacement tracking stays off this board.
    'showClaimTrackingColumn' => true,
    // Label Date column: when the shipping label was created (Veeqo shipment).
    'showLabelDateColumn' => true,
    'hideReplacementTrackingOnBoard' => true,
    // Merge the standalone "Created At" date column into the "Created By"
    // cell (name on top, short "21 JUN" date underneath, full timestamp
    // surfaced via hover tooltip).
    'mergeCreatedAtIntoCreatedBy' => true,
    // Quick-search input in the toolbar: matches against SKU, order #,
    // tracking, carrier, created-by, issue/action text, marketplace, AMP $,
    // Amt Rec, not-claimable notes and department.
    'showSearchBar' => true,
    'searchBarPlaceholder' => 'Search SKU, order, tracking, carrier…',
    // From / Till date range on the toolbar. Filters rows by created date.
    'showDateRangeFilter' => true,
    // L30 / L60 / L90 / All time picker beside the dates; L30 is the default.
    'showPeriodFilter' => true,
    'hideDateInputs' => true,
    // History / Export CSV / Import CSV show as icon-only buttons (name in the tooltip).
    'iconOnlyToolbarButtons' => true,
    // No order-History button on this board (each row has its own history icon).
    'hideHistoryButton' => true,
    // Row count badge sits at the far left of the toolbar.
    'rowCountLeft' => true,
    // Whole toolbar on one row (desktop).
    'toolbarOneLine' => true,
    // No yellow "G" (grouped entry) pill on this board.
    'hideGroupBadge' => true,
    // No "#" (issue id) column on this board.
    'hideIdColumn' => true,
    // Loss $ is autofilled (Amazon price x qty): read-only text, not an input.
    'lossReadOnly' => true,
    'defaultPeriodDays' => '30',
    // Ord column: clipboard icon only (order # via button title); no hover expand.
    'orderNumberIconOnly' => true,
])->render() !!}
