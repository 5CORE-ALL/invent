@extends('layouts.vertical', ['title' => '2BDC', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
@include('layouts.shared.page-title', ['page_title' => '2BDC Items', 'sub_title' => '2BDC'])

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">2BDC Items</h4>
                </div>

                <div class="mb-3 d-flex justify-content-end align-items-center">
                    <div class="d-flex flex-column">
                        <label for="searchInput" class="mb-1">Search:</label>
                        <div style="width: 250px;">
                            <input type="text" id="searchInput" class="form-control" placeholder="Search by any keyword...">
                        </div>
                    </div>
                </div>


                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle mb-0">
                        <thead class="table-light text-center">
                            <tr>
                                <th class="fw-semibold">SL no.</th>
                                <th class="fw-semibold">Parent</th>
                                <th class="fw-semibold">SKU</th>
                                <th class="fw-semibold">INV</th>
                                <th class="fw-semibold">Verif WHM</th>
                                <th class="fw-semibold">Verified IH</th>
                                <th class="fw-semibold">2BDC</th>
                                {{-- <th class="fw-semibold">Continued</th> --}}
                                <th class="fw-semibold">DC Approved</th>
                                <th class="fw-semibold">DC Cleared Sheets</th>
                                {{-- <th class="fw-semibold">eBay Live</th> --}}
                                <th class="fw-semibold">DC Cleared Sites</th>
                                <th class="fw-semibold">Remarks</th>
                            </tr>
                        </thead>

                        <tbody class="text-center">
                            @php
                                $serial = 1;
                            @endphp
                            @foreach($data as $index => $item)
                                <tr>
                                    <td>{{ $serial }}</td>
                                    <td>{{ $item['Parent'] }}</td>
                                    <td>{{ $item['SKU'] }}</td>
                                    <td>{{ $item['INV'] }}</td>

                                    <td>
                                        <div class="d-flex align-items-center justify-content-center">
                                            @php
                                                $whm = strtolower(trim($item['Verif WHM'] ?? ''));
                                                $isWhmChecked = in_array($whm, ['true', 'yes', '1']);
                                                $bgColor = $isWhmChecked ? 'danger' : 'success';
                                            @endphp

                                            <div class="verification-status {{ $bgColor }}">
                                                <i class="mdi {{ $isWhmChecked ? 'mdi-check-circle' : 'mdi-close-circle' }}"></i>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="d-flex align-items-center justify-content-center">
                                            @php
                                                $ih = strtolower(trim($item['Verified IH'] ?? ''));
                                                $isIhChecked = in_array($ih, ['true', 'yes', '1']);
                                            @endphp
                                            <div class="verification-status {{ $isIhChecked ? 'danger' : 'success' }}">
                                                <i class="mdi {{ $isIhChecked ? 'mdi-check-circle' : 'mdi-close-circle' }}"></i>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center justify-content-center">
                                            @php
                                                $continued = strtolower(trim($item['2BDC'] ?? ''));
                                                $isContinued = in_array($continued, ['true', 'yes', '1']);
                                            @endphp
                                            <div class="verification-status {{ $isContinued ? 'danger' : 'success' }}">
                                                <i class="mdi {{ $isContinued ? 'mdi-check-circle' : 'mdi-close-circle' }}"></i>
                                            </div>
                                        </div>
                                    </td>
                                    {{-- <td>
                                        <div class="d-flex align-items-center justify-content-center">
                                            @php
                                                $continued = strtolower(trim($item['Continued'] ?? ''));
                                                $isContinued = in_array($continued, ['true', 'yes', '1']);
                                            @endphp
                                            <div class="verification-status {{ $isContinued ? 'danger' : 'success' }}">
                                                <i class="mdi {{ $isContinued ? 'mdi-check-circle' : 'mdi-close-circle' }}"></i>
                                            </div>
                                        </div>
                                    </td> --}}
                                    <td>
                                        <div class="d-flex align-items-center justify-content-center">
                                            @php
                                                $dcApproved = strtolower(trim($item['DC Approved'] ?? ''));
                                                $isDcApproved = in_array($dcApproved, ['true', 'yes', '1']);
                                            @endphp
                                            <div class="verification-status {{ $isDcApproved ? 'danger' : 'success' }}">
                                                <i class="mdi {{ $isDcApproved ? 'mdi-check-circle' : 'mdi-close-circle' }}"></i>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center justify-content-center">
                                            @php
                                                $dcSheets = strtolower(trim($item['DC Cleared Sheets'] ?? ''));
                                                $isDcSheets = in_array($dcSheets, ['true', 'yes', '1']);
                                            @endphp
                                            <div class="verification-status {{ $isDcSheets ? 'danger' : 'success' }}">
                                                <i class="mdi {{ $isDcSheets ? 'mdi-check-circle' : 'mdi-close-circle' }}"></i>
                                            </div>
                                        </div>
                                    </td>
                                    {{-- <td>
                                        <div class="d-flex align-items-center justify-content-center">
                                            @php
                                                $ebayLive = strtolower(trim($item['eBay Live'] ?? ''));
                                                $isEbayLive = $ebayLive === 'live';
                                            @endphp
                                            <div class="verification-status {{ $isEbayLive ? 'danger' : 'success' }}">
                                                <i class="mdi {{ $isEbayLive ? 'mdi-check-circle' : 'mdi-close-circle' }}"></i>
                                            </div>
                                        </div>
                                    </td> --}}
                                    <td>
                                        <div class="d-flex align-items-center justify-content-center">
                                            @php
                                                $dcSites = trim($item['DC Cleared Sites'] ?? '');
                                                $isDcSites = !empty($dcSites);
                                            @endphp
                                            <div class="verification-status {{ $isDcSites ? 'danger' : 'success' }}">
                                                <i class="mdi {{ $isDcSites ? 'mdi-check-circle' : 'mdi-close-circle' }}"></i>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $item['Remarks'] }}</td>
                                </tr>
                                @php
                                    $serial++;  
                                @endphp
                            @endforeach
                        </tbody>

                    </table>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <div class="pagination-wrapper">
                        {{ $data->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </div>
                </div>

                <style>
                    .pagination-wrapper {
                        width: auto;
                        overflow-x: auto;
                    }

                    .pagination-wrapper .pagination {
                        margin: 0;
                        background: #fff;
                        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
                        border-radius: 4px;
                        display: flex;
                        flex-wrap: nowrap;
                        gap: 4px;
                    }

                    .pagination-wrapper .page-item .page-link {
                        padding: 0.5rem 1rem;
                        min-width: 40px;
                        text-align: center;
                        color: #464646;
                        border: 1px solid #f1f1f1;
                        font-weight: 500;
                        transition: all 0.2s ease;
                        border-radius: 6px;
                    }

                    .pagination-wrapper .page-item.active .page-link {
                        background: linear-gradient(135deg, #727cf5, #6366f1);
                        border: none;
                        color: white;
                        font-weight: 600;
                        box-shadow: 0 2px 4px rgba(114, 124, 245, 0.2);
                    }

                    .pagination-wrapper .page-item .page-link:hover:not(.active) {
                        background-color: #f8f9fa;
                        color: #727cf5;
                        border-color: #e9ecef;
                    }

                    /* Hide the "Showing x to y of z results" text */
                    .pagination-wrapper p.small,
                    .pagination-wrapper div.flex.items-center.justify-between {
                        display: none !important;
                    }

                    @media (max-width: 576px) {
                        .pagination-wrapper .page-item .page-link {
                            padding: 0.4rem 0.8rem;
                            min-width: 35px;
                            font-size: 0.875rem;
                        }
                    }

                    .verification-status {
                        display: inline-flex;
                        align-items: center;
                        gap: 6px;
                        padding: 4px 12px;
                        border-radius: 20px;
                        font-size: 0.875rem;
                        font-weight: 500;
                        transition: all 0.2s;
                    }
                    .verification-status.success {
                        background-color: rgb(2, 138, 36);
                        color: #ffffff;
                    }
                    .verification-status.danger {
                        background-color: rgb(207, 19, 19);
                        color: #ffffff;
                    }
                    .verification-status i {
                        font-size: 1.2rem;
                    }
                </style>
            </div>
        </div>
    </div>
</div>
@endsection
@section('script')
<script>
    const input = document.getElementById('searchInput');
    const table = document.querySelector('.table-responsive table');

    const debounce = (func, wait) => {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };

    const handleSearch = debounce(function(e) {
        const query = e.target.value.trim();
        const url = new URL("{{ url()->current() }}");
        url.searchParams.set('search', query);

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newTable = doc.querySelector('.table-responsive table');
            if (newTable) {
                table.innerHTML = newTable.innerHTML;
            }
        });
    }, 300); // 300ms delay

    input.addEventListener('keyup', handleSearch);
</script>

@endsection