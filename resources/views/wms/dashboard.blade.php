@extends('layouts.vertical', ['title' => 'Warehouse (WMS)', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('wms._subnav')

    <div class="row g-3">
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Warehouse structure</h5>
                    <p class="text-muted small">Manage warehouses, zones, racks, shelves, and bins with auto-generated location codes.</p>
                    <a href="{{ route('wms.structure') }}" class="btn btn-primary">Open</a>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Live inventory</h5>
                    <p class="text-muted small">Filter by warehouse, bin, or SKU. Colour hints for low or empty stock.</p>
                    <a href="{{ route('wms.inventory') }}" class="btn btn-primary">Open</a>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Barcode scan</h5>
                    <p class="text-muted small">USB scanner or device camera (mobile). Large input, instant lookup.</p>
                    <a href="{{ route('wms.scan') }}" class="btn btn-primary">Open</a>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Pick / Putaway</h5>
                    <p class="text-muted small">Lock stock for picks, confirm moves via AJAX (no full page reload).</p>
                    <div class="d-flex gap-2">
                        <a href="{{ route('wms.pick') }}" class="btn btn-outline-primary">Pick</a>
                        <a href="{{ route('wms.putaway') }}" class="btn btn-outline-primary">Putaway</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Smart locate</h5>
                    <p class="text-muted small">Search SKU or barcode and see full hierarchy path per bin.</p>
                    <a href="{{ route('wms.locate') }}" class="btn btn-primary">Open</a>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Movement history</h5>
                    <p class="text-muted small">Timeline of GRN, putaway, pick, pack, dispatch, and adjustments.</p>
                    <a href="{{ route('wms.movements') }}" class="btn btn-primary">Open</a>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-light border mt-4 small">
        <strong>Roles:</strong> set <code>users.role</code> to <code>warehouse_staff</code> or <code>staff</code> for scan/move;
        <code>warehouse_manager</code> or <code>manager</code> for adjustments; <code>admin</code> for full access.
        <strong>API:</strong> Sanctum personal access token required — <code>$user->createToken('wms')->plainTextToken</code>.
    </div>
@endsection
