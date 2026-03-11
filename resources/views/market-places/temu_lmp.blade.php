@extends('layouts.vertical', ['title' => 'Temu LMP', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-flex align-items-center justify-content-between">
                    <h4 class="mb-0">Temu LMP</h4>
                    <a href="{{ route('temu.decrease') }}" class="btn btn-outline-primary btn-sm">
                        <i class="fa fa-arrow-left me-1"></i> Temu Decrease
                    </a>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('upload_errors') && count(session('upload_errors')) > 0)
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <strong>Row errors:</strong>
                <ul class="mb-0 mt-1">
                    @foreach(array_slice(session('upload_errors'), 0, 5) as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                    @if(count(session('upload_errors')) > 5)
                        <li>... and {{ count(session('upload_errors')) - 5 }} more</li>
                    @endif
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Upload section -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="fa fa-upload me-2"></i>Upload Temu LMP Data
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('temu.lmp.upload') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="row align-items-end">
                                <div class="col-md-6 col-lg-4">
                                    <label for="lmp_file" class="form-label fw-bold">File (Excel or CSV/TSV)</label>
                                    <input type="file" class="form-control" id="lmp_file" name="lmp_file"
                                           accept=".xlsx,.xls,.csv,.txt" required>
                                    <div class="form-text">
                                        Columns: SKU, LMP, LMP Link, LMP, LMP Link (tab or comma separated). Max 20MB.
                                    </div>
                                </div>
                                <div class="col-md-4 mt-2 mt-md-0">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-upload me-1"></i> Upload
                                    </button>
                                </div>
                            </div>
                        </form>
                        <p class="text-muted small mt-2 mb-0">
                            <i class="fa fa-info-circle me-1"></i>
                            Upload replaces all existing data. Use the same format as your Excel: first row = header (SKU, LMP, …), then one row per SKU.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Temu LMP Table</h5>
                        <span class="badge bg-secondary">{{ $records->total() }} record(s)</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>SKU</th>
                                        <th>LMP</th>
                                        <th>LMP Link</th>
                                        <th>LMP 2</th>
                                        <th>LMP Link 2</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($records as $index => $row)
                                        <tr>
                                            <td>{{ $records->firstItem() + $index }}</td>
                                            <td><strong>{{ $row->sku }}</strong></td>
                                            <td>{{ $row->lmp !== null ? number_format($row->lmp, 2) : '–' }}</td>
                                            <td class="text-break" style="max-width: 280px;">
                                                @if($row->lmp_link)
                                                    <a href="{{ $row->lmp_link }}" target="_blank" rel="noopener" class="small">Link</a>
                                                @else
                                                    –
                                                @endif
                                            </td>
                                            <td>{{ $row->lmp_2 !== null ? number_format($row->lmp_2, 2) : '–' }}</td>
                                            <td class="text-break" style="max-width: 280px;">
                                                @if($row->lmp_link_2)
                                                    <a href="{{ $row->lmp_link_2 }}" target="_blank" rel="noopener" class="small">Link</a>
                                                @else
                                                    –
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                No data. Upload an Excel or CSV/TSV file above.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if($records->hasPages())
                            <div class="d-flex justify-content-center py-3">
                                {{ $records->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
