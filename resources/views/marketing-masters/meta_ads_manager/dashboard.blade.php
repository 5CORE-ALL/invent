@extends('layouts.vertical', ['title' => 'Meta Ads Manager - Dashboard', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Marketing Masters', 'page_title' => 'Meta Ads Manager - Dashboard'])

    <div class="row">
        <!-- Date Range Filter -->
        <div class="col-12 mb-3">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('meta.ads.manager.dashboard') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-3">
                                <label>Ad Account</label>
                                <select name="ad_account_id" class="form-control" onchange="document.getElementById('filterForm').submit()">
                                    <option value="">All Accounts</option>
                                    @foreach($adAccounts as $account)
                                        <option value="{{ $account->id }}" {{ $selectedAdAccountId == $account->id ? 'selected' : '' }}>
                                            {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>Date Start</label>
                                <input type="date" name="date_start" class="form-control" value="{{ $dateStart }}" onchange="document.getElementById('filterForm').submit()">
                            </div>
                            <div class="col-md-3">
                                <label>Date End</label>
                                <input type="date" name="date_end" class="form-control" value="{{ $dateEnd }}" onchange="document.getElementById('filterForm').submit()">
                            </div>
                            <div class="col-md-3">
                                <label>&nbsp;</label><br>
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="text-muted fw-normal mt-0 text-truncate" title="Spend">Spend</h5>
                            <h3 class="my-2 py-1">${{ number_format($currentKPIs['spend'], 2) }}</h3>
                            <p class="mb-0 text-muted">
                                <span class="text-{{ $changes['spend'] >= 0 ? 'success' : 'danger' }} me-2">
                                    <i class="mdi mdi-arrow-{{ $changes['spend'] >= 0 ? 'up' : 'down' }}-bold"></i> {{ abs($changes['spend']) }}%
                                </span>
                                <span class="text-nowrap">vs previous period</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="text-muted fw-normal mt-0 text-truncate" title="Impressions">Impressions</h5>
                            <h3 class="my-2 py-1">{{ number_format($currentKPIs['impressions']) }}</h3>
                            <p class="mb-0 text-muted">
                                <span class="text-{{ $changes['impressions'] >= 0 ? 'success' : 'danger' }} me-2">
                                    <i class="mdi mdi-arrow-{{ $changes['impressions'] >= 0 ? 'up' : 'down' }}-bold"></i> {{ abs($changes['impressions']) }}%
                                </span>
                                <span class="text-nowrap">vs previous period</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="text-muted fw-normal mt-0 text-truncate" title="Clicks">Clicks</h5>
                            <h3 class="my-2 py-1">{{ number_format($currentKPIs['clicks']) }}</h3>
                            <p class="mb-0 text-muted">
                                <span class="text-{{ $changes['clicks'] >= 0 ? 'success' : 'danger' }} me-2">
                                    <i class="mdi mdi-arrow-{{ $changes['clicks'] >= 0 ? 'up' : 'down' }}-bold"></i> {{ abs($changes['clicks']) }}%
                                </span>
                                <span class="text-nowrap">vs previous period</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="text-muted fw-normal mt-0 text-truncate" title="CTR">CTR</h5>
                            <h3 class="my-2 py-1">{{ number_format($currentKPIs['ctr'], 2) }}%</h3>
                            <p class="mb-0 text-muted">
                                <span class="text-{{ $changes['ctr'] >= 0 ? 'success' : 'danger' }} me-2">
                                    <i class="mdi mdi-arrow-{{ $changes['ctr'] >= 0 ? 'up' : 'down' }}-bold"></i> {{ abs($changes['ctr']) }}%
                                </span>
                                <span class="text-nowrap">vs previous period</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="text-muted fw-normal mt-0 text-truncate" title="CPC">CPC</h5>
                            <h3 class="my-2 py-1">${{ number_format($currentKPIs['cpc'], 2) }}</h3>
                            <p class="mb-0 text-muted">
                                <span class="text-{{ $changes['cpc'] <= 0 ? 'success' : 'danger' }} me-2">
                                    <i class="mdi mdi-arrow-{{ $changes['cpc'] <= 0 ? 'down' : 'up' }}-bold"></i> {{ abs($changes['cpc']) }}%
                                </span>
                                <span class="text-nowrap">vs previous period</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="text-muted fw-normal mt-0 text-truncate" title="CPM">CPM</h5>
                            <h3 class="my-2 py-1">${{ number_format($currentKPIs['cpm'], 2) }}</h3>
                            <p class="mb-0 text-muted">
                                <span class="text-{{ $changes['cpm'] <= 0 ? 'success' : 'danger' }} me-2">
                                    <i class="mdi mdi-arrow-{{ $changes['cpm'] <= 0 ? 'down' : 'up' }}-bold"></i> {{ abs($changes['cpm']) }}%
                                </span>
                                <span class="text-nowrap">vs previous period</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="text-muted fw-normal mt-0 text-truncate" title="ROAS">ROAS</h5>
                            <h3 class="my-2 py-1">{{ number_format($currentKPIs['purchase_roas'], 2) }}</h3>
                            <p class="mb-0 text-muted">
                                <span class="text-{{ $changes['purchase_roas'] >= 0 ? 'success' : 'danger' }} me-2">
                                    <i class="mdi mdi-arrow-{{ $changes['purchase_roas'] >= 0 ? 'up' : 'down' }}-bold"></i> {{ abs($changes['purchase_roas']) }}%
                                </span>
                                <span class="text-nowrap">vs previous period</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="text-muted fw-normal mt-0 text-truncate" title="CPA">CPA</h5>
                            <h3 class="my-2 py-1">${{ number_format($currentKPIs['cpa'], 2) }}</h3>
                            <p class="mb-0 text-muted">
                                <span class="text-{{ $changes['cpa'] <= 0 ? 'success' : 'danger' }} me-2">
                                    <i class="mdi mdi-arrow-{{ $changes['cpa'] <= 0 ? 'down' : 'up' }}-bold"></i> {{ abs($changes['cpa']) }}%
                                </span>
                                <span class="text-nowrap">vs previous period</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Quick Actions</h5>
                    <div class="d-flex gap-2">
                        <a href="{{ route('meta.ads.manager.accounts') }}" class="btn btn-primary">View Accounts</a>
                        <a href="{{ route('meta.ads.manager.campaigns') }}" class="btn btn-primary">View Campaigns</a>
                        <a href="{{ route('meta.ads.manager.adsets') }}" class="btn btn-primary">View Ad Sets</a>
                        <a href="{{ route('meta.ads.manager.ads') }}" class="btn btn-primary">View Ads</a>
                        <a href="{{ route('meta.ads.manager.automation') }}" class="btn btn-info">Automation Rules</a>
                        <a href="{{ route('meta.ads.manager.logs') }}" class="btn btn-secondary">View Logs</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
@endsection

