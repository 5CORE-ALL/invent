{!! view('customer-care.qc_and_packing', [
    'pageTitle' => 'Carrier Issue',
    'addIssueButtonText' => 'Add Carrier Issue',
    'introText' => 'Use Add Carrier Issue to record SKU issues. SKU lookup auto-fills Parent and available QTY.',
    'recordsTitle' => 'Carrier Issue Records',
    'modalTitle' => 'Carrier Issue',
    'skuDetailsUrl' => route('customer.care.carrier.issue.sku.details'),
    'recordsListUrl' => route('customer.care.carrier.issue.issues.index'),
    'recordsStoreUrl' => route('customer.care.carrier.issue.issues.store'),
    'recordsUpdateBaseUrl' => url('/customer-care/carrier-issue/issues'),
    'historyListUrl' => route('customer.care.carrier.issue.history.index'),
    'marketplaces' => $marketplaces ?? collect(),
])->render() !!}
