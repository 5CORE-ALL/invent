@extends('layouts.vertical', ['title' => 'Spare Parts & Packing', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <style>
        .sp-card {
            border-radius: 0.5rem;
            border: 1px solid #e9ecef;
        }
        .sp-card h3 {
            font-size: 1.75rem;
            margin-bottom: 0;
        }
        .tree ul {
            list-style: none;
            padding-left: 1.25rem;
            margin-bottom: 0;
        }
        .tree li {
            padding: 0.15rem 0;
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h4 class="mb-0">Spare Parts & Packing</h4>
                <div class="text-muted">Inventory Management / Spare Parts & Packing</div>
            </div>
            <a href="{{ route('spare.parts.index') }}" class="btn btn-outline-secondary btn-sm">Refresh</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php $s = $summary ?? []; @endphp
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card sp-card h-100"><div class="card-body"><div class="text-muted small">Total Spare Parts</div><h3>{{ $s['total_spare_parts'] ?? 0 }}</h3></div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card sp-card h-100 border-warning"><div class="card-body"><div class="text-muted small">Low Stock Items</div><h3 class="text-warning">{{ $s['low_stock_items'] ?? 0 }}</h3></div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card sp-card h-100"><div class="card-body"><div class="text-muted small">Pending Requisitions</div><h3>{{ $s['pending_requisitions'] ?? 0 }}</h3></div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card sp-card h-100"><div class="card-body"><div class="text-muted small">Pending Purchase Orders</div><h3>{{ $s['pending_purchase_orders'] ?? 0 }}</h3></div></div>
            </div>
        </div>

        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-requisitions" type="button">Requisitions</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-issue" type="button">Issue Parts</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-po" type="button">Purchase Orders</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-low" type="button">Low Stock</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-parts" type="button">Spare Parts & Parents</button></li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-requisitions">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">New Requisition</h5>
                        <form method="POST" action="{{ route('requisition.store') }}" class="row g-2">
                            @csrf
                            <div class="col-md-3">
                                <label class="form-label">Department</label>
                                <input type="text" name="department" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-select form-select-sm">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Notes</label>
                                <input type="text" name="notes" class="form-control form-control-sm">
                            </div>
                            @for($i = 0; $i < 3; $i++)
                                <div class="col-md-5">
                                    <label class="form-label">Part SKU {{ $i + 1 }}</label>
                                    <select name="part_id[]" class="form-select form-select-sm">
                                        <option value="">Select SKU</option>
                                        @foreach($products as $p)
                                            <option value="{{ $p->id }}">{{ $p->sku }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Qty</label>
                                    <input type="number" name="qty[]" min="1" class="form-control form-control-sm">
                                </div>
                            @endfor
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-sm">Save Draft</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Recent Requisitions</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead><tr><th>ID</th><th>Status</th><th>Priority</th><th>Dept</th><th>Items</th><th>Actions</th></tr></thead>
                                <tbody>
                                    @forelse($requisitions as $req)
                                        <tr>
                                            <td>{{ $req->id }}</td>
                                            <td>{{ $req->status }}</td>
                                            <td>{{ $req->priority }}</td>
                                            <td>{{ $req->department }}</td>
                                            <td>
                                                @foreach($req->items as $item)
                                                    {{ $item->part->sku ?? $item->part_id }}@if(!$loop->last), @endif
                                                @endforeach
                                            </td>
                                            <td class="d-flex gap-1">
                                                @if($req->status === 'draft')
                                                    <form method="POST" action="{{ route('requisition.submit', $req->id) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Submit</button></form>
                                                @endif
                                                @if(in_array($req->status, ['draft','submitted'], true))
                                                    <form method="POST" action="{{ route('requisition.approve', $req->id) }}">@csrf<button class="btn btn-sm btn-outline-success">Approve</button></form>
                                                @endif
                                                <form method="POST" action="{{ route('requisition.close', $req->id) }}">@csrf<button class="btn btn-sm btn-outline-dark">Close</button></form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted">No requisitions found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-issue">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Issue Against Approved Requisitions</h5>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead><tr><th>Req</th><th>SKU</th><th>Approved</th><th>Issued</th><th>Remaining</th><th>Qty</th><th>Action</th></tr></thead>
                                <tbody>
                                    @forelse($issues as $row)
                                        @php $remaining = $row->quantityRemainingToIssue(); @endphp
                                        <tr>
                                            <td>{{ $row->requisition_id }}</td>
                                            <td>{{ $row->part->sku ?? '' }}</td>
                                            <td>{{ $row->quantity_approved ?? 0 }}</td>
                                            <td>{{ $row->quantity_issued }}</td>
                                            <td>{{ $remaining }}</td>
                                            <td>
                                                <form method="POST" action="{{ route('issue.store') }}" class="d-flex gap-1 align-items-center">
                                                    @csrf
                                                    <input type="hidden" name="requisition_item_id" value="{{ $row->id }}">
                                                    <input type="number" name="quantity" min="1" max="{{ max(1, $remaining) }}" value="1" class="form-control form-control-sm" style="width:90px;">
                                                    <button class="btn btn-sm btn-primary">Issue</button>
                                                </form>
                                            </td>
                                            <td></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="text-center text-muted">No pending issue lines.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-po">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">New Spare Parts PO</h5>
                        <form method="POST" action="{{ route('po.store') }}" class="row g-2">
                            @csrf
                            <div class="col-md-4">
                                <label class="form-label">Supplier</label>
                                <select name="supplier_id" class="form-select form-select-sm" required>
                                    <option value="">Select...</option>
                                    @foreach($suppliers as $sup)
                                        <option value="{{ $sup->id }}">{{ $sup->name ?: $sup->company }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Expected</label>
                                <input type="date" name="expected_at" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Notes</label>
                                <input type="text" name="notes" class="form-control form-control-sm">
                            </div>
                            @for($i = 0; $i < 3; $i++)
                                <div class="col-md-4">
                                    <label class="form-label">Part SKU {{ $i + 1 }}</label>
                                    <select name="part_id[]" class="form-select form-select-sm">
                                        <option value="">Select SKU</option>
                                        @foreach($products as $p)
                                            <option value="{{ $p->id }}">{{ $p->sku }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Qty</label>
                                    <input type="number" name="qty[]" min="1" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Unit Cost</label>
                                    <input type="number" step="0.01" name="unit_cost[]" class="form-control form-control-sm">
                                </div>
                            @endfor
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-sm">Create PO</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Purchase Orders</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead><tr><th>PO #</th><th>Supplier</th><th>Status</th><th>Actions</th></tr></thead>
                                <tbody>
                                    @forelse($purchaseOrders as $po)
                                        <tr>
                                            <td>{{ $po->po_number }}</td>
                                            <td>{{ $po->supplier?->name ?: $po->supplier?->company }}</td>
                                            <td>{{ $po->status }}</td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-1">
                                                    @if($po->status === 'draft')
                                                        <form method="POST" action="{{ route('po.send', $po->id) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Mark Sent</button></form>
                                                    @endif
                                                    @if(in_array($po->status, ['sent','partially_received'], true))
                                                        @foreach($po->items as $item)
                                                            @php $left = $item->quantityRemainingToReceive(); @endphp
                                                            @if($left > 0)
                                                                <form method="POST" action="{{ route('po.receive', $po->id) }}" class="d-flex gap-1 align-items-center">
                                                                    @csrf
                                                                    <input type="hidden" name="item_id" value="{{ $item->id }}">
                                                                    <span class="small">{{ $item->part?->sku }} left {{ $left }}</span>
                                                                    <input type="number" name="quantity" min="1" max="{{ $left }}" value="{{ $left }}" class="form-control form-control-sm" style="width:80px;">
                                                                    <button class="btn btn-sm btn-success">Recv</button>
                                                                </form>
                                                            @endif
                                                        @endforeach
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted">No purchase orders found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-low">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Low Stock</h5>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead><tr><th>SKU</th><th>Parent</th><th>Stock</th><th>Reorder At</th></tr></thead>
                                <tbody>
                                    @forelse($lowStock as $p)
                                        <tr>
                                            <td>{{ $p->sku }}</td>
                                            <td>{{ $p->parentPart?->sku ?? '—' }}</td>
                                            <td>{{ app(\App\Services\SparePartInventoryService::class)->totalAvailableForSku($p->sku) }}</td>
                                            <td>{{ $p->reorder_level }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted">No low stock items.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-parts">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Create Spare Part</h5>
                        <form method="POST" action="{{ route('parts.store') }}" class="row g-2">
                            @csrf
                            <div class="col-md-3">
                                <label class="form-label">Parent</label>
                                <select name="parent_id" class="form-select form-select-sm" required>
                                    <option value="">Select parent...</option>
                                    @foreach($parentOptions as $p)
                                        <option value="{{ $p->id }}">{{ $p->sku }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">SKU</label>
                                <select name="part_id" class="form-select form-select-sm" required>
                                    <option value="">Select SKU...</option>
                                    @foreach($products as $p)
                                        <option value="{{ $p->id }}">{{ $p->sku }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Qty</label>
                                <input type="number" name="quantity" min="1" value="1" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Part Name</label>
                                <input type="text" name="part_name" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">MSL-Part</label>
                                <input type="text" name="msl_part" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Supplier</label>
                                <select name="supplier_id" class="form-select form-select-sm">
                                    <option value="">Select supplier...</option>
                                    @foreach($suppliers as $sup)
                                        <option value="{{ $sup->id }}">{{ $sup->name ?: $sup->company }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary btn-sm" type="submit">Save</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-7">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Parts List</h5>
                                <div class="table-responsive" style="max-height:420px;overflow:auto;">
                                    <table class="table table-sm table-hover">
                                        <thead class="sticky-top bg-light"><tr><th>Part Name</th><th>SKU</th><th>Qty</th><th>Supplier</th><th>Parent</th><th>Reorder</th><th>Edit</th></tr></thead>
                                        <tbody>
                                            @forelse($parts as $p)
                                                <tr>
                                                    <td>{{ $p->sparePartDetail?->part_name ?: '—' }}</td>
                                                    <td>{{ $p->sku }}</td>
                                                    <td>{{ $p->sparePartDetail?->quantity ?? '—' }}</td>
                                                    <td>{{ $p->sparePartDetail?->supplier?->name ?: $p->sparePartDetail?->supplier?->company ?: '—' }}</td>
                                                    <td>{{ $p->parentPart?->sku ?: '—' }}</td>
                                                    <td>{{ $p->reorder_level ?? '—' }}</td>
                                                    <td>
                                                        <form method="POST" action="{{ route('parts.update', $p->id) }}" class="d-flex gap-1">
                                                            @csrf
                                                            @method('PATCH')
                                                            <select name="is_spare_part" class="form-select form-select-sm" style="width:120px;">
                                                                <option value="1" {{ $p->is_spare_part ? 'selected' : '' }}>Spare</option>
                                                                <option value="0" {{ !$p->is_spare_part ? 'selected' : '' }}>Normal</option>
                                                            </select>
                                                            <button class="btn btn-sm btn-outline-primary">Save</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="7" class="text-center text-muted">No spare parts found.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Tree (Top-level Spares)</h5>
                                <div class="tree small border rounded p-2" style="max-height:420px;overflow:auto;">
                                    @if(($tree ?? collect())->count())
                                        <ul>
                                            @foreach($tree as $node)
                                                <li>
                                                    {{ $node->sku }}
                                                    @if($node->childParts->count())
                                                        <ul>
                                                            @foreach($node->childParts as $child)
                                                                <li>{{ $child->sku }}</li>
                                                            @endforeach
                                                        </ul>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <span class="text-muted">No top-level spare parts.</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
