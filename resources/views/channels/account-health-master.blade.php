@extends('layouts.vertical', ['title' => 'Account Health Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
  .tabulator .tabulator-header {
    background: linear-gradient(90deg, #e0e7ff 0%, #f4f7fa 100%);
    border-bottom: 2px solid #2563eb;
    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.10);
  }

  .tabulator .tabulator-header .tabulator-col {
    text-align: center;
    background: transparent;
    border-right: 1px solid #e5e7eb;
    padding: 16px 10px;
    font-weight: 700;
    color: #1e293b;
    font-size: 1.08rem;
    letter-spacing: 0.02em;
    transition: background 0.2s;
  }

  .tabulator .tabulator-header .tabulator-col:hover {
    background: #e0eaff;
    color: #2563eb;
  }

  .tabulator-row {
    background-color: #fff !important;
    transition: background 0.18s;
  }

  .tabulator-row:nth-child(even) {
    background-color: #f8fafc !important;
  }

  .tabulator .tabulator-cell {
    text-align: center;
    padding: 14px 10px;
    border-right: 1px solid #e5e7eb;
    border-bottom: 1px solid #e5e7eb;
    font-size: 1rem;
    color: #22223b;
    vertical-align: middle;
    transition: background 0.18s, color 0.18s;
  }

  .tabulator .tabulator-cell:focus {
    outline: 2px solid #2563eb;
    background: #e0eaff;
  }

  .tabulator-row:hover {
    background-color: #dbeafe !important;
  }

  .parent-row {
    background-color: #e0eaff !important;
    font-weight: 700;
  }

  #account-health-master .tabulator {
    border-radius: 18px;
    box-shadow: 0 6px 24px rgba(37, 99, 235, 0.13);
    overflow: hidden;
    border: 1px solid #e5e7eb;
  }

  .tabulator .tabulator-row .tabulator-cell:last-child,
  .tabulator .tabulator-header .tabulator-col:last-child {
    border-right: none;
  }

  .tabulator .tabulator-footer {
    background: #f4f7fa;
    border-top: 1px solid #e5e7eb;
    font-size: 1rem;
    color: #4b5563;
    padding: 5px;
    height: 100px;
  }

  .tabulator .tabulator-footer:hover {
    background: #e0eaff;
  }

  @media (max-width: 768px) {

    .tabulator .tabulator-header .tabulator-col,
    .tabulator .tabulator-cell {
      padding: 8px 2px;
      font-size: 0.95rem;
    }
  }

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
@include('layouts.shared.page-title', ['page_title' => 'Account Health Master', 'sub_title' => 'Account Health Master'])
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert"
  style="background-color: #d1e7dd; color: #0f5132;">
  <style>
    .alert-success .btn-close {
      filter: invert(41%) sepia(94%) saturate(362%) hue-rotate(89deg) brightness(90%) contrast(92%);
    }
  </style>
  <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif
<div class="row">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-body">

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
          <h4 class="mb-0 fw-bold text-primary">Account Health Master</h4>
          <div class="btn-group time-navigation-group me-2" role="group" aria-label="Channel navigation">
            <button id="play-backward" class="btn btn-light rounded-circle shadow-sm me-2"
              style="width: 40px; height: 40px;" title="Previous Channel">
              <i class="fas fa-step-backward"></i>
            </button>

            <button id="play-toggle" class="btn btn-primary rounded-circle shadow-sm me-2"
              style="width: 40px; height: 40px;" title="Play / Pause">
              <i class="fas fa-play" id="play-icon"></i>
            </button>

            <button id="play-forward" class="btn btn-light rounded-circle shadow-sm"
              style="width: 40px; height: 40px;" title="Next Channel">
              <i class="fas fa-step-forward"></i>
            </button>
          </div>

          <div class="d-flex align-items-center gap-2">
            <select id="channelFilter" class="form-select form-control-lg me-2" style="width: 200px; min-width: 160px;">
              <option value="">All Channels</option>
              @foreach($channels as $channel)
                <option value="{{ $channel->channel }}">{{ $channel->channel }}</option>
              @endforeach
            </select>
            <button type="button" class="btn btn-success d-flex align-items-center"
              data-bs-toggle="modal" data-bs-target="#accountHealthModal">
              <i class="fas fa-plus-circle me-1"></i> Add Report
            </button>
          </div>
        </div>
        <div id="account-health-master"></div>
      </div>
    </div>
  </div>
</div>

{{-- account health master form --}}

<div class="modal fade" id="accountHealthModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered shadow-none " role="document">
    <div class="modal-content border-1 border-primary rounded-2">
      <div class="modal-header bg-white border-bottom-0 rounded-top-2">
        <h5 class="modal-title fw-semibold text-primary">
          <i class="fas fa-heartbeat me-2"></i> Account Health Report
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body bg-light">
        <form method="POST" action="{{ route('account.health.store') }}">
          @csrf
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold mb-1">Marketplace Channel <span class="text-danger">*</span></label>
              <div class="custom-select-wrapper" style="position: relative;">
                <div class="custom-select-display form-select">Choose a channel...</div>
                <div class="custom-select-options" style="display: none;">
                  <input type="text" class="custom-select-search" placeholder="Search..." />
                  @foreach($channels as $channel)
                  <div class="custom-select-option" data-value="{{ $channel->channel }}">{{ $channel->channel }}</div>
                  @endforeach
                </div>
                <input type="hidden" name="channel" required>
              </div>
              @error('channel')
              <div class="text-danger mt-1 small">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-4">
              <label class="form-label fw-semibold mb-1">Report Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" name="report_date" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-4">
              <label class="form-label mb-1">Account Health links</label>
              <input type="text" class="form-control" name="account_health_links"
                placeholder="Enter Account Health links">
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label mb-1">Pre-fulfillment Cancel Rate</label>
              <input type="text" class="form-control" name="pre_fulfillment_cancel_rate"
                placeholder="Enter Pre-fulfillment Cancel Rate">
            </div>
            <div class="col-md-4">
              <label class="form-label mb-1">ODR / Transaction Defect Rate</label>
              <input type="text" class="form-control" name="odr_transaction_defect_rate"
                placeholder="Enter ODR / Transaction Defect Rate">
            </div>
            <div class="col-md-4">
              <label class="form-label mb-1">Fulfillment Rate</label>
              <input type="text" class="form-control" name="fulfillment_rate" placeholder="Enter Fulfillment Rate">
            </div>
            <div class="col-md-4">
              <label class="form-label mb-1">Late Shipment Rate</label>
              <input type="text" class="form-control" name="late_shipment_rate" placeholder="Enter Late Shipment Rate">
            </div>
            <div class="col-md-4">
              <label class="form-label mb-1">Valid Tracking Rate</label>
              <input type="text" class="form-control" name="valid_tracking_rate" placeholder="Enter Valid Tracking Rate">
            </div>
            <div class="col-md-4">
              <label class="form-label mb-1">On-Time Delivery Rate</label>
              <input type="text" class="form-control" name="on_time_delivery_rate"
                placeholder="Enter On-Time Delivery Rate">
            </div>
            <div class="col-md-4">
              <label class="form-label mb-1">Negative Feedback</label>
              <input type="text" class="form-control" name="negative_feedback" placeholder="Enter Negative Feedback">
            </div>
            <div class="col-md-4">
              <label class="form-label mb-1">Positive Feedback</label>
              <input type="text" class="form-control" name="positive_feedback" placeholder="Enter Positive Feedback">
            </div>
            <div class="col-md-4">
              <label class="form-label mb-1">Guarantee / Chargeback Claims</label>
              <input type="text" class="form-control" name="guarantee_claims"
                placeholder="Enter Guarantee / Chargeback Claims">
            </div>
            <div class="col-md-4">
              <label class="form-label mb-1">Refund Rate</label>
              <input type="text" class="form-control" name="refund_rate" placeholder="Enter Refund Rate">
            </div>
            <div class="col-md-4">
              <label class="form-label mb-1">Avg. Processing Time (days)</label>
              <input type="text" class="form-control" name="avg_processing_time"
                placeholder="Enter Avg. Processing Time (days)">
            </div>
            <div class="col-md-4">
              <label class="form-label mb-1">Message Response Time</label>
              <input type="text" class="form-control" name="message_time" placeholder="Enter Message Response Time">
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-12">
              <label class="form-label mb-1">Remarks / Action Required</label>
              <textarea class="form-control" name="remarks" rows="3"
                placeholder="Enter remarks or action required..."></textarea>
            </div>
          </div>
          <div class="mt-4 d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save me-1"></i> Save Report
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

{{-- link modal --}}
<div class="modal fade" id="accountHealthLinkModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered shadow-none">
    <div class="modal-content border-1 border-primary rounded-2">
      <form id="accountHealthLinkForm" class="modal-content needs-validation" novalidate>
        @csrf
        <input type="hidden" name="id" id="healthLinkId">
        <div class="modal-header bg-primary text-white">
          <h4 class="modal-title"><i class="fas fa-link me-2"></i> Update Account Health Link</h4>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body bg-light">
          <div class="mb-3">
            <label class="form-label fw-semibold">Account Health URL <span class="text-danger">*</span></label>
            <input type="url" class="form-control form-control-lg" name="account_health_links" id="healthLinkInput"
              required placeholder="https://example.com/account-health">
            <span class="invalid-feedback">Please enter a valid URL.</span>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times me-1"></i> Cancel
          </button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Save Link
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

@endsection

@if ($errors->any())
<script>
  document.addEventListener('DOMContentLoaded', function () {
      var myModal = new bootstrap.Modal(document.getElementById('accountHealthModal'));
      myModal.show();
    });
</script>
@endif

@section('script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>

<script>
  document.addEventListener("DOMContentLoaded", function () {
        const tableData = @json($accountHealthMaster);
        const table = new Tabulator("#account-health-master", {
        layout: "fitDataFill",
        pagination: "local",
        paginationSize: 25,
        height: "700px",
        data: tableData,
        columns: [
            {
              title: "Channels",
              field: "channel",
              headerSort: true,
              formatter: function(cell) {
                const value = cell.getValue();
                if (!value) return "";
                // Add a colored badge for channel
                let color = "#f5f5f5";
                return `<span style="display:inline-flex;align-items:center;gap:7px;">
                  
                  <span style="background:${color};color:#000;font-weight:600;padding:4px 14px;border-radius:12px;font-size:1em;letter-spacing:0.01em;">
                    ${value}
                  </span>
                </span>`;
              }
            },
            {
              title: `L-30 Sales`,
              field: "l30_sales",
              headerSort: true,
              editor: "input", 
              formatter: function(cell) {
                const value = cell.getValue();
                if (!value || isNaN(value)) return `<span style="color:#64748b;font-weight:500;"></span>`;
                // Format with commas (Indian style), no currency symbol
                const formatted = Number(value).toLocaleString("en-US");
                return `<span style="background:#22c55e1A;color:#22c55e;font-weight:600;padding:4px 14px;border-radius:12px;font-size:1em;letter-spacing:0.01em;display:inline-block;min-width:90px;text-align:center;">
                  ${formatted}
                </span>`;
              }
            },
            {
              title: `L-30 Orders`,
              field: "l30_orders",
              headerSort: true,
              editor: "input", 
              formatter: function(cell) {
                const value = cell.getValue();
                if (!value || isNaN(value)) return `<span style="color:#64748b;font-weight:500;"></span>`;
                // Format with commas and badge
                const formatted = Number(value).toLocaleString("en-US");
                return `<span style="background:#22c55e1A;color:#22c55e;font-weight:600;padding:4px 14px;border-radius:12px;font-size:1em;letter-spacing:0.01em;display:inline-block;min-width:70px;text-align:center;">
                  ${formatted}
                </span>`;
              }
            },
            {
              title: `Account Health <i class="fas fa-link"></i>`, 
              field: "account_health_links",
              formatter: function(cell) {
                const value = cell.getValue();
                const rowData = cell.getRow().getData(); // for passing ID or other info
                let buttons = '';

                if (value && value.trim() !== "") {
                  // View button (pill style)
                  buttons += `
                  <a href="${value}" target="_blank" title="Open Account Health Link"
                  style="display:inline-flex;align-items:center;gap:8px;padding:7px 18px;background:linear-gradient(90deg,#2563eb 60%,#2563eb 100%);color:#fff;border-radius:20px;text-decoration:none;font-weight:600;box-shadow:0 2px 8px rgba(37,99,235,0.13);transition:background 0.18s;outline:none;border:none;height:40px;min-width:120px;">
                  <i class="fas fa-heartbeat" style="color:#fff;font-size:1.15em;"></i>
                  <span style="font-size:1em;">View</span>
                  <i class="fas fa-arrow-up-right-from-square" style="color:#fff;font-size:1em;"></i>
                  </a>
                  `;

                  // Edit icon (circle button)
                  buttons += `
                  <button class="btn btn-light ms-1 edit-health-link"
                  data-bs-toggle="modal" 
                  data-bs-target="#accountHealthLinkModal"
                  data-id="${rowData.id}" 
                  data-link="${value}"
                  title="Edit Link"
                  style="background-color:#2563eb;border-radius:50%;padding:0;margin-top: -3px;width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;">
                  <i class="fas fa-pen" style="color:#fff;font-size:1em;"></i>
                  </button>
                  `;
                } else {
                  // Add icon
                    buttons += `
                    <button class="btn add-health-link"
                      data-bs-toggle="modal" 
                      data-bs-target="#accountHealthLinkModal"
                      data-id="${rowData.id}" 
                      data-link="" 
                      title="Add Account Health Link"
                      style="background:linear-gradient(90deg,#22c55e 60%,#16a34a 100%);color:#fff;border:none;border-radius:50%;width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(34,197,94,0.13);transition:background 0.18s;">
                      <i class="fas fa-plus" style="font-size:1.2em;"></i>
                    </button>
                    `;
                }

                return buttons;
              },
              cellClick: function(e, cell) {
                const target = e.target.closest("button");
                if (!target) return;

                const rowData = cell.getRow().getData();
                const link = target.dataset.link || '';
                const id = target.dataset.id;

                // Open your modal logic here
                if (target.classList.contains("edit-health-link")) {
                  openHealthLinkModal(id, link); // Pass id and existing value
                } else if (target.classList.contains("add-health-link")) {
                  openHealthLinkModal(id, ''); // Empty for add
                }
              }
            },
            { title: "Remarks / Action</br>Required", field: "remarks", editor: "input" },
            { title: "Pre-fulfillment </br> Cancel Rate", field: "pre_fulfillment_cancel_rate", editor: "input" },
            { title: "ODR / Transaction </br> defect rate / Cancel Rate", field: "odr", editor: "input" },
            { title: "Fulfillment</br>Rate</br>99.55%", field: "fulfillment_rate", editor: "input" },
            { title: "Late Shipment</br>Rate", field: "late_shipment_rate", editor: "input" },
            { title: "Valid Tracking</br>Rate", field: "valid_tracking_rate", editor: "input" },
            { title: "On-Time Delivery</br>Rate", field: "on_time_delivery_rate", editor: "input" },
            { title: "Negative</br>feedback", field: "negative_feedback", editor: "input" },
            { title: "Positive</br>Feedback", field: "positive_feedback", editor: "input" },
            { title: "Guarantee/</br>Chargeback Claims/</br>Cases w/o Seller Resoulution", field: "guarantee_claims", editor: "input" },
            { title: "Refund</br>Rate", field: "refund_rate", editor: "input" },
            { title: "Avg. Processing </br> Time working days", field: "avg_processing_time", editor: "input" },
            { title: "Message</br>Time", field: "message_time", editor: "input" },
            { title: "Overall", field: "overall", editor: "input" },

        ],
    });
    table.on("cellEdited", function(cell){
        const updatedData = cell.getRow().getData();
        
        fetch('/account-health/update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(updatedData)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                console.log("Updated successfully");
            } else {
                alert(data.message || "Update failed");
            }
        })
        .catch(err => {
            console.error("Error:", err);
            alert("Something went wrong");
        });
    });

    channelSelect();
    openHealthLinkModal();

    document.body.style.zoom = "85%";
  });

    // Channel filter functionality
    document.addEventListener("DOMContentLoaded", function () {
      const channelFilter = document.getElementById("channelFilter");
      const table = Tabulator.findTable("#account-health-master")[0];

      channelFilter.addEventListener("change", function () {
        const selectedChannel = this.value;
        if (selectedChannel) {
          table.setFilter("channel", "=", selectedChannel);
        } else {
          table.clearFilter();
        }
      });
    });

  function channelSelect(){
    const wrapper = document.querySelector(".custom-select-wrapper");
    const display = wrapper.querySelector(".custom-select-display");
    const optionsContainer = wrapper.querySelector(".custom-select-options");
    const searchInput = wrapper.querySelector(".custom-select-search");
    const hiddenInput = wrapper.querySelector("input[name='channel']");
    const options = wrapper.querySelectorAll(".custom-select-option");

    display.addEventListener("click", () => {
      const isOpen = optionsContainer.style.display === "block";
      optionsContainer.style.display = isOpen ? "none" : "block";
      searchInput.value = "";
      filterOptions("");
      if (!isOpen) searchInput.focus();
    });

    options.forEach(option => {
      option.addEventListener("click", () => {
        const value = option.dataset.value;            
        const label = option.dataset.value;          
        display.textContent = label;                
        hiddenInput.value = value;  
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

    document.addEventListener("click", function (e) {
      if (!wrapper.contains(e.target)) {
        optionsContainer.style.display = "none";
      }
    });
  }

  function openHealthLinkModal(){
    const modal = document.getElementById('accountHealthLinkModal');

    modal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      const id = button.getAttribute('data-id');
      const link = button.getAttribute('data-link');

      modal.querySelector('#healthLinkId').value = id;
      modal.querySelector('#healthLinkInput').value = link;
    });
    
    document.getElementById("accountHealthLinkForm").addEventListener("submit", function(e) {
        e.preventDefault();
        const form = this;
        const id = form.querySelector("#healthLinkId").value;
        const link = form.querySelector("#healthLinkInput").value;

        fetch("{{ route('account.health.link.update') }}", {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": "{{ csrf_token() }}",
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ id, account_health_links: link })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const table = Tabulator.findTable("#account-health-master")[0];
                const row = table.getRow(id);
                if (row) {
                    row.update({ account_health_links: link });
                }
                bootstrap.Modal.getInstance(document.getElementById('accountHealthLinkModal')).hide();
            } else {
                alert(data.message || "Something went wrong");
            }
        })
        .catch(err => {
            console.error(err);
        });
    });
  }

  let channelList = [];
  let currentChannelIndex = 0;
  let isPlaying = false;

  // Extract sorted unique channel list
  function extractSortedChannels() {
    const table = Tabulator.findTable("#account-health-master")[0];
    if (!table) return;
    const data = table.getData();
    channelList = [...new Set(data.map(d => d.channel).filter(Boolean))].sort((a, b) => a.localeCompare(b));
  }

  // Filter table by channel
  function filterByChannel(index) {
    if (!channelList.length) return;
    currentChannelIndex = index;
    const channel = channelList[currentChannelIndex];
    const table = Tabulator.findTable("#account-health-master")[0];
    if (table) {
      table.clearFilter();
      table.setFilter("channel", "=", channel);
    }
    const dropdown = document.getElementById("channelFilter");
    if (dropdown) dropdown.value = channel;
  }

  // Show all rows
  function resetFilter() {
    const table = Tabulator.findTable("#account-health-master")[0];
    if (table) table.clearFilter();
    const dropdown = document.getElementById("channelFilter");
    if (dropdown) dropdown.value = "";
  }

  // Play = show first channel
  function handlePlay() {
    extractSortedChannels();
    if (!channelList.length) return;
    isPlaying = true;
    togglePlayPauseIcon();
    currentChannelIndex = 0;
    filterByChannel(currentChannelIndex);
  }

  // Pause = show all
  function handlePause() {
    isPlaying = false;
    togglePlayPauseIcon();
    resetFilter();
  }

  // Toggle ▶️ / ⏸ icon
  function togglePlayPauseIcon() {
    const icon = document.getElementById("play-icon");
    if (isPlaying) {
      icon.classList.remove("fa-play");
      icon.classList.add("fa-pause");
    } else {
      icon.classList.remove("fa-pause");
      icon.classList.add("fa-play");
    }
  }

  // Next channel
  function nextChannel() {
    if (!channelList.length) return;
    currentChannelIndex = (currentChannelIndex + 1) % channelList.length;
    filterByChannel(currentChannelIndex);
  }

  // Previous channel
  function prevChannel() {
    if (!channelList.length) return;
    currentChannelIndex = (currentChannelIndex - 1 + channelList.length) % channelList.length;
    filterByChannel(currentChannelIndex);
  }

  // Initialize
  document.addEventListener("DOMContentLoaded", function () {
    extractSortedChannels();

    const playToggle = document.getElementById("play-toggle");
    const forward = document.getElementById("play-forward");
    const backward = document.getElementById("play-backward");
    const channelFilter = document.getElementById("channelFilter");

    playToggle?.addEventListener("click", function () {
      isPlaying ? handlePause() : handlePlay();
    });

    forward?.addEventListener("click", function () {
      if (isPlaying) nextChannel();
    });

    backward?.addEventListener("click", function () {
      if (isPlaying) prevChannel();
    });

    channelFilter?.addEventListener("change", function () {
      extractSortedChannels();
      const selected = this.value;
      if (!selected) {
        handlePause();
      } else {
        const idx = channelList.indexOf(selected);
        if (idx !== -1) {
          currentChannelIndex = idx;
          isPlaying = true;
          togglePlayPauseIcon();
          filterByChannel(currentChannelIndex);
        }
      }
    });
  });
</script>