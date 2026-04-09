<div class="crm-tab-partial" data-tab-partial="shopify-data">
    <p class="text-muted small">Shopify customer rows linked to this CRM customer (latest sync first, max 100).</p>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Shopify ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Sync</th>
                    <th>Last synced</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($shopifyCustomers as $sc)
                    <tr>
                        <td class="font-monospace small">{{ $sc->shopify_customer_id }}</td>
                        <td>{{ trim(($sc->first_name ?? '').' '.($sc->last_name ?? '')) ?: '—' }}</td>
                        <td class="small">{{ $sc->email ?? '—' }}</td>
                        <td class="small">{{ $sc->phone ?? '—' }}</td>
                        <td><span class="badge bg-light text-dark border">{{ $sc->sync_status ?? '—' }}</span></td>
                        <td class="small text-nowrap">{{ $sc->last_synced_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-muted">No Shopify data linked yet. Run a customer sync from Shopify.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
