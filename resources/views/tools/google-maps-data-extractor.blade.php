@extends('layouts.vertical', ['title' => 'Google Maps Data Extractor', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <style>
        .extractor-summary-card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #ffffff;
        }

        .extractor-table th {
            white-space: nowrap;
            background: #f8fafc;
        }

        .extractor-table td {
            vertical-align: top;
        }

        .extractor-url {
            max-width: 260px;
            word-break: break-word;
        }

        .extractor-progress {
            display: none;
        }

        .extractor-loading-overlay {
            align-items: center;
            background: rgba(15, 23, 42, 0.72);
            bottom: 0;
            display: none;
            justify-content: center;
            left: 0;
            position: fixed;
            right: 0;
            top: 0;
            z-index: 2000;
        }

        .extractor-loading-card {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
            display: flex;
            flex-direction: column;
            max-width: 460px;
            min-height: 430px;
            overflow: hidden;
            padding: 24px;
            width: calc(100% - 32px);
        }

        .extractor-loading-card-header {
            align-items: flex-start;
            display: flex;
            flex-shrink: 0;
            gap: 12px;
            justify-content: space-between;
            margin-bottom: 0.25rem;
        }

        .extractor-loading-card-header h4 {
            flex: 1;
            margin-bottom: 0 !important;
        }

        #extractor-overlay-close-btn {
            flex-shrink: 0;
            margin-top: 2px;
        }

        .extractor-loading-control-actions {
            gap: 8px !important;
            padding-top: 2px;
            width: 100%;
        }

        .extractor-loading-control-actions .btn {
            flex: 1 1 auto;
        }

        #extractor-loading-text {
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
            display: -webkit-box;
            flex-shrink: 0;
            line-height: 1.45;
            margin-bottom: 1rem !important;
            max-height: 4.35em;
            min-height: 4.35em;
            overflow: hidden;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .extractor-loading-stats {
            flex-shrink: 0;
            min-height: 1.25rem;
        }

        .extractor-loading-actions {
            border-top: 1px solid #eef2f7;
            flex-shrink: 0;
            margin-top: auto;
            padding-top: 16px;
        }

        .extractor-background-link {
            background: none;
            border: 0;
            color: #0d9488;
            display: block;
            font-size: 0.8125rem;
            font-weight: 500;
            letter-spacing: 0.01em;
            margin: 0 0 14px;
            padding: 4px 0;
            text-align: center;
            text-decoration: none;
            width: 100%;
        }

        .extractor-background-link:hover,
        .extractor-background-link:focus {
            color: #0f766e;
            text-decoration: none;
        }

        #extractor-loading-records,
        #extractor-loading-step {
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        #extractor-loading-bar {
            transition: width 0.45s ease;
        }

        .city-picker-menu {
            border: 1px solid #d0d5dd;
            border-radius: 8px;
            display: none;
            margin-top: 6px;
            max-height: 320px;
            overflow: hidden;
        }

        .city-picker-list {
            max-height: 230px;
            overflow-y: auto;
            padding: 8px 12px;
        }

        #location_preview {
            max-height: 90px;
            overflow-y: auto;
            word-break: break-word;
        }

        .extractor-loading-log {
            background: #0f172a;
            border-radius: 8px;
            color: #dbeafe;
            flex-shrink: 0;
            font-size: 12px;
            line-height: 1.45;
            margin-top: 14px;
            max-height: 170px;
            min-height: 170px;
            overflow-y: auto;
            padding: 10px 12px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .extractor-background-status {
            align-items: center;
            background: #0f172a;
            border: 0;
            border-radius: 999px;
            bottom: 24px;
            box-shadow: 0 12px 35px rgba(15, 23, 42, 0.28);
            color: #ffffff;
            display: none;
            gap: 10px;
            max-width: min(420px, calc(100vw - 32px));
            padding: 10px 16px;
            position: fixed;
            right: 24px;
            text-align: left;
            z-index: 1999;
        }

        .extractor-background-status small {
            color: #cbd5e1;
            display: block;
            line-height: 1.25;
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .extractor-background-status .spinner-border {
            height: 16px;
            width: 16px;
        }

        .extractor-pagination svg {
            height: 16px;
            width: 16px;
        }

        .extractor-pagination .pagination {
            flex-wrap: wrap;
            gap: 4px;
            justify-content: center;
            margin-bottom: 0;
        }
    </style>
@endsection

@section('content')
    <div class="extractor-loading-overlay" id="extractor-loading-overlay">
        <div class="extractor-loading-card">
            <div class="extractor-loading-card-header">
                <h4 id="extractor-loading-title">Fetching Google Maps Leads</h4>
                <button type="button" class="btn-close" id="extractor-overlay-close-btn" aria-label="Close"></button>
            </div>
            <p class="text-muted mb-3" id="extractor-loading-text">
                Starting extraction. Please keep this page open...
            </p>
            <div class="progress" style="height: 14px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated"
                    id="extractor-loading-bar"
                    role="progressbar"
                    style="width: 8%;"
                    aria-valuenow="8"
                    aria-valuemin="0"
                    aria-valuemax="100"></div>
            </div>
            <div class="d-flex justify-content-between small text-muted mt-2 extractor-loading-stats">
                <span id="extractor-loading-records">0 records fetched</span>
                <span id="extractor-loading-step">Preparing...</span>
            </div>
            <div class="extractor-loading-log" id="extractor-loading-log">Waiting for scraper log...</div>
            <div class="extractor-loading-actions">
                <button type="button" class="extractor-background-link" id="extractor-background-link">
                    Run in Background
                </button>
                <div class="d-flex flex-wrap gap-2 extractor-loading-control-actions">
                    <button type="button" class="btn btn-sm btn-warning" id="extractor-pause-resume-btn" data-state="running">Pause</button>
                    <button type="button" class="btn btn-sm btn-secondary" id="extractor-stop-btn">Stop & Keep</button>
                    <button type="button" class="btn btn-sm btn-danger" id="extractor-cancel-btn">Cancel & Discard</button>
                </div>
            </div>
        </div>
    </div>

    <button type="button" class="extractor-background-status" id="extractor-background-status">
        <span class="spinner-border spinner-border-sm" id="extractor-background-spinner" aria-hidden="true"></span>
        <span>
            <strong id="extractor-background-title">Extractor running</strong>
            <small id="extractor-background-text">Click to view progress</small>
        </span>
    </button>

    <div class="modal fade" id="enrichment-mode-modal" tabindex="-1" aria-labelledby="enrichment-mode-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="enrichment-mode-modal-label">Choose Enrichment Mode</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        <strong id="enrichment-existing-count">Some leads</strong> already have enriched data.
                    </p>
                    <p class="text-muted mb-0">
                        Do you want to continue only rows still missing data, or start a new enrichment pass over all website rows?
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-enrichment-mode="cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" data-enrichment-mode="pending">Continue Remaining</button>
                    <button type="button" class="btn btn-warning" data-enrichment-mode="all">Start New Pass</button>
                </div>
            </div>
        </div>
    </div>

    @include('layouts.shared.page-title', [
        'page_title' => 'Google Maps Data Extractor',
        'sub_title' => 'Tools',
    ])

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Please fix the following:</strong>
            <ul class="mb-0 mt-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-xl-3">
            <div class="card">
                <div class="card-header">
                    <h4 class="header-title mb-0">Run Extraction</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('google-maps-data-extractor.search') }}" method="POST" id="extractor-search-form">
                        @csrf

                        <div class="mb-3">
                            <label for="query" class="form-label">Search Query</label>
                            <input type="text" name="query" id="query" class="form-control"
                                value="{{ old('query', $activeSearch->query ?? '') }}"
                                placeholder="Example: music schools" required>
                        </div>

                        <input type="hidden" name="location" id="location"
                            value="{{ old('location', $activeSearch->location ?? '') }}">
                        <input type="hidden" name="progress_token" id="progress_token" value="">

                        <div class="mb-3">
                            <label for="location_country" class="form-label">Country</label>
                            <input type="text" class="form-control" id="location_country_display" value="United States" readonly>
                            <input type="hidden" name="location_country" id="location_country" value="United States">
                        </div>

                        <input type="hidden" name="location_scope" value="specific_city">

                        <div class="mb-3">
                            <label for="location_state" class="form-label">State / Region</label>
                            <select name="location_state" id="location_state" class="form-select" required>
                                <option value="">Select state / region</option>
                            </select>
                        </div>

                        <div class="mb-3 d-none" id="location_city_wrap">
                            <label class="form-label">Cities</label>
                            <button type="button" class="form-control text-start bg-white" id="city_picker_toggle">
                                Select cities
                            </button>
                            <div class="city-picker-menu bg-white" id="city_picker_menu">
                                <div class="p-2 border-bottom">
                                    <input type="text" class="form-control form-control-sm" id="city_picker_search"
                                        placeholder="Search cities...">
                                    <div class="d-flex gap-2 mt-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="city_picker_select_all">
                                            Select all visible
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="city_picker_clear">
                                            Clear
                                        </button>
                                    </div>
                                </div>
                                <div class="city-picker-list" id="city_picker_list"></div>
                            </div>
                            <input type="hidden" name="location_city_payload" id="location_city_payload" value="[]">
                            <div class="form-text">
                                Search and select multiple cities. Each city keeps paginating until no new records appear, the limit is reached, or all cities are checked.
                            </div>
                        </div>

                        <div class="alert alert-light border small py-2" id="location_preview">
                            Select a state, then choose one or more cities.
                        </div>

                        <div class="mb-3">
                            <label for="limit" class="form-label">Result Limit</label>
                            <select name="limit" id="limit" class="form-select">
                                <option value="all" @selected((string) old('limit', ($activeSearch?->result_limit ?? 10) === 0 ? 'all' : ($activeSearch->result_limit ?? 10)) === 'all')>
                                    All available data
                                </option>
                                @foreach ([5, 10, 15, 20, 25, 50, 100, 250, 500, 1000, 2500, 5000] as $limit)
                                    <option value="{{ $limit }}"
                                        @selected((int) old('limit', $activeSearch->result_limit ?? 10) === $limit)>
                                        {{ $limit }} results
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                Choose All available data to fetch until every selected city/search is exhausted.
                            </div>
                        </div>

                        <div class="form-text text-danger mb-2 d-none" id="extractor-form-error"></div>

                        <button type="submit" class="btn btn-primary w-100" id="extract-submit-btn" disabled>
                            <i class="ri-search-line me-1"></i>Extract Real Data
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="header-title mb-0">Recent Searches</h4>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse ($searches as $search)
                            <div class="list-group-item {{ $activeSearch?->id === $search->id ? 'active' : '' }}">
                                <div class="d-flex justify-content-between gap-2 align-items-start">
                                    <a href="{{ route('google-maps-data-extractor.show', $search) }}"
                                        class="text-decoration-none flex-grow-1 {{ $activeSearch?->id === $search->id ? 'text-white' : 'text-body' }}">
                                        <strong>{{ $search->query }}</strong>
                                        <div class="small {{ $activeSearch?->id === $search->id ? 'text-white-50' : 'text-muted' }}">
                                            {{ $search->location ?: 'No location' }} · {{ $search->results_count }} result(s)
                                        </div>
                                    </a>
                                    <div class="d-flex flex-column align-items-end gap-2">
                                        <span class="badge bg-{{ $search->status === 'completed' ? 'success' : ($search->status === 'failed' ? 'danger' : 'warning') }}">
                                            {{ ucfirst($search->status) }}
                                        </span>
                                        <form action="{{ route('google-maps-data-extractor.destroy', $search) }}" method="POST"
                                            onsubmit="return confirm('Delete this search and all extracted leads?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-link text-danger p-0 border-0 shadow-none" title="Delete search">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-3 text-muted">No searches yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-9">
            @if ($activeSearch)
                <div class="extractor-summary-card p-3 mb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h4 class="mb-1">{{ $activeSearch->query }}</h4>
                            <div class="text-muted">
                                {{ $activeSearch->location ?: 'No location' }} ·
                                Status: <strong>{{ ucfirst($activeSearch->status) }}</strong> ·
                                Results: <strong>{{ $activeSearch->results_count }}</strong>
                            </div>
                        </div>

                        @if ($results->isNotEmpty())
                            <div class="d-flex flex-wrap gap-2">
                                <form action="{{ route('google-maps-data-extractor.enrich', $activeSearch) }}" method="POST" id="legacy-enrich-form" class="d-none">
                                    @csrf
                                </form>

                                <button type="button"
                                    class="btn btn-primary"
                                    id="enrich-results-btn"
                                    data-enrich-url="{{ route('google-maps-data-extractor.enrich-batch', $activeSearch) }}">
                                    <i class="ri-links-line me-1"></i>Enrich Website Data
                                </button>

                                <a href="{{ route('google-maps-data-extractor.export', $activeSearch) }}"
                                    class="btn btn-success"
                                    id="export-results-link"
                                    data-base-export-url="{{ route('google-maps-data-extractor.export', $activeSearch) }}">
                                    <i class="ri-download-line me-1"></i>Export CSV
                                </a>
                            </div>
                        @endif
                    </div>

                    @if ($results->isNotEmpty())
                        <div class="small text-muted mt-2">
                            Enrichment crawls saved websites in controlled batches and shows progress in the overlay.
                        </div>
                    @endif

                    @if ($activeSearch->error_message)
                        <div class="alert alert-danger mt-3 mb-0">{{ $activeSearch->error_message }}</div>
                    @endif
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="header-title mb-0">Extracted Leads</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <label for="lead-filter" class="form-label mb-0 fw-semibold">Filter:</label>
                            <select id="lead-filter" class="form-select form-select-sm" style="max-width: 240px;">
                                <option value="all">All leads</option>
                                <option value="email">Has email</option>
                                <option value="phone">Has phone</option>
                                <option value="email_or_phone">Has email or phone</option>
                                <option value="social">Has social links</option>
                            </select>
                            <span class="small text-muted" id="lead-filter-count"></span>
                            <span class="small text-muted ms-auto" id="lead-pagination-summary"></span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover extractor-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Address</th>
                                        <th>Website</th>
                                        <th>Social</th>
                                        <th>Maps</th>
                                        <th>Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($results as $result)
                                        <tr class="extractor-result-row"
                                            data-has-email="{{ $result->email ? '1' : '0' }}"
                                            data-has-phone="{{ $result->phone ? '1' : '0' }}"
                                            data-has-social="{{ ! empty($result->social_links) ? '1' : '0' }}">
                                            <td>
                                                <strong>{{ $result->name }}</strong>
                                                @if ($result->category)
                                                    <div class="small text-muted">{{ $result->category }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $result->email ?: '-' }}</td>
                                            <td>{{ $result->phone ?: '-' }}</td>
                                            <td>{{ $result->address ?: '-' }}</td>
                                            <td class="extractor-url">
                                                @if ($result->website)
                                                    <a href="{{ $result->website }}" target="_blank" rel="noopener">
                                                        {{ $result->website }}
                                                    </a>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                @forelse (($result->social_links ?? []) as $socialLink)
                                                    <a href="{{ $socialLink }}" target="_blank" rel="noopener" class="d-block">
                                                        {{ parse_url($socialLink, PHP_URL_HOST) ?: 'Social' }}
                                                    </a>
                                                @empty
                                                    -
                                                @endforelse
                                            </td>
                                            <td>
                                                @if ($result->maps_url)
                                                    <a href="{{ $result->maps_url }}" target="_blank" rel="noopener">Open</a>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                {{ $result->rating ?: '-' }}
                                                @if ($result->reviews_count)
                                                    <div class="small text-muted">{{ $result->reviews_count }} reviews</div>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                No extracted leads for this search.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="extractor-pagination mt-3" id="lead-client-pagination"></div>
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="ri-map-pin-search-line display-4 text-primary"></i>
                        <h4 class="mt-3">Start a Google Maps data extraction</h4>
                        <p class="text-muted mb-0">
                            Enter a business or school type and location to test real-data extraction without an API key.
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('script')
    <script type="module">
        import { State, City } from 'https://cdn.jsdelivr.net/npm/country-state-city@3.2.1/+esm';

        document.addEventListener('DOMContentLoaded', function () {
            const countryCode = 'US';
            const stateOptions = State.getStatesOfCountry(countryCode);

            const countrySelect = document.getElementById('location_country');
            const stateSelect = document.getElementById('location_state');
            const cityWrap = document.getElementById('location_city_wrap');
            const extractSubmitBtn = document.getElementById('extract-submit-btn');
            const extractorFormError = document.getElementById('extractor-form-error');
            const queryInput = document.getElementById('query');
            const cityPickerToggle = document.getElementById('city_picker_toggle');
            const cityPickerMenu = document.getElementById('city_picker_menu');
            const cityPickerSearch = document.getElementById('city_picker_search');
            const cityPickerList = document.getElementById('city_picker_list');
            const cityPickerSelectAll = document.getElementById('city_picker_select_all');
            const cityPickerClear = document.getElementById('city_picker_clear');
            const selectedCityPayloadInput = document.getElementById('location_city_payload');
            const locationInput = document.getElementById('location');
            const locationPreview = document.getElementById('location_preview');
            let availableCities = [];
            let selectedCityValues = new Set();

            function populateStates() {
                if (!countrySelect || !stateSelect) {
                    return;
                }

                stateSelect.innerHTML = '<option value="">Select state / region</option>';

                stateOptions.forEach(function (state) {
                    const option = document.createElement('option');
                    option.value = state.name;
                    option.dataset.stateCode = state.isoCode;
                    option.textContent = state.name;
                    stateSelect.appendChild(option);
                });

                updateLocationFields();
            }

            function populateCities() {
                if (!countrySelect || !stateSelect || !cityPickerList) {
                    return;
                }

                const selectedState = stateSelect.selectedOptions[0];
                const stateCode = selectedState?.dataset.stateCode || '';
                availableCities = stateCode
                    ? City.getCitiesOfState(countryCode, stateCode)
                        .map(city => city.name)
                        .filter(city => !/\bCounty$/i.test(city))
                    : [];
                availableCities = Array.from(new Set(availableCities)).sort((first, second) => first.localeCompare(second));
                renderCityPicker();
            }

            function getSelectedCities() {
                return Array.from(selectedCityValues);
            }

            function renderCityPicker() {
                if (!cityPickerList) {
                    return;
                }

                const search = (cityPickerSearch?.value || '').toLowerCase();
                const visibleCities = availableCities.filter(city => city.toLowerCase().includes(search));
                cityPickerList.innerHTML = '';

                visibleCities.forEach(function (city, index) {
                    const id = `city_option_${index}_${city.replace(/[^a-z0-9]/gi, '_')}`;
                    const wrapper = document.createElement('div');
                    const input = document.createElement('input');
                    const label = document.createElement('label');

                    wrapper.className = 'form-check';
                    input.className = 'form-check-input city-picker-option';
                    input.type = 'checkbox';
                    input.value = city;
                    input.id = id;
                    input.checked = selectedCityValues.has(city);
                    label.className = 'form-check-label';
                    label.htmlFor = id;
                    label.textContent = city;
                    wrapper.appendChild(input);
                    wrapper.appendChild(label);
                    cityPickerList.appendChild(wrapper);
                });

                if (visibleCities.length === 0) {
                    cityPickerList.innerHTML = '<div class="text-muted small">No cities found for this state/search.</div>';
                }

                cityPickerList.querySelectorAll('.city-picker-option').forEach(function (input) {
                    input.addEventListener('change', function () {
                        if (input.checked) {
                            selectedCityValues.add(input.value);
                        } else {
                            selectedCityValues.delete(input.value);
                        }

                        updateLocationFields();
                    });
                });

                updateCityPickerLabel();
            }

            function updateCityPickerLabel() {
                if (!cityPickerToggle) {
                    return;
                }

                const selectedCities = getSelectedCities();
                cityPickerToggle.textContent = selectedCities.length > 0
                    ? `${selectedCities.length} cities selected`
                    : 'Select cities';
            }

            function syncSelectedCityInputs() {
                if (!selectedCityPayloadInput) {
                    return;
                }

                selectedCityPayloadInput.value = JSON.stringify(getSelectedCities());
            }

            function getPlannedCityFetches() {
                return Array.from(new Set(getSelectedCities().filter(Boolean)));
            }

            function composeLocation(compact = false) {
                const country = countrySelect?.value || '';
                const state = stateSelect?.value || '';
                const selectedCities = getSelectedCities();

                if (!state) {
                    return '';
                }

                if (selectedCities.length > 0) {
                    if (compact) {
                        return [`${selectedCities.length} selected cities`, state, country].filter(Boolean).join(', ');
                    }

                    return [selectedCities.join(', '), state, country].filter(Boolean).join(', ');
                }

                return [state, country].filter(Boolean).join(', ');
            }

            function isExtractionFormValid() {
                const query = queryInput?.value.trim() || '';
                const state = stateSelect?.value.trim() || '';
                return query !== '' && state !== '' && getSelectedCities().length > 0;
            }

            function showExtractorFormError(message = '') {
                if (!extractorFormError) {
                    return;
                }

                if (message) {
                    extractorFormError.textContent = message;
                    extractorFormError.classList.remove('d-none');
                    return;
                }

                extractorFormError.textContent = '';
                extractorFormError.classList.add('d-none');
            }

            function updateExtractSubmitState() {
                if (!extractSubmitBtn) {
                    return;
                }

                const valid = isExtractionFormValid();
                extractSubmitBtn.disabled = !valid;

                if (valid) {
                    showExtractorFormError();
                }
            }

            function updateLocationFields() {
                const selectedCities = getSelectedCities();
                const hasState = Boolean(stateSelect?.value);

                cityWrap?.classList.toggle('d-none', !hasState);
                populateCities();

                const composed = composeLocation(true);
                if (locationInput) {
                    locationInput.value = composed;
                }

                if (locationPreview) {
                    if (!hasState) {
                        locationPreview.textContent = 'Select a state, then choose one or more cities.';
                    } else if (selectedCities.length > 0) {
                        locationPreview.textContent = `${selectedCities.length} city(ies) selected in ${[stateSelect.value, countrySelect?.value || ''].filter(Boolean).join(', ')}`;
                    } else {
                        locationPreview.textContent = `Select one or more cities in ${[stateSelect.value, countrySelect?.value || ''].filter(Boolean).join(', ')}.`;
                    }
                }

                updateCityPickerLabel();
                syncSelectedCityInputs();
                updateExtractSubmitState();
            }

            countrySelect?.addEventListener('change', populateStates);
            stateSelect?.addEventListener('change', function () {
                selectedCityValues.clear();
                updateLocationFields();
            });
            queryInput?.addEventListener('input', updateExtractSubmitState);
            cityPickerToggle?.addEventListener('click', function () {
                if (!cityPickerMenu) {
                    return;
                }

                cityPickerMenu.style.display = cityPickerMenu.style.display === 'block' ? 'none' : 'block';
            });
            cityPickerSearch?.addEventListener('input', renderCityPicker);
            cityPickerSelectAll?.addEventListener('click', function () {
                cityPickerList?.querySelectorAll('.city-picker-option').forEach(input => {
                    input.checked = true;
                    selectedCityValues.add(input.value);
                });
                updateLocationFields();
            });
            cityPickerClear?.addEventListener('click', function () {
                selectedCityValues.clear();
                cityPickerList?.querySelectorAll('.city-picker-option').forEach(input => {
                    input.checked = false;
                });
                updateLocationFields();
            });
            document.addEventListener('click', function (event) {
                if (!cityPickerMenu || cityPickerMenu.contains(event.target) || cityPickerToggle?.contains(event.target)) {
                    return;
                }

                cityPickerMenu.style.display = 'none';
            });
            populateStates();

            const searchForm = document.getElementById('extractor-search-form');
            const loadingOverlay = document.getElementById('extractor-loading-overlay');
            const loadingTitle = document.getElementById('extractor-loading-title');
            const loadingBar = document.getElementById('extractor-loading-bar');
            const loadingText = document.getElementById('extractor-loading-text');
            const loadingRecords = document.getElementById('extractor-loading-records');
            const loadingStep = document.getElementById('extractor-loading-step');
            const loadingLog = document.getElementById('extractor-loading-log');
            const progressTokenInput = document.getElementById('progress_token');
            const backgroundLink = document.getElementById('extractor-background-link');
            const backgroundStatus = document.getElementById('extractor-background-status');
            const backgroundStatusTitle = document.getElementById('extractor-background-title');
            const backgroundStatusText = document.getElementById('extractor-background-text');
            const backgroundStatusSpinner = document.getElementById('extractor-background-spinner');
            const pauseResumeButton = document.getElementById('extractor-pause-resume-btn');
            const stopButton = document.getElementById('extractor-stop-btn');
            const cancelButton = document.getElementById('extractor-cancel-btn');
            const overlayCloseButton = document.getElementById('extractor-overlay-close-btn');
            let activeProgressToken = '';
            let activeProgressPoller = null;
            let activeRunInBackground = false;
            let activeProgressType = 'fetch';
            let activeCompletionUrl = '';
            let latestProgressPayload = null;
            let displayedProgressPercent = 8;
            let indeterminateProgressPercent = 8;
            let activeForegroundMonitor = false;
            const backgroundStorageKey = 'google_maps_extractor_background_job';

            function makeProgressToken() {
                if (window.crypto?.randomUUID) {
                    return window.crypto.randomUUID().replace(/-/g, '');
                }

                return `${Date.now()}${Math.random().toString(36).slice(2)}`;
            }

            function progressUrlForToken(token) {
                return `{{ route('google-maps-data-extractor.progress', '__TOKEN__') }}`.replace('__TOKEN__', encodeURIComponent(token));
            }

            function startUrl() {
                return `{{ route('google-maps-data-extractor.start') }}`;
            }

            function processUrl() {
                return `{{ route('google-maps-data-extractor.process') }}`;
            }

            function controlUrlForToken(token) {
                return `{{ route('google-maps-data-extractor.control', '__TOKEN__') }}`.replace('__TOKEN__', encodeURIComponent(token));
            }

            function showUrlForSearch(searchId) {
                return new URL(String(searchId), extractorBaseUrl()).toString();
            }

            function extractorBaseUrl() {
                const path = window.location.pathname.replace(/\/\d+\/?$/, '').replace(/\/$/, '');
                return `${window.location.origin}${path}/`;
            }

            function completionUrlFromPayload(payload = {}) {
                if (payload.search_id) {
                    return showUrlForSearch(payload.search_id);
                }

                return activeCompletionUrl || '';
            }

            function compactUrl(value, maxLength = 80) {
                const url = String(value || '').trim();

                if (!url) {
                    return '';
                }

                try {
                    const parsed = new URL(url);
                    const compact = parsed.hostname.replace(/^www\./, '') + parsed.pathname;
                    return compact.length > maxLength ? `${compact.slice(0, maxLength - 3)}...` : compact;
                } catch (error) {
                    return url.length > maxLength ? `${url.slice(0, maxLength - 3)}...` : url;
                }
            }

            function saveBackgroundJob(extra = {}) {
                if (!activeProgressToken) {
                    return;
                }

                const existingJob = readBackgroundJob() || {};
                const isSameJob = existingJob.token === activeProgressToken;

                localStorage.setItem(backgroundStorageKey, JSON.stringify({
                    ...(isSameJob ? existingJob : {}),
                    token: activeProgressToken,
                    type: activeProgressType,
                    completionUrl: activeCompletionUrl,
                    status: 'running',
                    updatedAt: Date.now(),
                    ...extra,
                }));
            }

            function clearBackgroundJob() {
                localStorage.removeItem(backgroundStorageKey);
            }

            function readBackgroundJob() {
                try {
                    const payload = JSON.parse(localStorage.getItem(backgroundStorageKey) || 'null');
                    return payload && payload.token ? payload : null;
                } catch (error) {
                    clearBackgroundJob();
                    return null;
                }
            }

            async function sendExtractionControl(action) {
                if (!activeProgressToken) {
                    if (loadingText) {
                        loadingText.textContent = 'No active job to control. Start a new run if needed.';
                    }
                    return;
                }

                try {
                    const response = await fetch(controlUrlForToken(activeProgressToken), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({ action }),
                    });

                    if (!response.ok) {
                        const errorPayload = await response.json().catch(() => ({}));
                        throw new Error(errorPayload.message || 'Unable to update extraction control.');
                    }

                    const data = await response.json();

                    if (data.complete && loadingOverlay?.style.display === 'flex') {
                        if (data.redirect_url) {
                            activeCompletionUrl = data.redirect_url;
                        }
                        if (data.cancelled || data.stopped) {
                            updateProgressOverlay({
                                status: data.cancelled ? 'cancelled' : 'stopped',
                                message: data.cancelled
                                    ? (activeProgressType === 'enrich'
                                        ? 'Website enrichment cancelled. Existing lead data was kept.'
                                        : 'Extraction cancelled. Fetched records were discarded.')
                                    : (activeProgressType === 'enrich'
                                        ? 'Website enrichment stopped. Existing lead data was kept.'
                                        : `Extraction stopped. Kept ${data.records || 0} fetched record(s).`),
                                records: data.records || 0,
                                redirect_url: data.redirect_url || '',
                            });
                            showCompletedOverlayActions();
                        }
                    } else if (data.ok && action === 'pause') {
                        updateProgressOverlay({
                            ...(latestProgressPayload || {}),
                            status: 'paused',
                            message: 'Extraction paused. Waiting for resume...',
                        });
                    } else if (data.ok && ['stop', 'cancel'].includes(action)) {
                        if (loadingText) {
                            loadingText.textContent = action === 'cancel'
                                ? 'Cancel requested...'
                                : 'Stop requested...';
                        }
                    }

                    if (loadingText && !data.complete) {
                        if (action === 'resume') {
                            loadingText.textContent = 'Resume requested...';
                        } else if (!['stop', 'cancel'].includes(action)) {
                            loadingText.textContent = `${action.charAt(0).toUpperCase() + action.slice(1)} requested...`;
                        }
                    }

                    if (pauseResumeButton && ['pause', 'resume'].includes(action)) {
                        const isPaused = action === 'pause';
                        pauseResumeButton.dataset.state = isPaused ? 'paused' : 'running';
                        pauseResumeButton.textContent = isPaused ? 'Resume' : 'Pause';
                        pauseResumeButton.classList.toggle('btn-success', isPaused);
                        pauseResumeButton.classList.toggle('btn-warning', !isPaused);
                    }
                } catch (error) {
                    if (loadingText) {
                        loadingText.textContent = error.message || 'Control request failed. Please try again.';
                    }
                }
            }

            function isOverlayJobComplete() {
                const completeStatuses = ['completed', 'stopped', 'cancelled', 'failed'];
                return completeStatuses.includes(latestProgressPayload?.status)
                    || stopButton?.dataset.action === 'reload';
            }

            function minimizeExtractorOverlay() {
                if (isOverlayJobComplete()) {
                    if (loadingOverlay) {
                        loadingOverlay.style.display = 'none';
                    }

                    return;
                }

                activeRunInBackground = true;
                saveBackgroundJob();
                updateBackgroundStatus(latestProgressPayload || {
                    status: 'running',
                });
                pollStoredBackgroundProgress(activeProgressToken);

                if (activeProgressPoller) {
                    clearInterval(activeProgressPoller);
                    activeProgressPoller = null;
                }

                if (loadingOverlay) {
                    loadingOverlay.style.display = 'none';
                }
            }

            function resetOverlay(title, message, cancelLabel = 'Cancel & Discard') {
                activeRunInBackground = false;
                activeCompletionUrl = '';
                displayedProgressPercent = 8;
                indeterminateProgressPercent = 8;

                if (backgroundStatus) {
                    backgroundStatus.style.display = 'none';
                }

                if (loadingTitle) {
                    loadingTitle.textContent = title;
                }

                if (loadingText) {
                    loadingText.textContent = message;
                    loadingText.title = message;
                }

                if (loadingBar) {
                    loadingBar.classList.remove('bg-danger');
                    loadingBar.classList.add('progress-bar-animated');
                    loadingBar.style.width = '8%';
                    loadingBar.setAttribute('aria-valuenow', '8');
                }

                if (loadingRecords) {
                    loadingRecords.textContent = '0 records processed';
                }

                if (loadingStep) {
                    loadingStep.textContent = 'Preparing...';
                }

                if (loadingLog) {
                    loadingLog.textContent = 'Starting...';
                }

                if (pauseResumeButton) {
                    pauseResumeButton.style.display = 'inline-block';
                    pauseResumeButton.dataset.state = 'running';
                    pauseResumeButton.textContent = 'Pause';
                    pauseResumeButton.classList.add('btn-warning');
                    pauseResumeButton.classList.remove('btn-success');
                }

                if (cancelButton) {
                    cancelButton.style.display = 'inline-block';
                    cancelButton.textContent = cancelLabel;
                }

                if (backgroundLink) {
                    backgroundLink.style.display = 'block';
                }

                if (stopButton) {
                    stopButton.textContent = 'Stop & Keep';
                    stopButton.dataset.action = 'stop';
                    stopButton.style.display = 'inline-block';
                    stopButton.classList.add('btn-secondary');
                    stopButton.classList.remove('btn-primary');
                }
            }

            function updateBackgroundStatus(payload = {}) {
                if (!backgroundStatus || !backgroundStatusTitle || !backgroundStatusText) {
                    return;
                }

                latestProgressPayload = {
                    ...(latestProgressPayload || {}),
                    ...payload,
                };

                const isEnrichment = activeProgressType === 'enrich';
                const records = latestProgressPayload.records || latestProgressPayload.processed || 0;
                const total = isEnrichment
                    ? (latestProgressPayload.total_queries || latestProgressPayload.total || 0)
                    : (latestProgressPayload.result_limit || latestProgressPayload.limit || latestProgressPayload.total || 0);
                const completeStatuses = ['completed', 'stopped', 'cancelled', 'failed'];
                const isComplete = completeStatuses.includes(latestProgressPayload.status);
                const title = isEnrichment ? 'Enrichment running in background' : 'Fetching leads in background';
                const completeTitle = isEnrichment ? 'Enrichment finished' : 'Lead fetching finished';
                const progressText = total > 0
                    ? `${records} of ${total} processed`
                    : `${records} record(s) processed`;

                backgroundStatusTitle.textContent = isComplete ? completeTitle : title;
                backgroundStatusText.textContent = isComplete
                    ? (latestProgressPayload.redirect_url ? 'Click to open results' : 'Click to refresh progress')
                    : `${progressText}. Click to view progress.`;

                if (backgroundStatusSpinner) {
                    backgroundStatusSpinner.style.display = isComplete ? 'none' : 'inline-block';
                }

                backgroundStatus.style.display = 'inline-flex';
            }

            function showCompletedOverlayActions() {
                if (backgroundLink) {
                    backgroundLink.style.display = 'none';
                }

                if (pauseResumeButton) {
                    pauseResumeButton.style.display = 'none';
                }

                if (stopButton) {
                    stopButton.textContent = 'Reload Results';
                    stopButton.dataset.action = 'reload';
                    stopButton.classList.remove('btn-secondary');
                    stopButton.classList.add('btn-primary');
                    stopButton.style.display = activeCompletionUrl ? 'inline-block' : 'none';
                }

                if (cancelButton) {
                    cancelButton.style.display = 'none';
                }
            }

            function startStoredBackgroundMonitor(token) {
                if (activeProgressPoller) {
                    clearInterval(activeProgressPoller);
                }

                pollStoredBackgroundProgress(token);
                activeProgressPoller = setInterval(() => pollStoredBackgroundProgress(token), 2000);
            }

            async function pollStoredBackgroundProgress(token) {
                try {
                    const response = await fetch(progressUrlForToken(token), {
                        headers: {
                            'Accept': 'application/json',
                        },
                    });

                    if (!response.ok) {
                        return;
                    }

                    const payload = await response.json();
                    latestProgressPayload = payload;
                    const isOverlayVisible = loadingOverlay?.style.display === 'flex';

                    if (isOverlayVisible) {
                        updateProgressOverlay(payload);
                    }

                    if (activeRunInBackground || !isOverlayVisible) {
                        updateBackgroundStatus(payload);
                    }

                    if (['completed', 'stopped', 'cancelled', 'failed'].includes(payload.status)) {
                        clearInterval(activeProgressPoller);
                        activeProgressPoller = null;
                        activeCompletionUrl = completionUrlFromPayload(payload) || window.location.href;
                        saveBackgroundJob({
                            completionUrl: activeCompletionUrl,
                            status: payload.status,
                            latestProgress: payload,
                        });
                        updateBackgroundStatus(payload);
                    } else {
                        activeCompletionUrl = '';
                        saveBackgroundJob({
                            completionUrl: '',
                            status: payload.status || 'running',
                            latestProgress: payload,
                        });
                    }
                } catch (error) {
                    updateBackgroundStatus({
                        status: 'running',
                        records: 0,
                        total_queries: 0,
                    });
                }
            }

            function restoreBackgroundJob() {
                const storedJob = readBackgroundJob();

                if (!storedJob) {
                    return;
                }

                activeProgressToken = storedJob.token;
                activeProgressType = storedJob.type || 'fetch';
                activeCompletionUrl = '';
                activeRunInBackground = true;
                latestProgressPayload = storedJob.latestProgress || null;

                updateBackgroundStatus(latestProgressPayload || {
                    status: 'running',
                    records: 0,
                    result_limit: storedJob.resultLimit || 0,
                    total_queries: 0,
                });

                startStoredBackgroundMonitor(activeProgressToken);
            }

            function computeProgressPercent(payload) {
                const records = payload.records || 0;
                const current = payload.current_query_number || 0;
                const isEnrichment = activeProgressType === 'enrich';
                const completeStatuses = ['completed', 'stopped', 'cancelled', 'failed'];

                if (completeStatuses.includes(payload.status)) {
                    return 100;
                }

                const limitTotal = isEnrichment
                    ? (payload.total_queries || payload.total || 0)
                    : (payload.result_limit || payload.limit || 0);
                const queryTotal = payload.total_queries || 0;

                if (limitTotal > 0) {
                    return Math.min(95, Math.max(8, Math.round((records / limitTotal) * 100)));
                }

                if (queryTotal > 0) {
                    const progressValue = isEnrichment ? records : current;
                    return Math.min(95, Math.max(8, Math.round((progressValue / queryTotal) * 100)));
                }

                return null;
            }

            function applyProgressPercent(payload) {
                const completeStatuses = ['completed', 'stopped', 'cancelled', 'failed'];
                const isComplete = completeStatuses.includes(payload.status);
                let computedPercent = computeProgressPercent(payload);

                if (computedPercent === null) {
                    if (['queued', 'running', 'paused'].includes(payload.status)) {
                        indeterminateProgressPercent = Math.min(92, indeterminateProgressPercent + 1);
                        computedPercent = indeterminateProgressPercent;
                    } else {
                        computedPercent = displayedProgressPercent;
                    }
                }

                if (payload.status === 'paused') {
                    computedPercent = displayedProgressPercent;
                } else if (!isComplete) {
                    computedPercent = Math.max(displayedProgressPercent, computedPercent);
                }

                displayedProgressPercent = isComplete ? 100 : computedPercent;
                loadingBar.style.width = `${displayedProgressPercent}%`;
                loadingBar.setAttribute('aria-valuenow', String(displayedProgressPercent));
            }

            function updateProgressOverlay(payload) {
                latestProgressPayload = payload;
                const records = payload.records || 0;
                const current = payload.current_query_number || 0;
                const isEnrichment = activeProgressType === 'enrich';
                const total = isEnrichment
                    ? (payload.total_queries || payload.total || 0)
                    : (payload.result_limit || payload.limit || 0);
                const queryTotal = payload.total_queries || 0;
                const completeStatuses = ['completed', 'stopped', 'cancelled', 'failed'];
                const isComplete = completeStatuses.includes(payload.status);

                applyProgressPercent(payload);

                if (payload.current_query) {
                    const enrichingLabel = `Enriching ${compactUrl(payload.current_query)}`;
                    loadingText.textContent = enrichingLabel;
                    loadingText.title = payload.message || payload.current_query;
                } else if (payload.message) {
                    loadingText.textContent = payload.message;
                    loadingText.title = payload.message;
                }

                if (loadingRecords) {
                    loadingRecords.textContent = isEnrichment
                        ? `${records} website rows processed`
                        : `${records} records fetched`;
                }

                if (loadingStep) {
                    loadingStep.textContent = isComplete
                        ? (payload.status || 'completed')
                        : isEnrichment && total > 0
                        ? `${Math.min(records, total)} of ${total}`
                        : total > 0
                        ? `${Math.min(records, total)} of ${total}`
                        : queryTotal > 0
                        ? `Search ${Math.min(current || 1, queryTotal)} of ${queryTotal}`
                        : (payload.status || 'running');
                }

                if (isComplete) {
                    showCompletedOverlayActions();
                }

                if (loadingLog && Array.isArray(payload.logs)) {
                    loadingLog.textContent = payload.logs.join("\n") || 'Waiting for scraper log...';
                    loadingLog.scrollTop = loadingLog.scrollHeight;
                }
            }

            function pollExtractionProgress(token) {
                return setInterval(async function () {
                    if (activeForegroundMonitor) {
                        return;
                    }

                    try {
                        const response = await fetch(progressUrlForToken(token), {
                            headers: {
                                'Accept': 'application/json',
                            },
                        });

                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();
                        updateProgressOverlay(payload);
                    } catch (error) {
                        // Keep the last stable overlay state if polling fails briefly.
                    }
                }, 2000);
            }

            async function startExtractionRequest() {
                const formData = new FormData(searchForm);
                const response = await fetch(startUrl(), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: formData,
                });

                if (!response.ok) {
                    throw new Error('Unable to start extraction.');
                }

                return response.json();
            }

            async function processExtractionStep(token) {
                const response = await fetch(processUrl(), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        progress_token: token,
                    }),
                });

                if (!response.ok) {
                    throw new Error('Unable to process extraction step.');
                }

                return response.json();
            }

            async function runExtractionLoop(token) {
                activeForegroundMonitor = true;

                try {
                    while (true) {
                        const response = await fetch(progressUrlForToken(token), {
                            headers: {
                                'Accept': 'application/json',
                            },
                        });

                        if (!response.ok) {
                            throw new Error('Unable to read extraction progress.');
                        }

                        const payload = await response.json();
                        updateProgressOverlay(payload);

                        if (activeRunInBackground) {
                            updateBackgroundStatus(payload);
                        }

                        if (payload.status === 'failed') {
                            throw new Error(payload.message || 'Extraction failed.');
                        }

                        if (['completed', 'stopped', 'cancelled'].includes(payload.status)) {
                            activeCompletionUrl = completionUrlFromPayload(payload);
                            showCompletedOverlayActions();

                            if (!activeRunInBackground && activeCompletionUrl) {
                                clearBackgroundJob();
                                window.location.href = activeCompletionUrl;
                            } else if (activeRunInBackground) {
                                saveBackgroundJob({
                                    completionUrl: activeCompletionUrl,
                                    status: payload.status,
                                    latestProgress: payload,
                                });
                                updateBackgroundStatus(payload);
                            }

                            break;
                        }

                        await new Promise(resolve => setTimeout(resolve, payload.status === 'paused' ? 2000 : 2000));
                    }
                } finally {
                    activeForegroundMonitor = false;
                }
            }

            if (searchForm && loadingOverlay && loadingBar && loadingText) {
                searchForm.addEventListener('submit', async function (event) {
                    event.preventDefault();
                    updateLocationFields();

                    if (!isExtractionFormValid()) {
                        showExtractorFormError('Enter a search query, select a state, and choose at least one city.');
                        return;
                    }

                    showExtractorFormError();

                    const limitValue = document.getElementById('limit')?.value || '10';
                    const isAllData = limitValue === 'all';
                    const limit = isAllData ? 0 : parseInt(limitValue, 10);
                    const limitLabel = isAllData ? 'all available data' : `${limit} leads`;
                    const state = stateSelect?.value || 'selected state';
                    const plannedCities = getPlannedCityFetches();
                    const progressToken = makeProgressToken();
                    activeProgressToken = progressToken;
                    activeProgressType = 'fetch';
                    activeCompletionUrl = '';
                    saveBackgroundJob({
                        status: 'running',
                        completionUrl: '',
                        resultLimit: limit,
                    });

                    if (progressTokenInput) {
                        progressTokenInput.value = progressToken;
                    }

                    loadingOverlay.style.display = 'flex';
                    resetOverlay('Fetching Google Maps Leads', plannedCities.length > 0
                        ? `Fetching ${state}: ${plannedCities[0]} (1 of ${plannedCities.length}). Continuing until ${limitLabel} is fetched or all selected cities are checked...`
                        : `Fetching ${limitLabel}. Requests are throttled to reduce blocking risk...`);

                    try {
                        const startPayload = await startExtractionRequest();
                        activeCompletionUrl = '';
                        saveBackgroundJob({
                            status: 'running',
                            completionUrl: '',
                            searchUrl: startPayload.search_id ? showUrlForSearch(startPayload.search_id) : '',
                            resultLimit: limit,
                        });
                        await runExtractionLoop(progressToken);
                    } catch (error) {
                        if (loadingText) {
                            loadingText.textContent = error.message || 'Extraction failed.';
                        }
                    }
                });
            }

            pauseResumeButton?.addEventListener('click', () => {
                const isPaused = pauseResumeButton.dataset.state === 'paused';
                void sendExtractionControl(isPaused ? 'resume' : 'pause');
            });
            backgroundLink?.addEventListener('click', () => {
                minimizeExtractorOverlay();
            });
            overlayCloseButton?.addEventListener('click', () => {
                minimizeExtractorOverlay();
            });
            backgroundStatus?.addEventListener('click', () => {
                if (activeCompletionUrl) {
                    clearBackgroundJob();
                    window.location.href = activeCompletionUrl;
                    return;
                }

                activeRunInBackground = false;

                if (backgroundStatus) {
                    backgroundStatus.style.display = 'none';
                }

                if (loadingOverlay) {
                    loadingOverlay.style.display = 'flex';
                }

                if (activeProgressToken && !activeProgressPoller && !activeForegroundMonitor) {
                    activeProgressPoller = pollExtractionProgress(activeProgressToken);
                }
            });
            stopButton?.addEventListener('click', () => {
                if (stopButton.dataset.action === 'reload') {
                    if (activeCompletionUrl) {
                        clearBackgroundJob();
                        window.location.href = activeCompletionUrl;
                    } else {
                        window.location.reload();
                    }

                    return;
                }

                void sendExtractionControl('stop');
            });
            cancelButton?.addEventListener('click', () => {
                const isEnrichment = activeProgressType === 'enrich';
                const message = isEnrichment
                    ? 'Cancel website enrichment? Existing extracted leads will be kept.'
                    : 'Cancel this extraction and discard fetched records from this run?';

                if (window.confirm(message)) {
                    void sendExtractionControl('cancel');
                }
            });

            const filter = document.getElementById('lead-filter');
            const rows = Array.from(document.querySelectorAll('.extractor-result-row'));
            const filterCount = document.getElementById('lead-filter-count');
            const paginationSummary = document.getElementById('lead-pagination-summary');
            const paginationWrap = document.getElementById('lead-client-pagination');
            const exportLink = document.getElementById('export-results-link');
            const rowsPerPage = 100;
            let currentLeadPage = 1;

            function rowMatchesLeadFilter(row, value) {
                const hasEmail = row.dataset.hasEmail === '1';
                const hasPhone = row.dataset.hasPhone === '1';
                const hasSocial = row.dataset.hasSocial === '1';

                if (value === 'email') {
                    return hasEmail;
                }

                if (value === 'phone') {
                    return hasPhone;
                }

                if (value === 'email_or_phone') {
                    return hasEmail || hasPhone;
                }

                if (value === 'social') {
                    return hasSocial;
                }

                return true;
            }

            function renderLeadPagination(totalPages) {
                if (!paginationWrap) {
                    return;
                }

                paginationWrap.innerHTML = '';

                if (totalPages <= 1) {
                    return;
                }

                const nav = document.createElement('nav');
                const list = document.createElement('ul');
                const pages = new Set([1, totalPages]);

                for (let page = Math.max(1, currentLeadPage - 2); page <= Math.min(totalPages, currentLeadPage + 2); page++) {
                    pages.add(page);
                }

                nav.setAttribute('aria-label', 'Lead table pagination');
                list.className = 'pagination pagination-sm';

                function addPage(label, page, disabled = false, active = false) {
                    const item = document.createElement('li');
                    const button = document.createElement('button');
                    item.className = `page-item${disabled ? ' disabled' : ''}${active ? ' active' : ''}`;
                    button.type = 'button';
                    button.className = 'page-link';
                    button.textContent = label;
                    button.disabled = disabled;
                    button.addEventListener('click', function () {
                        currentLeadPage = page;
                        applyLeadFilter();
                    });
                    item.appendChild(button);
                    list.appendChild(item);
                }

                addPage('Previous', Math.max(1, currentLeadPage - 1), currentLeadPage === 1);

                Array.from(pages).sort((a, b) => a - b).forEach(function (page, index, sortedPages) {
                    if (index > 0 && page - sortedPages[index - 1] > 1) {
                        const item = document.createElement('li');
                        item.className = 'page-item disabled';
                        item.innerHTML = '<span class="page-link">...</span>';
                        list.appendChild(item);
                    }

                    addPage(String(page), page, false, page === currentLeadPage);
                });

                addPage('Next', Math.min(totalPages, currentLeadPage + 1), currentLeadPage === totalPages);
                nav.appendChild(list);
                paginationWrap.appendChild(nav);
            }

            function applyLeadFilter(resetPage = false) {
                if (!filter) {
                    return;
                }

                const value = filter.value;
                const matchingRows = rows.filter(row => rowMatchesLeadFilter(row, value));
                const totalPages = Math.max(1, Math.ceil(matchingRows.length / rowsPerPage));

                if (resetPage) {
                    currentLeadPage = 1;
                }

                currentLeadPage = Math.min(currentLeadPage, totalPages);
                const start = (currentLeadPage - 1) * rowsPerPage;
                const end = start + rowsPerPage;
                const visibleRows = new Set(matchingRows.slice(start, end));

                rows.forEach(function (row) {
                    row.style.display = visibleRows.has(row) ? '' : 'none';
                });

                if (filterCount) {
                    filterCount.textContent = `${matchingRows.length} of ${rows.length} matching`;
                }

                if (paginationSummary) {
                    const first = matchingRows.length === 0 ? 0 : start + 1;
                    const last = Math.min(end, matchingRows.length);
                    paginationSummary.textContent = matchingRows.length > 0
                        ? `Showing ${first}-${last} of ${matchingRows.length} leads`
                        : 'No leads match this filter';
                }

                if (exportLink) {
                    const baseUrl = exportLink.dataset.baseExportUrl;
                    const separator = baseUrl.includes('?') ? '&' : '?';
                    exportLink.href = value === 'all' ? baseUrl : `${baseUrl}${separator}filter=${encodeURIComponent(value)}`;
                }

                renderLeadPagination(totalPages);
            }

            if (filter) {
                filter.addEventListener('change', () => applyLeadFilter(true));
                applyLeadFilter();
            }

            const enrichButton = document.getElementById('enrich-results-btn');

            async function runEnrichmentBatch(progressToken = '', mode = 'pending') {
                const response = await fetch(enrichButton.dataset.enrichUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        progress_token: progressToken,
                        mode: mode,
                    }),
                });

                if (!response.ok) {
                    throw new Error('Enrichment request failed.');
                }

                return response.json();
            }

            function chooseEnrichmentMode() {
                const alreadyEnriched = rows.filter(row => (
                    row.dataset.hasEmail === '1'
                    || row.dataset.hasPhone === '1'
                    || row.dataset.hasSocial === '1'
                )).length;

                if (alreadyEnriched === 0) {
                    return Promise.resolve('pending');
                }

                return new Promise(function (resolve) {
                    const modalElement = document.getElementById('enrichment-mode-modal');
                    const countElement = document.getElementById('enrichment-existing-count');

                    if (!modalElement || !window.bootstrap?.Modal) {
                        resolve(window.confirm(`${alreadyEnriched} lead(s) already have enriched data. Continue only remaining rows?`)
                            ? 'pending'
                            : 'all');
                        return;
                    }

                    if (countElement) {
                        countElement.textContent = `${alreadyEnriched} lead(s)`;
                    }

                    const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);

                    function cleanup() {
                        modalElement.querySelectorAll('[data-enrichment-mode]').forEach(button => {
                            button.removeEventListener('click', handleClick);
                        });
                        modalElement.removeEventListener('hidden.bs.modal', handleHidden);
                    }

                    function handleClick(event) {
                        const mode = event.currentTarget.dataset.enrichmentMode;
                        cleanup();
                        modal.hide();
                        resolve(mode === 'cancel' ? null : mode);
                    }

                    function handleHidden() {
                        cleanup();
                        resolve(null);
                    }

                    modalElement.querySelectorAll('[data-enrichment-mode]').forEach(button => {
                        button.addEventListener('click', handleClick);
                    });
                    modalElement.addEventListener('hidden.bs.modal', handleHidden, { once: true });
                    modal.show();
                });
            }

            if (enrichButton) {
                enrichButton.addEventListener('click', async function () {
                    const enrichmentMode = await chooseEnrichmentMode();

                    if (!enrichmentMode) {
                        return;
                    }

                    const progressToken = makeProgressToken();
                    activeProgressToken = progressToken;
                    activeProgressType = 'enrich';
                    activeCompletionUrl = '';
                    saveBackgroundJob();

                    enrichButton.disabled = true;
                    loadingOverlay.style.display = 'flex';
                    resetOverlay(
                        'Enriching Website Data',
                        enrichmentMode === 'all'
                            ? 'Starting a new enrichment pass over all website rows...'
                            : 'Continuing enrichment for remaining missing rows...',
                        'Cancel'
                    );

                    try {
                        const queuedPayload = await runEnrichmentBatch(progressToken, enrichmentMode);

                        if (queuedPayload.complete) {
                            clearBackgroundJob();
                            displayedProgressPercent = 100;
                            loadingText.textContent = queuedPayload.message || 'No website rows found for enrichment.';
                            loadingText.title = loadingText.textContent;
                            loadingBar.style.width = '100%';
                            loadingBar.setAttribute('aria-valuenow', '100');
                            loadingBar.classList.remove('progress-bar-animated');
                            enrichButton.disabled = false;
                            return;
                        }

                        activeForegroundMonitor = true;

                        try {
                            while (true) {
                                const response = await fetch(progressUrlForToken(progressToken), {
                                    headers: {
                                        'Accept': 'application/json',
                                    },
                                });

                                if (!response.ok) {
                                    throw new Error('Unable to read enrichment progress.');
                                }

                                const payload = await response.json();
                                updateProgressOverlay(payload);

                                if (activeRunInBackground) {
                                    updateBackgroundStatus(payload);
                                }

                                if (payload.status === 'failed') {
                                    throw new Error(payload.message || 'Enrichment failed.');
                                }

                                if (['completed', 'stopped', 'cancelled'].includes(payload.status)) {
                                    activeCompletionUrl = completionUrlFromPayload(payload) || window.location.href;
                                    showCompletedOverlayActions();
                                    loadingBar.classList.remove('progress-bar-animated');
                                    if (activeRunInBackground) {
                                        saveBackgroundJob({
                                            completionUrl: activeCompletionUrl,
                                            status: payload.status,
                                            latestProgress: payload,
                                        });
                                        updateBackgroundStatus(payload);
                                    }
                                    if (!activeRunInBackground) {
                                        clearBackgroundJob();
                                        setTimeout(function () {
                                            window.location.reload();
                                        }, 900);
                                    }
                                    break;
                                }

                                await new Promise(function (resolve) {
                                    setTimeout(resolve, payload.status === 'paused' ? 2000 : 2000);
                                });
                            }
                        } finally {
                            activeForegroundMonitor = false;
                        }
                    } catch (error) {
                        activeForegroundMonitor = false;
                        enrichButton.disabled = false;
                        loadingText.textContent = error.message || 'Enrichment failed. Please try again.';
                        loadingBar.classList.remove('progress-bar-animated');
                        loadingBar.classList.add('bg-danger');
                    }
                });
            }

            restoreBackgroundJob();
        });
    </script>
@endsection
