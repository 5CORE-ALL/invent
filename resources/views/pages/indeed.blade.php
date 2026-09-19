@extends('layouts.vertical', ['title' => 'Indeed'])

@section('css')
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .indeed-open-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #2557a7;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        .indeed-open-btn:hover { background: #1d4584; color: #fff; }
        #indeed-table { min-height: 180px; }
    </style>
@endsection

@section('content')
    <div class="container-fluid mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="fw-bold mb-1">Indeed</h2>
                <p class="text-muted mb-0">Open the Indeed employer page from here.</p>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div id="indeed-table"></div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        const indeedEmployerUrl = @json($employerUrl);
        new Tabulator('#indeed-table', {
            layout: 'fitColumns',
            placeholder: 'No pages',
            data: [{
                site: 'Indeed',
                page: 'Employer',
                url: indeedEmployerUrl
            }],
            columns: [
                { title: 'Site', field: 'site', minWidth: 140 },
                { title: 'Page', field: 'page', minWidth: 160 },
                {
                    title: 'Action',
                    field: 'url',
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    minWidth: 220,
                    formatter: function () {
                        return '<a class="indeed-open-btn" href="' + indeedEmployerUrl + '" target="_blank" rel="noopener noreferrer">'
                            + '<i class="fas fa-external-link-alt"></i> Open Indeed Employer</a>';
                    }
                }
            ]
        });
    </script>
@endsection
