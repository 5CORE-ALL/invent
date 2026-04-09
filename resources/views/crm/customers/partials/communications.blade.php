<div class="crm-tab-partial" data-tab-partial="communications">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="text-muted small">{{ $timeline->count() }} {{ Str::plural('event', $timeline->count()) }}</span>
        <a href="{{ route('crm.customers.communications.index', $customer) }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
            Full page
        </a>
    </div>
    <div class="card">
        <div class="card-body">
            @include('crm.communications.partials.timeline-list', ['timeline' => $timeline])
        </div>
    </div>
</div>
