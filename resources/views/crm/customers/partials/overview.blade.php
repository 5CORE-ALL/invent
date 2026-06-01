<div class="crm-tab-partial" data-tab-partial="overview">
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">Contact</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">Name</dt>
                        <dd class="col-sm-8">{{ $customer->name }}</dd>
                        <dt class="col-sm-4 text-muted">Email</dt>
                        <dd class="col-sm-8">{{ $customer->email ?? '—' }}</dd>
                        <dt class="col-sm-4 text-muted">Phone</dt>
                        <dd class="col-sm-8">{{ $customer->phone ?? '—' }}</dd>
                        <dt class="col-sm-4 text-muted">Company</dt>
                        <dd class="col-sm-8">{{ $customer->company->name ?? '—' }}</dd>
                        <dt class="col-sm-4 text-muted">Created</dt>
                        <dd class="col-sm-8">{{ $customer->created_at?->format('M j, Y g:i A') ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">Activity snapshot</div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 small">
                        <li class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted">Follow-ups (total)</span>
                            <strong>{{ $followUpsTotal }}</strong>
                        </li>
                        <li class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted">Pending follow-ups</span>
                            <strong>{{ $customer->follow_ups_count }}</strong>
                        </li>
                        <li class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted">Communications</span>
                            <strong>{{ $customer->communication_logs_count }}</strong>
                        </li>
                        <li class="d-flex justify-content-between py-1">
                            <span class="text-muted">Shopify profiles</span>
                            <strong>{{ $customer->shopify_customers_count }}</strong>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
