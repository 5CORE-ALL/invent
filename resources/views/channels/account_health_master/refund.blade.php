@extends('layouts.vertical', ['title' => 'Refund/Returns'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* Custom styles for the Tabulator table */
        /* Pagination styling */
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            padding: 8px 16px;
            margin: 0 4px;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #e0eaff;
            color: #2563eb;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #2563eb;
            color: white;
        }

        .custom-select-wrapper {
            width: 100%;
            cursor: pointer;
            position: relative;
        }

        .custom-select-display {
            background-color: #fff;
            border: 1px solid #ced4da;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
        }

        .custom-select-options {
            position: absolute;
            z-index: 999;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ced4da;
            border-top: none;
            background-color: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .custom-select-search {
            width: 100%;
            padding: 0.5rem;
            border: none;
            border-bottom: 1px solid #eee;
            outline: none;
        }

        .custom-select-option {
            padding: 0.5rem 0.75rem;
            cursor: pointer;
        }

        .custom-select-option:hover {
            background-color: #f1f1f1;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Refund/Returns',
        'sub_title' => 'Account Health Master',
    ])
    @if (Session::has('flash_message'))
        <div class="alert alert-primary bg-primary text-white alert-dismissible fade show" role="alert"
            style="background-color: #169e28 !important; color: #fff !important;">
            {{ Session::get('flash_message') }}
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-end align-items-center mb-4 gap-1">
                        <div class="d-flex align-items-center gap-2">
                            <select id="channelFilter" class="form-select form-control-lg me-2"
                                style="width: 200px; min-width: 160px;">
                                <option value="">All Channels</option>
                                @foreach ($channels as $channel)
                                    <option value="{{ $channel->channel }}">{{ $channel->channel }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-success d-flex align-items-center d-none" data-bs-toggle="modal"
                                data-bs-target="#accountHealthModal">
                                <i class="fas fa-plus-circle me-1"></i> Add Report
                            </button>
                        </div>
                    </div>
                    <div id="odr-rate"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- odr rate modal form --}}
    <div class="modal fade" id="accountHealthModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered shadow-none" role="document">
            <div class="modal-content border border-primary rounded-2">
                <div class="modal-header bg-white border-bottom rounded-top-2">
                    <h5 class="modal-title fw-semibold text-primary">
                        <i class="fas fa-heartbeat me-2"></i> ODR Rate Report
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body bg-light">
                    <form method="POST" action="{{ route('odr.rate.save') }}">
                        @csrf
                        <div class="row g-4">

                            {{-- Marketplace Channel --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Marketplace Channel <span
                                        class="text-danger">*</span></label>
                                <div class="custom-select-wrapper position-relative">
                                    <div class="custom-select-display form-select">Choose a channel...</div>
                                    <div class="custom-select-options position-absolute bg-white border rounded shadow-sm mt-1 w-100"
                                        style="display: none; z-index: 1000;">
                                        <input type="text" class="custom-select-search form-control mb-2"
                                            placeholder="Search..." />
                                        @foreach ($channels as $channel)
                                            <div class="custom-select-option px-3 py-2 border-bottom cursor-pointer"
                                                data-value="{{ $channel->id }}">
                                                {{ $channel->channel }}
                                            </div>
                                        @endforeach
                                    </div>
                                    <input type="hidden" name="channel_id" required>
                                </div>
                                @error('channel')
                                    <div class="text-danger mt-1 small">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Report Date --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Report Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="report_date" value="{{ date('Y-m-d') }}"
                                    required>
                            </div>

                            {{-- Account Health Links --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Account Health Links</label>
                                <input type="text" class="form-control" name="account_health_links"
                                    placeholder="Enter Account Health link">
                            </div>
                        </div>

                        <div class="mt-4 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-save me-1"></i> Save Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const table = new Tabulator("#odr-rate", {
                ajaxURL: "/fetchRefundRates",
                ajaxConfig: "GET",
                layout: "fitDataFill",
                pagination: true,
                paginationSize: 50,
                paginationMode: "local",
                movableColumns: false,
                resizableColumns: true,
                height: "550px",
                columns: [{
                        title: "Channels",
                        field: "channel",
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (!value) return "";
                            let color = "#f5f5f5";
                            return `<span style="display:inline-flex;align-items:center;gap:7px;">
                        
                        <span style="background:${color};color:#000;font-weight:600;padding:4px 14px;border-radius:12px;font-size:1em;letter-spacing:0.01em;">
                            ${value}
                        </span>
                        </span>`;
                        }
                    },
                    {
                        title: `Account Health <i class="fas fa-link"></i>`,
                        field: "account_health_links",
                        editor: "input",
                        formatter: function(cell) {
                            const value = cell.getValue();

                            if (value && value.trim() !== "") {
                                return `
                                    <a href="${value}" target="_blank" title="Open Account Health Link"
                                    style="display:inline-flex;align-items:center;gap:8px;padding:7px 18px;background:linear-gradient(90deg,#2563eb 60%,#2563eb 100%);color:#fff;border-radius:20px;text-decoration:none;font-weight:600;box-shadow:0 2px 8px rgba(37,99,235,0.13);transition:background 0.18s;outline:none;border:none;height:40px;width:90px;">
                                        <span style="font-size:1em;">View</span>
                                        <i class="fas fa-arrow-up-right-from-square" style="color:#fff;font-size:1em;"></i>
                                    </a>
                                `;
                            }

                            return ""; // no button if link is empty
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "Allowed",
                        field: "allowed",
                        editor: "input",
                        formatter: function (cell) {
                            const data = cell.getData();
                            let allowed = parseFloat(data.allowed);
                            let actual = parseFloat(data.actual);

                            // Handle NaN values
                            allowed = isNaN(allowed) ? 0 : allowed;
                            actual = isNaN(actual) ? 0 : actual;

                            let color = "transparent"; // default bg
                            if (actual < (allowed - 100) / 2) {
                                color = "#28a745"; // light green
                            } else if (actual > allowed) {
                                color = "#ffc107"; // light yellow
                            } else {
                                color = "#dc3545"; // light red
                            }

                            const percentText = allowed + "%";

                            return `<div class="text-center" style="background-color:${color}; color:#ffffff; padding:4px; border-radius:4px;">
                                        <span>${percentText}</span>
                                    </div>`;
                        },
                        cellEdited: function(cell) {
                            cell.getRow().update({}); // re-trigger formatter
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "Current",
                        field: "current",
                        editor: "input",
                        hozAlign: "center"
                    },
                    {
                        title: "Date",
                        field: "report_date",
                        hozAlign: "center"
                    },
                    {
                        title: "Previous",
                        field: "prev_1",
                        hozAlign: "center"
                    },
                    {
                        title: "Date",
                        field: "prev_1_date",
                        hozAlign: "center"
                    },
                    {
                        title: "Previous",
                        field: "prev_2",
                        hozAlign: "center"
                    },
                    {
                        title: "Date",
                        field: "prev_2_date",
                        hozAlign: "center"
                    },
                    {
                        title: "What",
                        field: "what",
                        editor: "input",
                        hozAlign: "center"
                    },
                    {
                        title: "Why",
                        field: "why",
                        editor: "input",
                        hozAlign: "center"
                    },
                    {
                        title: "Action",
                        field: "action",
                        editor: "input",
                        hozAlign: "center"
                    },
                    {
                        title: "C+ Action",
                        field: "c_action",
                        editor: "input",
                        hozAlign: "center"
                    },
                    {
                        title: "Report Date",
                        field: "report_date",
                    }
                ],
            });

            table.on("cellEdited", function(cell) {
                let rowData = cell.getData();

                fetch("/refund-rate/update", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content")
                        },
                        body: JSON.stringify(rowData)
                    })
                    .then(res => res.json())
                    .then(response => {
                        console.log("Updated:", response);
                    })
                    .catch(error => console.error("Update failed:", error));
            });
            channelSelect();
        });

        // Channel filter functionality
        document.addEventListener("DOMContentLoaded", function () {
        const channelFilter = document.getElementById("channelFilter");
        const table = Tabulator.findTable("#odr-rate")[0];

            channelFilter.addEventListener("change", function () {
                const selectedChannel = this.value;
                if (selectedChannel) {
                table.setFilter("channel", "=", selectedChannel);
                } else {
                table.clearFilter();
                }
            });
        });

        function channelSelect() {
            const wrapper = document.querySelector(".custom-select-wrapper");
            const display = wrapper.querySelector(".custom-select-display");
            const optionsContainer = wrapper.querySelector(".custom-select-options");
            const searchInput = wrapper.querySelector(".custom-select-search");
            const hiddenInput = wrapper.querySelector("input[name='channel_id']");
            const options = wrapper.querySelectorAll(".custom-select-option");

            display.addEventListener("click", (e) => {
                e.stopPropagation(); // 👈 add this line
                const isOpen = optionsContainer.style.display === "block";
                optionsContainer.style.display = isOpen ? "none" : "block";
                searchInput.value = "";
                filterOptions("");
                if (!isOpen) searchInput.focus();
            });

            options.forEach(option => {
                option.addEventListener("click", (e) => {
                    e.stopPropagation();
                    const value = option.dataset.value;         // ID
                    const label = option.textContent.trim();    // Channel Name
                    display.textContent = label;                // Show name in UI
                    hiddenInput.value = value;                  // Submit ID to backend
                    optionsContainer.style.display = "none";
                });

            });


            searchInput.addEventListener("input", (e) => {
                filterOptions(e.target.value);
            });

            function filterOptions(query) {
                options.forEach(opt => {
                    const text = opt.textContent.toLowerCase();
                    opt.style.display = text.includes(query.toLowerCase()) ? "block" : "none";
                });
            }

            document.addEventListener("click", function(e) {
                if (!wrapper.contains(e.target)) {
                    optionsContainer.style.display = "none";
                }
            });
        }

    </script>

