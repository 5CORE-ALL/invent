@extends('layouts.vertical', ['title' => $title ?? 'TopDawg - Orders', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">TopDawg Orders</h4>
                    <span class="badge bg-primary">{{ $orders->total() }} orders</span>
                </div>
                <div class="card-body">
                    <p class="text-muted">All TopDawg orders. Run <code>php artisan topdawg:fetch</code> to refresh.</p>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>SKU</th>
                                    <th>Title</th>
                                    <th>Qty</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($orders as $o)
                                    <tr>
                                        <td>{{ $o->order_number ?? '-' }}</td>
                                        <td>{{ $o->order_date ? \Carbon\Carbon::parse($o->order_date)->format('M d, Y') : '-' }}</td>
                                        <td><span class="badge bg-secondary">{{ $o->status ?? '-' }}</span></td>
                                        <td>{{ $o->sku ?? '-' }}</td>
                                        <td>{{ Str::limit($o->display_sku ?? '-', 40) }}</td>
                                        <td>{{ $o->quantity ?? 1 }}</td>
                                        <td>{{ $o->amount ? number_format($o->amount, 2) : '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No TopDawg orders. Run <code>php artisan topdawg:fetch</code> to sync.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($orders->hasPages())
                        <div class="d-flex justify-content-center mt-3">
                            {{ $orders->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
