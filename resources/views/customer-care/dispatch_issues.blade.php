{!! view('customer-care.qc_and_packing', [
    'pageTitle' => 'Dispatch Issues',
    'addIssueButtonText' => 'Add Dispatch Issue',
    'introText' => 'Use Add Dispatch Issue to record SKU issues. SKU lookup auto-fills Parent and available QTY.',
    'recordsTitle' => 'Dispatch Issues Records',
    'modalTitle' => 'Dispatch Issue',
    'skuDetailsUrl' => route('customer.care.dispatch.issues.sku.details'),
    'recordsListUrl' => route('customer.care.dispatch.issues.list.index'),
    'recordsStoreUrl' => route('customer.care.dispatch.issues.list.store'),
    'recordsUpdateBaseUrl' => url('/customer-care/dispatch-issues/issues'),
    'historyListUrl' => route('customer.care.dispatch.issues.history.index'),
    'marketplaces' => $marketplaces ?? collect(),
])->render() !!}
