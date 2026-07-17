@extends('layouts.vertical', ['title' => $title ?? 'Marketplace Manager', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<style>
    .mm-channel-card { border: 1px solid #e9ecef; border-radius: 10px; transition: box-shadow .15s; }
    .mm-channel-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }
    .mm-status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
    .mm-status-dot.connected { background: #0ab39c; }
    .mm-status-dot.disconnected { background: #f06548; }
    .mm-mp-logo { width: 28px; height: 28px; object-fit: contain; flex-shrink: 0; }
</style>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h4 class="mb-1">Marketplace Manager</h4>
                <p class="text-muted mb-0">Connect marketplaces to Shopify (source shop). Sync listings, inventory, and orders.</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <form method="post" action="{{ route('marketplace.manager.refresh.shopify') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success">
                        <i class="ri-store-2-line me-1"></i> Refresh Shopify
                    </button>
                </form>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success py-2">{{ session('success') }}</div>
        @endif

        <div class="alert alert-light border mb-3 py-2">
            <strong>Shared Shopify live store</strong>
            — Active SKUs: <strong>{{ number_format((int) ($shopifyActiveSkuCount ?? 0)) }}</strong>
            @if(!empty($shopifyCatalogSyncedAt))
                · last synced {{ $shopifyCatalogSyncedAt }}
            @else
                · not synced yet
            @endif
            @if(!empty($shopifyRefreshStatus['status']))
                · refresh: {{ $shopifyRefreshStatus['status'] }}
            @endif
            <span class="text-muted">· All marketplace listings read this once (no per-page Shopify API).</span>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Marketplace</th>
                                <th>Source Shop</th>
                                <th>Connection</th>
                                <th>Listings</th>
                                <th>Inventory Sync</th>
                                <th>Order Import</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($channels as $ch)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            @if(!empty($ch['logo']))
                                                <img src="{{ asset($ch['logo']) }}" alt="{{ $ch['label'] }}" class="mm-mp-logo"
                                                     onerror="this.style.display='none'">
                                            @endif
                                            <span class="badge bg-dark">{{ $ch['short'] }}</span>
                                            <strong>{{ $ch['label'] }}</strong>
                                        </div>
                                    </td>
                                    <td>{{ $ch['source_shop'] }}</td>
                                    <td>
                                        <span class="mm-status-dot {{ $ch['connected'] ? 'connected' : 'disconnected' }} me-1"></span>
                                        {{ $ch['connected'] ? 'Connected' : 'Not connected' }}
                                    </td>
                                    <td>{{ number_format($ch['listings_count']) }}</td>
                                    <td>
                                        @if($ch['sync_settings']['inventory']['inventory_sync'] ?? false)
                                            <span class="badge bg-success-subtle text-success">On</span>
                                        @else
                                            <span class="badge bg-light text-muted">Off</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $fetchOn = $ch['sync_settings']['order']['fetch_orders'] ?? true;
                                            $autoOn = $ch['sync_settings']['order']['auto_import_to_shopify'] ?? false;
                                        @endphp
                                        @if(! $fetchOn)
                                            <span class="badge bg-light text-muted">Fetch Off</span>
                                        @elseif($autoOn)
                                            <span class="badge bg-success-subtle text-success">Fetch + Auto</span>
                                        @else
                                            <span class="badge bg-info-subtle text-info">Fetch only</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('marketplace.manager.show', $ch['slug']) }}" class="btn btn-sm btn-outline-primary">Manage</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No marketplaces configured yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="alert alert-info mt-3 mb-0">
            <i class="ri-information-line me-1"></i>
            AliExpress, Alibaba, and Reverb are available here. More marketplaces can be added the same way.
        </div>
    </div>
</div>
@endsection
