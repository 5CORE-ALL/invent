{!! view('customer-care.qc_and_packing', [
    'pageTitle' => 'Label Issues',
    'addIssueButtonText' => 'Add Label Issue',
    'introText' => 'Use Add Label Issue to record SKU issues. SKU lookup auto-fills Parent and available QTY.',
    'recordsTitle' => 'Label Issues Records',
    'modalTitle' => 'Label Issue',
    'skuDetailsUrl' => route('customer.care.label.issues.sku.details'),
    'recordsListUrl' => route('customer.care.label.issues.list.index'),
    'recordsStoreUrl' => route('customer.care.label.issues.list.store'),
    'recordsUpdateBaseUrl' => url('/customer-care/label-issues/issues'),
    'historyListUrl' => route('customer.care.label.issues.history.index'),
    'marketplaces' => $marketplaces ?? collect(),
])->render() !!}
