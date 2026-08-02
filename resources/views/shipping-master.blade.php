@extends('layouts.vertical', ['title' => 'Shipping Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <style>
        .table-responsive {
            position: relative;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            max-height: 600px;
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            background-color: white;
            width: 100%;
        }

        .table-responsive thead th {
            position: sticky;
            top: 0;
            background: #8fb9fe !important;
            color: white;
            z-index: 10;
            font-weight: 600;
            border-bottom: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            font-size: 9px;
            letter-spacing: 0.2px;
            text-transform: lowercase;
            transition: all 0.2s ease;
            height: auto;
            min-height: 72px;
            min-width: 52px;
            width: auto;
            text-align: center;
            padding: 8px 6px;
        }
        /* Header label - horizontal (no rotation), allows <br> for two lines */
        .table-responsive thead th .th-vertical-label {
            display: block;
            font-size: 10px;
            font-weight: 700;
            color: #000;
            margin-bottom: 2px;
            text-align: center;
            line-height: 1.25;
            text-transform: lowercase;
        }
        /* Horizontal header label - no rotation (Parent, SKU) */
        .table-responsive thead th .th-horizontal-label {
            display: block;
            white-space: nowrap;
            font-size: 10px;
            font-weight: 700;
            color: #000;
            text-align: center;
            margin-bottom: 2px;
            text-transform: lowercase;
        }
        .table-responsive thead th.th-parent-sku-col {
            min-width: 64px;
            width: auto;
            min-height: 72px;
        }
        .table-responsive tbody td.td-parent-col,
        .table-responsive tbody td.td-sku-col {
            white-space: nowrap;
            min-width: 0;
            width: auto;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .table-responsive thead th.th-has-filter {
            min-width: 52px;
            width: auto;
        }
        .table-responsive thead th.th-checkbox-col {
            height: auto;
            min-width: 24px;
            width: 24px;
            max-width: 24px;
        }

        .table-responsive thead th:hover {
            background: #7aa8fd !important;
        }

        .table-responsive thead input {
            background-color: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 4px;
            color: #333;
            padding: 2px 3px;
            margin-top: 4px;
            font-size: 9px;
            width: 3em;
            min-width: 3em;
            max-width: 3em;
            transition: all 0.2s;
        }
        .table-responsive thead input.header-search-120 {
            width: 20ch;
            min-width: 20ch;
            max-width: 20ch;
        }

        .table-responsive thead select {
            background-color: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 4px;
            color: #333;
            padding: 2px 3px;
            margin-top: 4px;
            font-size: 9px;
            width: 3em;
            min-width: 3em;
            max-width: 3em;
            transition: all 0.2s;
        }

        .table-responsive thead input:focus {
            background-color: white;
            box-shadow: 0 0 0 2px rgba(26, 86, 183, 0.3);
            outline: none;
        }

        .table-responsive thead input::placeholder {
            color: #8e9ab4;
            font-style: italic;
        }

        .table-responsive thead select.missing-data-filter {
            background-color: rgba(255, 255, 255, 0.9);
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #333;
            transition: all 0.2s;
        }
        .table-responsive thead select.missing-data-filter:focus {
            background-color: white;
            border-color: #1a56b7;
            box-shadow: 0 0 0 2px rgba(26, 86, 183, 0.3);
            outline: none;
        }
        .table-responsive thead select.missing-data-filter option[value="missing"]:checked {
            background-color: #fecaca;
            color: #dc2626;
            font-weight: bold;
        }
        .table-responsive thead select.missing-data-filter[value="missing"] {
            background-color: #fecaca;
            color: #dc2626;
            font-weight: bold;
            border-color: #ef4444;
        }

        .status-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .table-responsive tbody td {
            padding: 6px 8px;
            border-bottom: 1px solid #edf2f9;
            font-size: 11px;
            color: #495057;
            transition: all 0.2s ease;
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
        }

        .table-responsive tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .table-responsive tbody tr.shipping-parent-row {
            background-color: #dbeafe !important;
        }
        .table-responsive tbody tr.shipping-parent-row:nth-child(even) {
            background-color: #bfdbfe !important;
        }

        .table-responsive tbody tr:hover {
            background-color: #e8f0fe;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .table-responsive tbody tr:hover td {
            color: #000;
        }

        .table-responsive tbody tr.shipping-parent-row:hover {
            background-color: #93c5fd !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(37, 99, 235, 0.15);
        }
        .table-responsive tbody tr.shipping-parent-row:hover td {
            color: #0f172a;
        }

        .table-responsive .text-center {
            text-align: center;
        }

        .shipping-rate-header,
        .shipping-rate-header .th-vertical-label,
        .shipping-rate-header .th-horizontal-label {
            font-weight: 700 !important;
            font-size: 12px !important;
        }
        .shipping-rate-cell {
            font-weight: 700;
            font-size: 13px;
        }
        .shipping-rate-cell.shipping-rate-alert {
            color: #dc3545 !important;
        }
        .table-responsive tbody tr:hover td.shipping-rate-cell.shipping-rate-alert {
            color: #b02a37 !important;
        }
        .shipping-rate-cell.label-qty-ok {
            background-color: #bbf7d0 !important;
            color: #166534 !important;
        }
        .table-responsive tbody tr:hover td.shipping-rate-cell.label-qty-ok {
            background-color: #86efac !important;
            color: #14532d !important;
        }
        .shipping-rate-cell.label-qty-alert {
            background-color: #fecaca !important;
            color: #991b1b !important;
        }
        .table-responsive tbody tr:hover td.shipping-rate-cell.label-qty-alert {
            background-color: #fca5a5 !important;
            color: #7f1d1d !important;
        }

        /* Multi-package rows (Label QTY >= 2): distinct package backgrounds */
        .table-responsive tbody tr.shipping-package-row-2 td {
            background-color: #fff7ed !important;
        }
        .table-responsive tbody tr.shipping-package-row-3 td {
            background-color: #eff6ff !important;
        }
        .table-responsive tbody tr.shipping-package-row-4 td {
            background-color: #f5f3ff !important;
        }
        .table-responsive tbody tr.shipping-package-row-extra td {
            background-color: #f0fdf4 !important;
        }
        .table-responsive tbody tr.shipping-package-row-2:hover td {
            background-color: #ffedd5 !important;
        }
        .table-responsive tbody tr.shipping-package-row-3:hover td {
            background-color: #dbeafe !important;
        }
        .table-responsive tbody tr.shipping-package-row-4:hover td {
            background-color: #ede9fe !important;
        }
        .table-responsive tbody tr.shipping-package-row-extra:hover td {
            background-color: #dcfce7 !important;
        }
        .shipping-package-badge {
            display: inline-block;
            margin-top: 2px;
            padding: 0 5px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: 700;
            line-height: 1.4;
            color: #9a3412;
            background: #ffedd5;
            white-space: nowrap;
        }
        .shipping-package-component {
            display: block;
            margin-top: 2px;
            font-size: 9px;
            font-weight: 600;
            color: #0369a1;
            line-height: 1.2;
            max-width: 140px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .table-responsive tbody td.shipping-rate-cell.shipping-rate-high {
            color: #dc3545 !important;
        }
        .table-responsive tbody td.shipping-rate-cell.shipping-rate-low {
            color: #198754 !important;
        }
        .table-responsive tbody td.shipping-rate-cell.shipping-rate-low-2 {
            color: #0d6efd !important;
        }
        .table-responsive tbody td.shipping-rate-cell.shipping-rate-low-3 {
            color: #ca8a04 !important;
        }
        .table-responsive tbody tr:hover td.shipping-rate-cell.shipping-rate-high,
        .table-responsive tbody tr.shipping-parent-row td.shipping-rate-cell.shipping-rate-high,
        .table-responsive tbody tr.shipping-parent-row:hover td.shipping-rate-cell.shipping-rate-high {
            color: #dc3545 !important;
        }
        .table-responsive tbody tr:hover td.shipping-rate-cell.shipping-rate-low,
        .table-responsive tbody tr.shipping-parent-row td.shipping-rate-cell.shipping-rate-low,
        .table-responsive tbody tr.shipping-parent-row:hover td.shipping-rate-cell.shipping-rate-low {
            color: #198754 !important;
        }
        .table-responsive tbody tr:hover td.shipping-rate-cell.shipping-rate-low-2,
        .table-responsive tbody tr.shipping-parent-row td.shipping-rate-cell.shipping-rate-low-2,
        .table-responsive tbody tr.shipping-parent-row:hover td.shipping-rate-cell.shipping-rate-low-2 {
            color: #0d6efd !important;
        }
        .table-responsive tbody tr:hover td.shipping-rate-cell.shipping-rate-low-3,
        .table-responsive tbody tr.shipping-parent-row td.shipping-rate-cell.shipping-rate-low-3,
        .table-responsive tbody tr.shipping-parent-row:hover td.shipping-rate-cell.shipping-rate-low-3 {
            color: #ca8a04 !important;
        }

        /* Product Master Ship column only: light yellow bg, black text */
        .table-responsive thead th.shipping-ship-col {
            background-color: #fef9c3 !important;
            color: #000000 !important;
        }
        .table-responsive thead th.shipping-ship-col .th-vertical-label {
            color: #000000 !important;
        }
        .table-responsive tbody td.shipping-ship-col {
            background-color: #fef9c3 !important;
            color: #000000 !important;
        }
        .table-responsive tbody tr:hover td.shipping-ship-col {
            background-color: #fef08a !important;
            color: #000000 !important;
        }
        .table-responsive tbody tr.shipping-parent-row td.shipping-ship-col {
            background-color: #fef9c3 !important;
            color: #000000 !important;
        }
        .table-responsive tbody tr.shipping-parent-row:hover td.shipping-ship-col {
            background-color: #fef08a !important;
            color: #000000 !important;
        }
        .table-responsive tbody td.shipping-ship-col.shipping-rate-alert {
            color: #dc3545 !important;
        }
        .table-responsive tbody tr:hover td.shipping-ship-col.shipping-rate-alert {
            color: #b02a37 !important;
        }

        /* Missing data indicator (same look + behaviour as Product Master,
           sized for the compact shipping-master cells). Used on every
           child-SKU cell whose value is null / empty / NaN. Clicking the
           badge opens the row's edit modal and focuses the missing field. */
        .missing-data-indicator {
            display: inline-block;
            color: #dc3545;
            font-weight: bold;
            font-size: 11px;
            line-height: 1;
            background-color: #ffebee;
            padding: 3px 7px;
            border-radius: 4px;
            border: 1px solid #dc3545;
            cursor: pointer;
            transition: all 0.2s ease;
            min-width: 22px;
            text-align: center;
            user-select: none;
        }
        .missing-data-indicator:hover {
            background-color: #dc3545;
            color: #fff;
            transform: scale(1.08);
            box-shadow: 0 2px 6px rgba(220, 53, 69, 0.3);
        }
        .missing-data-indicator:active {
            transform: scale(0.96);
        }
        .table-responsive tbody tr:hover td .missing-data-indicator {
            background-color: #dc3545;
            color: #fff;
        }
        /* Field highlight when the modal opens via an M click — lets the user
           immediately see which field they jumped to. */
        .form-control.missing-field-highlight,
        .form-select.missing-field-highlight {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.2) !important;
            background-color: #fff5f5 !important;
            transition: all 0.25s ease;
        }
        /* Cells holding only the M badge shouldn't inherit the red "alert"
           text color or the yellow ship-col background — the badge speaks for
           itself. */
        .table-responsive tbody td.shipping-rate-cell.has-missing-indicator,
        .table-responsive tbody td.shipping-ship-col.has-missing-indicator {
            color: inherit !important;
        }

        /* Highlight selected item dimension headers */
        .table-responsive thead th.item-dim-header {
            background-color: #fff9c4 !important; /* light yellow */
        }
        /* Declared dimension headers — distinct from ACT yellow */
        .table-responsive thead th.item-dim-decl-header {
            background-color: #d4edda !important; /* light green */
        }
        /* Always hide CTN L/W/H (CM) columns from view */
        .table-responsive thead th.ctn-cm-col,
        .table-responsive tbody td.ctn-cm-col {
            display: none;
        }
        /* Always hide Item Length/Width/Height (CM) columns from view */
        .table-responsive thead th.item-cm-col,
        .table-responsive tbody td.item-cm-col {
            display: none;
        }
        /* Hide Item Weight ACT (Kg) column */
        .table-responsive thead th.hide-item-wt-act,
        .table-responsive tbody td.hide-item-wt-act {
            display: none;
        }
        /* Hide Item Weight DECL (LB) column */
        .table-responsive thead th.hide-item-wt-decl,
        .table-responsive tbody td.hide-item-wt-decl {
            display: none;
        }
        /* Hide FBA SKU column */
        .table-responsive thead th.hide-fba-sku-col,
        .table-responsive tbody td.hide-fba-sku-col {
            display: none;
        }
        /* Hide Fedex / UPS / USPS / UNI ship columns (overridden by col-user-visible) */
        .table-responsive thead th.hide-carrier-col,
        .table-responsive tbody td.hide-carrier-col {
            display: none;
        }

        /* User column visibility (Columns dropdown → channel_tabulator_column_settings) */
        #dim-wt-master-datatable th.col-user-hidden,
        #dim-wt-master-datatable td.col-user-hidden {
            display: none !important;
        }
        #dim-wt-master-datatable th.col-user-visible,
        #dim-wt-master-datatable td.col-user-visible {
            display: table-cell !important;
        }

        #shipping-column-dropdown-menu {
            min-width: min(920px, 96vw);
            max-width: 96vw;
            padding: 10px 12px;
        }
        #shipping-column-dropdown-menu .col-vis-groups {
            display: grid;
            grid-template-columns: repeat(5, minmax(140px, 1fr));
            gap: 8px;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        #shipping-column-dropdown-menu .col-vis-group {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 6px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
        }
        #shipping-column-dropdown-menu .col-vis-group-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #495057;
            margin: 0 0 6px;
            padding: 2px 4px;
            border-bottom: 1px solid #dee2e6;
            user-select: none;
            cursor: pointer;
        }
        #shipping-column-dropdown-menu .col-vis-group-title input {
            margin: 0;
            flex-shrink: 0;
            cursor: pointer;
        }
        #shipping-column-dropdown-menu .col-vis-group-title.col-vis-group-empty {
            opacity: 0.55;
            cursor: default;
        }
        #shipping-column-dropdown-menu .col-vis-group-list {
            flex: 1;
            min-height: 60px;
            max-height: 320px;
            overflow-y: auto;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        #shipping-column-dropdown-menu .col-vis-item {
            list-style: none;
            margin: 0;
            padding: 0;
            border-radius: 4px;
        }
        #shipping-column-dropdown-menu .col-vis-item > label {
            display: block;
            padding: 3px 5px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin: 0;
            font-size: 0.8rem;
            user-select: none;
        }
        #shipping-column-dropdown-menu .col-vis-item > label:hover {
            background: rgba(0, 0, 0, 0.04);
            border-radius: 3px;
        }
        #shipping-column-dropdown-menu .col-vis-item > label.col-vis-locked {
            opacity: 0.65;
            cursor: default;
        }

        .table {
            margin-bottom: 0;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            table-layout: auto;
        }

        /* Verified column – red/green dot dropdown */
        .verified-data-dropdown {
            width: 28px;
            height: 28px;
            min-width: 28px;
            padding: 0;
            border-radius: 50%;
            border: 2px solid rgba(0,0,0,0.15);
            font-size: 14px;
            line-height: 1;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
            outline: none;
            appearance: none;
            -webkit-appearance: none;
            background-repeat: no-repeat;
            background-position: center;
        }
        .verified-data-dropdown.not-verified {
            background-color: #fff;
            border-color: #dc3545;
            color: #dc3545;
        }
        .verified-data-dropdown.not-verified:hover {
            background-color: rgba(220, 53, 69, 0.1);
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.3);
        }
        .verified-data-dropdown.verified {
            background-color: #fff;
            border-color: #28a745;
            color: #28a745;
        }
        .verified-data-dropdown.verified:hover {
            background-color: rgba(40, 167, 69, 0.1);
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.3);
        }
        .verified-data-dropdown option[value="0"] { color: #dc3545; }
        .verified-data-dropdown option[value="1"] { color: #28a745; }

        /* Label Type dropdown in Type column — color by value */
        .label-type-dropdown {
            font-size: 10px;
            padding: 2px 4px;
            max-width: 78px;
            min-width: 62px;
            border-radius: 4px;
            border: 1px solid #ced4da;
            background: #fff;
            color: #212529;
            font-weight: 700;
            cursor: pointer;
        }
        .label-type-dropdown.label-type-env {
            background-color: #fecaca;
            border-color: #ef4444;
            color: #991b1b;
        }
        .label-type-dropdown.label-type-std {
            background-color: #bbf7d0;
            border-color: #22c55e;
            color: #166534;
        }
        .label-type-dropdown.label-type-osize {
            background-color: #e9d5ff;
            border-color: #a855f7;
            color: #6b21a8;
        }
        .label-type-dropdown.label-type-pallet {
            background-color: #bfdbfe;
            border-color: #3b82f6;
            color: #1e40af;
        }
        .label-type-dropdown:focus {
            box-shadow: 0 0 0 2px rgba(26, 86, 183, 0.25);
            outline: none;
        }

        /* Ensure table fits container - auto layout so columns fit content */
        #dim-wt-master-datatable {
            width: 100% !important;
            table-layout: auto;
        }

        /* Prevent horizontal overflow */
        .card-body {
            overflow-x: hidden;
        }

        /* Action column buttons — half of default btn-sm size */
        #dim-wt-master-datatable .action-btns {
            gap: 0.15rem !important;
            align-items: center;
        }
        #dim-wt-master-datatable .action-btns .btn {
            padding: 0.1rem 0.25rem !important;
            font-size: 0.55rem !important;
            line-height: 1 !important;
            border-radius: 2px !important;
            min-width: 0;
        }
        #dim-wt-master-datatable .action-btns .btn i {
            font-size: 0.7rem;
            line-height: 1;
            vertical-align: middle;
        }

        .edit-btn {
            border-radius: 2px;
            transition: all 0.2s;
            background: #fff;
        }

        .edit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(26, 86, 183, 0.2);
        }

        .delete-btn {
            border-radius: 2px;
            transition: all 0.2s;
            background: #fff;
        }

        .delete-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
        }

        .rainbow-loader {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading-text {
            margin-top: 10px;
            font-weight: bold;
        }

        .custom-toast {
            z-index: 2000;
            max-width: 400px;
            width: auto;
            min-width: 300px;
            font-size: 16px;
        }
        
        .toast-body {
            padding: 12px 15px;
            word-wrap: break-word;
            white-space: normal;
        }

        .row-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #1a56b7;
        }

        #selectAll {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #1a56b7;
        }

        #pushDataBtn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .d-flex.gap-2 > button {
            flex-shrink: 0;
        }

        .time-navigation-group {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 50px;
            overflow: hidden;
            padding: 2px;
            background: #f8f9fa;
            display: inline-flex;
            align-items: center;
        }
        .time-navigation-group button {
            padding: 0;
            border-radius: 50% !important;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 2px;
            transition: all 0.2s ease;
            border: 1px solid #dee2e6;
            background: white;
            cursor: pointer;
        }
        .time-navigation-group button:hover:not(:disabled) {
            background-color: #f1f3f5 !important;
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .time-navigation-group button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .time-navigation-group button i { font-size: 1rem; }
        #play-auto { color: #28a745; }
        #play-auto:hover:not(:disabled) { background-color: #28a745 !important; color: white !important; }
        #play-pause { color: #ffc107; display: none; }
        #play-pause:hover:not(:disabled) { background-color: #ffc107 !important; color: white !important; }
        #play-backward, #play-forward { color: #007bff; }
        #play-backward:hover:not(:disabled), #play-forward:hover:not(:disabled) { background-color: #007bff !important; color: white !important; }

        @media (max-width: 768px) {
            .d-flex.justify-content-end {
                justify-content: flex-start !important;
            }
            
            .d-flex.gap-2 > button {
                flex: 1 1 auto;
                min-width: 0;
            }
        }

        /* Slab Rates modal — compact carrier inputs (slab column scrolls with table) */
        #slabRatesTable .slab-rates-sticky-col {
            background: transparent;
            white-space: nowrap;
        }
        #slabRatesTable tbody tr.slab-row-empty .slab-rates-sticky-col {
            color: #94a3b8;
        }
        #slabRatesTable thead th {
            white-space: nowrap;
        }
        #slabRatesTable th.slab-rates-carrier-col,
        #slabRatesTable td.slab-rates-carrier-cell {
            min-width: 86px;
            width: 86px;
            text-align: center;
        }
        #slabRatesTable td.slab-rates-carrier-cell input.slab-rate-input {
            width: 78px;
            text-align: right;
            font-size: 12px;
            padding: 2px 6px;
            height: 28px;
        }
        #slabRatesTable td.slab-count-cell .badge { font-size: 11px; }
        #slabRatesTable tbody tr.slab-row-empty td.slab-rates-carrier-cell input.slab-rate-input {
            background-color: #f1f5f9;
        }
        /* "Prefilled from table" hint: input shows the current value with a
           subtle background so the user can tell it isn't a fresh edit yet,
           but the digits themselves stay upright and readable. */
        #slabRatesTable input.slab-rate-input.slab-rate-prefilled {
            color: #212529;
            background-color: #f8fafc;
            border-color: #e2e8f0;
        }
        #slabRatesTable input.slab-rate-input.slab-rate-prefilled:focus {
            background-color: #fff;
            border-color: #86b7fe;
        }
        #slabRatesTable input.slab-rate-input.slab-rate-mixed {
            background-color: #fffbeb;
            border-color: #fde68a;
        }

        /* ── Shipping Master change-history modal (compact) ── */
        .shipping-history-table { font-size: 12px; }
        .shipping-history-table th,
        .shipping-history-table td {
            padding: 4px 8px !important;
            vertical-align: middle;
        }
        .shipping-history-table .shm-field-cell {
            font-weight: 600;
            color: #0a3d91;
            white-space: nowrap;
        }
        .shipping-history-table .shm-field-cell .shm-field-icon {
            color: #6c8fc4;
            margin-right: 4px;
        }
        .shipping-history-table tr.shm-field-first td {
            border-top: 1px solid #c7dbff;
        }
        .shipping-history-table tr.shm-field-cont .shm-field-cell {
            color: #b9c4d6;
            font-weight: 500;
            font-size: 11px;
        }
        .shipping-history-table .shm-when {
            white-space: nowrap;
            color: #6c757d;
            font-size: 11px;
        }
        .shipping-history-table .shm-who .badge {
            font-size: 10px;
            font-weight: 600;
            padding: 3px 7px;
        }
        .shipping-history-table .shm-old {
            color: #6c757d;
            text-decoration: line-through;
            text-decoration-color: rgba(220, 53, 69, 0.55);
        }
        .shipping-history-table .shm-arrow {
            color: #adb5bd;
            margin: 0 6px;
        }
        .shipping-history-table .shm-new {
            background: #0d6efd;
            color: #ffffff;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .shipping-history-table .shm-empty {
            font-style: italic;
            opacity: 0.85;
        }
        .shipping-history-table .shm-latest-dot {
            display: inline-block;
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #28a745;
            margin-right: 4px;
            vertical-align: middle;
        }
        .shipping-history-table tbody tr:hover {
            background: #f8fbff;
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared.page-title', [
        'page_title' => 'Shipping Master',
        'sub_title' => 'Shipping Master Analysis',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <div class="btn-group time-navigation-group" role="group" aria-label="Parent navigation">
                                    <button type="button" id="play-backward" class="btn btn-light rounded-circle" title="Previous parent">
                                        <i class="fas fa-step-backward"></i>
                                    </button>
                                    <button type="button" id="play-pause" class="btn btn-light rounded-circle" title="Show all"
                                        style="display: none;">
                                        <i class="fas fa-pause"></i>
                                    </button>
                                    <button type="button" id="play-auto" class="btn btn-light rounded-circle" title="Start parent navigation">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <button type="button" id="play-forward" class="btn btn-light rounded-circle" title="Next parent">
                                        <i class="fas fa-step-forward"></i>
                                    </button>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <label class="form-label mb-0" for="shippingRowTypeFilter">Rows:</label>
                                    <select id="shippingRowTypeFilter" class="form-select form-select-sm" style="width: auto; min-width: 118px;" title="Show parent SKUs, child SKUs, or both">
                                        <option value="all">All</option>
                                        <option value="parent">Parent</option>
                                        <option value="sku" selected>SKU</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-end flex-wrap gap-2">
                                <div class="dropdown">
                                    <button type="button" class="btn btn-outline-secondary" id="shippingColumnVisibilityDropdown"
                                        data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Columns" aria-label="Columns">
                                        <i class="fas fa-columns"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="shippingColumnVisibilityDropdown" id="shipping-column-dropdown-menu">
                                        <!-- Populated by JS -->
                                    </ul>
                                </div>
                                <button type="button" class="btn btn-primary" id="pushDataBtn" disabled>
                                    <i class="fas fa-cloud-upload-alt me-1"></i> Push Data
                                </button>
                                <button type="button" class="btn btn-dark" id="slabRatesBtn" title="Slab — apply rates by weight slab">
                                    <i class="fas fa-layer-group me-1"></i> Slab
                                </button>
                                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#importExcelModal">
                                    <i class="fas fa-file-upload me-1"></i> Import
                                </button>
                                <button type="button" class="btn btn-success" id="downloadExcel" title="Download" aria-label="Download">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="dim-wt-master-datatable" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th data-col-key="select" data-col-label="Select" class="text-center th-checkbox-col">
                                        <input type="checkbox" id="selectAll" title="Select All" style="width: 16px; height: 16px;">
                                    </th>
                                    <th data-col-key="image" data-col-label="Img"><span class="th-vertical-label">Img</span></th>
                                    <th data-col-key="parent" data-col-label="Parent" class="th-has-filter th-parent-sku-col">
                                        <div class="th-horizontal-label" style="font-size: 9px;">Parent</div>
                                        <input type="text" id="parentSearch" class="form-control-sm header-search-120"
                                            placeholder="Search" style="font-size: 9px; padding: 2px 4px;">
                                    </th>
                                    <th data-col-key="sku" data-col-label="SKU" class="th-has-filter th-parent-sku-col">
                                        <div class="th-horizontal-label" style="font-size: 9px;">SKU</div>
                                        <input type="text" id="skuSearch" class="form-control-sm header-search-120"
                                            placeholder="Search" style="font-size: 9px; padding: 2px 4px;">
                                    </th>
                                    <th data-col-key="status" data-col-label="STATUS" class="th-has-filter">
                                        <div class="th-vertical-label" style="font-size: 9px;">STATUS</div>
                                        <select id="filterSTATUS" class="form-control form-control-sm mt-1 missing-data-filter" style="font-size: 9px; padding: 2px 4px;" data-column="STATUS">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="active">🟢 Active</option>
                                            <option value="inactive">🔴 Inactive</option>
                                            <option value="DC">🔴 DC</option>
                                            <option value="upcoming">🟡 Upcoming</option>
                                            <option value="2BDC">🔵 2BDC</option>
                                        </select>
                                    </th>
                                    <th data-col-key="label_qty" data-col-label="Label Qty" class="th-has-filter shipping-rate-header">
                                        <div class="th-vertical-label">Label<br>Qty</div>
                                        <select id="filterLabelQty" class="form-control form-control-sm mt-1 missing-data-filter" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter Label Qty column">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="has">Has value</option>
                                        </select>
                                    </th>
                                    <th data-col-key="label_type" data-col-label="Type" class="th-has-filter shipping-rate-header" title="Label Type">
                                        <div class="th-vertical-label">Type</div>
                                        <select id="filterLabelType" class="form-control form-control-sm mt-1 missing-data-filter" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Label Type">
                                            <option value="all">All</option>
                                            <option value="ENV">ENV</option>
                                            <option value="STD">STD</option>
                                            <option value="O-Size">O-Size</option>
                                            <option value="Pallet">Pallet</option>
                                        </select>
                                    </th>
                                    <th data-col-key="inv" data-col-label="INV" class="shipping-rate-header"><span class="th-vertical-label">INV</span></th>
                                    <th data-col-key="ship" data-col-label="Ship" class="th-has-filter shipping-rate-header shipping-ship-col" data-pm-ship-col="ship">
                                        <div class="th-vertical-label">Ship</div>
                                        <select id="filterShipCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter Ship column">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th data-col-key="ship_bb" data-col-label="Ship BB" class="th-has-filter shipping-rate-header" data-pm-ship-col="ship_bb">
                                        <div class="th-vertical-label">Ship<br>BB</div>
                                        <select id="filterShipBbCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter Ship BB column">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th data-col-key="tt_ship" data-col-label="TT 1 Ship" class="th-has-filter shipping-rate-header" data-pm-ship-col="tt">
                                        <div class="th-vertical-label">TT 1<br>Ship</div>
                                        <select id="filterTtShipCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter TT 1 Ship">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th data-col-key="temu_ship" data-col-label="Temu ship" class="th-has-filter shipping-rate-header" data-pm-ship-col="temu">
                                        <div class="th-vertical-label">Temu<br>ship</div>
                                        <select id="filterTemuShipCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter Temu ship">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th data-col-key="temu_gofo" data-col-label="Temu GOFO" class="th-has-filter shipping-rate-header" data-pm-ship-col="temu_gofo">
                                        <div class="th-vertical-label">Temu<br>GOFO</div>
                                        <select id="filterTemuGofoCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter Temu GOFO">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th data-col-key="gofo" data-col-label="GOFO" class="th-has-filter shipping-rate-header" data-pm-ship-col="gofo">
                                        <div class="th-vertical-label">GOFO</div>
                                        <select id="filterGofoCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter GOFO">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th data-col-key="fedex" data-col-label="Fedex" class="th-has-filter shipping-rate-header hide-carrier-col" data-pm-ship-col="fedex">
                                        <div class="th-vertical-label">Fedex</div>
                                        <select id="filterFedexCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter Fedex">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th data-col-key="ups" data-col-label="UPS" class="th-has-filter shipping-rate-header hide-carrier-col" data-pm-ship-col="ups">
                                        <div class="th-vertical-label">UPS</div>
                                        <select id="filterUpsCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter UPS">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th data-col-key="usps" data-col-label="USPS" class="th-has-filter shipping-rate-header hide-carrier-col" data-pm-ship-col="usps">
                                        <div class="th-vertical-label">USPS</div>
                                        <select id="filterUspsCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter USPS">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th data-col-key="uni" data-col-label="UNI" class="th-has-filter shipping-rate-header hide-carrier-col" data-pm-ship-col="uni">
                                        <div class="th-vertical-label">UNI</div>
                                        <select id="filterUniCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter UNI">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th data-col-key="fba_sku" data-col-label="FBA SKU" class="th-has-filter th-parent-sku-col shipping-rate-header hide-fba-sku-col">
                                        <div class="th-horizontal-label" style="font-size: 9px;">FBA SKU</div>
                                        <input type="text" id="fbaSkuSearch" class="form-control-sm header-search-120"
                                            placeholder="Search" style="font-size: 9px; padding: 2px 4px;">
                                    </th>
                                    <th data-col-key="fba_ship" data-col-label="FBA ship" class="shipping-rate-header"><span class="th-vertical-label">FBA<br>ship</span></th>
                                    <th data-col-key="fba_manual_ship" data-col-label="FBA manual ship" class="shipping-rate-header"><span class="th-vertical-label">FBA manual<br>ship</span></th>
                                    <th data-col-key="wt_act_kg" data-col-label="Item Weight ACT (Kg)" class="th-has-filter item-dim-header hide-item-wt-act">
                                        <div class="th-vertical-label" style="font-size: 9px;">Item Weight ACT<br>(Kg)</div>
                                        <select id="filterWtActKg" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th data-col-key="wt_act" data-col-label="Item WT ACT (OZ / LB)" class="th-has-filter item-dim-header th-wt-act-lb-filter" title="Below 1 lb shown in OZ; 1 lb and above shown in LB">
                                        <div class="th-vertical-label" style="font-size: 9px;">Item WT ACT<br>(OZ / LB)</div>
                                        <select id="filterWtAct" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 180px;" title="Filter by Item WT ACT (oz / lb)">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th data-col-key="l" data-col-label="Item Length (inch)" class="th-has-filter item-dim-header">
                                        <div class="th-vertical-label" style="font-size: 9px;">Item Length<br>(inch)</div>
                                        <select id="filterL" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th data-col-key="w" data-col-label="Item Width (inch)" class="th-has-filter item-dim-header">
                                        <div class="th-vertical-label" style="font-size: 9px;">Item Width<br>(inch)</div>
                                        <select id="filterW" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th data-col-key="h" data-col-label="Item Height (Inch)" class="th-has-filter item-dim-header">
                                        <div class="th-vertical-label" style="font-size: 9px;">Item Height<br>(Inch)</div>
                                        <select id="filterH" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th data-col-key="wt_decl" data-col-label="Itm wt GW Decl" class="th-has-filter item-dim-decl-header" title="Below 1 lb shown in OZ; 1 lb and above shown in LB. Copies ACT when Decl is empty.">
                                        <div class="th-vertical-label" style="font-size: 9px;">Itm wt GW Decl</div>
                                        <select id="filterWtDecl" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 180px;" title="Filter by Itm wt GW Decl">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th data-col-key="l_decl" data-col-label="Item L IN Decl" class="th-has-filter item-dim-decl-header" title="Copies Item Length when Decl is empty">
                                        <div class="th-vertical-label" style="font-size: 9px;">Item L IN Decl</div>
                                        <select id="filterLDecl" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th data-col-key="w_decl" data-col-label="Item W IN Decl" class="th-has-filter item-dim-decl-header" title="Copies Item Width when Decl is empty">
                                        <div class="th-vertical-label" style="font-size: 9px;">Item W IN Decl</div>
                                        <select id="filterWDecl" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th data-col-key="h_decl" data-col-label="Item H IN Decl" class="th-has-filter item-dim-decl-header" title="Copies Item Height when Decl is empty">
                                        <div class="th-vertical-label" style="font-size: 9px;">Item H IN Decl</div>
                                        <select id="filterHDecl" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th data-col-key="l_cm" data-col-label="Item Length (CM)" class="item-cm-col"><span class="th-vertical-label">Item Length<br>(CM)</span></th>
                                    <th data-col-key="w_cm" data-col-label="Item Width (CM)" class="item-cm-col"><span class="th-vertical-label">Item Width<br>(CM)</span></th>
                                    <th data-col-key="h_cm" data-col-label="Item Height (CM)" class="item-cm-col"><span class="th-vertical-label">Item Height<br>(CM)</span></th>
                                    <th data-col-key="ctn_l" data-col-label="CTN L (CM)" class="ctn-cm-col"><span class="th-vertical-label">CTN L<br>(CM)</span></th>
                                    <th data-col-key="ctn_w" data-col-label="CTN W (CM)" class="ctn-cm-col"><span class="th-vertical-label">CTN W<br>(CM)</span></th>
                                    <th data-col-key="ctn_h" data-col-label="CTN H (CM)" class="ctn-cm-col"><span class="th-vertical-label">CTN H<br>(CM)</span></th>
                                    <th data-col-key="ctn_cbm" data-col-label="Carton CBM"><span class="th-vertical-label">Carton<br>CBM</span></th>
                                    <th data-col-key="ctn_qty" data-col-label="CTN QTY"><span class="th-vertical-label">CTN<br>QTY</span></th>
                                    <th data-col-key="ctn_cbm_each" data-col-label="Carton CBM each"><span class="th-vertical-label">Carton CBM<br>each</span></th>
                                    <th data-col-key="verified" data-col-label="Verified" class="text-center"><span class="th-vertical-label">Verified</span></th>
                                    <th data-col-key="action" data-col-label="Action"><span class="th-vertical-label">Action</span></th>
                                </tr>
                            </thead>
                            <tbody id="table-body"></tbody>
                        </table>
                    </div>

                    <div id="rainbow-loader" class="rainbow-loader">
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="loading-text">Loading Shipping Master data...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Shipping Master Modal -->
    <div class="modal fade" id="editDimWtModal" tabindex="-1" aria-labelledby="editDimWtModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDimWtModalLabel">Edit Shipping Master</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editDimWtForm">
                        <input type="hidden" id="editProductId" name="product_id">
                        <input type="hidden" id="editSku" name="sku">
                        <input type="hidden" id="editParent" name="parent">

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="editLabelQty" class="form-label fw-bold">Label Qty</label>
                                <input type="number" step="1" min="0" class="form-control fw-bold" id="editLabelQty" name="label_qty" placeholder="Label Qty">
                            </div>
                            <div class="col-md-4">
                                <label for="editLabelType" class="form-label fw-bold" title="Label Type">Type</label>
                                <select class="form-select fw-bold" id="editLabelType" name="label_type" title="Label Type">
                                    <option value="ENV">ENV</option>
                                    <option value="STD" selected>STD</option>
                                    <option value="O-Size">O-Size</option>
                                    <option value="Pallet">Pallet</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-1">
                            <div class="col-12">
                                <small class="text-secondary fw-semibold">Item Dimension</small>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="editWtActKg" class="form-label">Item Weight ACT (Kg)</label>
                                <input type="number" step="0.01" class="form-control" id="editWtActKg" name="wt_act_kg" placeholder="Enter Item Weight ACT (Kg)">
                            </div>
                            <div class="col-md-4">
                                <label for="editWtAct" class="form-label">Item WT ACT (LB)</label>
                                <input type="number" step="0.01" class="form-control" id="editWtAct" name="wt_act" placeholder="Enter Item WT ACT (LB)">
                            </div>
                            <div class="col-md-4">
                                <label for="editWtDecl" class="form-label">Itm wt GW Decl</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="editWtDecl" name="wt_decl" placeholder="Rounds up to next slab (e.g. 1.1 → 2, 14.5 → 20)">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="editL" class="form-label">Item Length (inch)</label>
                                <input type="number" step="0.01" class="form-control" id="editL" name="l" placeholder="Enter Item Length (inch)">
                            </div>
                            <div class="col-md-4">
                                <label for="editW" class="form-label">Item Width (inch)</label>
                                <input type="number" step="0.01" class="form-control" id="editW" name="w" placeholder="Enter Item Width (inch)">
                            </div>
                            <div class="col-md-4">
                                <label for="editH" class="form-label">Item Height (Inch)</label>
                                <input type="number" step="0.01" class="form-control" id="editH" name="h" placeholder="Enter Item Height (Inch)">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="editLDecl" class="form-label">Item L IN Decl</label>
                                <input type="number" step="0.01" class="form-control" id="editLDecl" name="l_decl" placeholder="Enter Item L IN Decl">
                            </div>
                            <div class="col-md-4">
                                <label for="editWDecl" class="form-label">Item W IN Decl</label>
                                <input type="number" step="0.01" class="form-control" id="editWDecl" name="w_decl" placeholder="Enter Item W IN Decl">
                            </div>
                            <div class="col-md-4">
                                <label for="editHDecl" class="form-label">Item H IN Decl</label>
                                <input type="number" step="0.01" class="form-control" id="editHDecl" name="h_decl" placeholder="Enter Item H IN Decl">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="editLCm" class="form-label">Item Length (CM)</label>
                                <input type="number" step="0.01" class="form-control" id="editLCm" name="l_cm" placeholder="Enter Item Length (CM)">
                            </div>
                            <div class="col-md-4">
                                <label for="editWCm" class="form-label">Item Width (CM)</label>
                                <input type="number" step="0.01" class="form-control" id="editWCm" name="w_cm" placeholder="Enter Item Width (CM)">
                            </div>
                            <div class="col-md-4">
                                <label for="editHCm" class="form-label">Item Height (CM)</label>
                                <input type="number" step="0.01" class="form-control" id="editHCm" name="h_cm" placeholder="Enter Item Height (CM)">
                            </div>
                        </div>
                        
                        <div class="row mb-1">
                            <div class="col-12">
                                <small class="text-secondary fw-semibold">CARTON Dimension section</small>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-2">
                                <label for="editCtnL" class="form-label">CTN L (CM)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnL" name="ctn_l" placeholder="Enter CTN L (CM)">
                            </div>
                            <div class="col-md-2">
                                <label for="editCtnLInch" class="form-label">CTN L (inch)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnLInch" name="ctn_l_inch" placeholder="Auto from CM" readonly>
                            </div>
                            <div class="col-md-2">
                                <label for="editCtnW" class="form-label">CTN W (CM)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnW" name="ctn_w" placeholder="Enter CTN W (CM)">
                            </div>
                            <div class="col-md-2">
                                <label for="editCtnWInch" class="form-label">CTN W (inch)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnWInch" name="ctn_w_inch" placeholder="Auto from CM" readonly>
                            </div>
                            <div class="col-md-2">
                                <label for="editCtnH" class="form-label">CTN H (CM)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnH" name="ctn_h" placeholder="Enter CTN H (CM)">
                            </div>
                            <div class="col-md-2">
                                <label for="editCtnHInch" class="form-label">CTN H (inch)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnHInch" name="ctn_h_inch" placeholder="Auto from CM" readonly>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editCtnQty" class="form-label">CTN (QTY)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnQty" name="ctn_qty" placeholder="Enter CTN (QTY)">
                            </div>
                            <div class="col-md-6">
                                <label for="editCtnWeightKg" class="form-label">CTN Weight (KG)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnWeightKg" name="ctn_weight_kg" placeholder="Enter CTN Weight (KG)">
                            </div>
                        </div>

                        <div class="row mb-1">
                            <div class="col-12">
                                <small class="text-secondary fw-semibold">Marketplace ship (Product Master)</small>
                                <div class="text-muted small">Read-only here — change rates only from <strong>Slab Rates</strong> (by weight LB), so a slab stays uniform.</div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="editShip" class="form-label fw-bold">Ship</label>
                                <input type="number" step="0.01" class="form-control fw-bold bg-light" id="editShip" name="ship" placeholder="Ship" readonly tabindex="-1" title="Edit via Slab Rates only">
                            </div>
                            <div class="col-md-3">
                                <label for="editShipBb" class="form-label fw-bold">Ship BB</label>
                                <input type="number" step="0.01" class="form-control fw-bold bg-light" id="editShipBb" name="ship_bb" placeholder="Ship BB" readonly tabindex="-1" title="Edit via Slab Rates only">
                            </div>
                            <div class="col-md-3">
                                <label for="editTtShip" class="form-label fw-bold">TT 1 Ship</label>
                                <input type="number" step="0.01" class="form-control fw-bold bg-light" id="editTtShip" name="tt_ship" placeholder="TT 1 Ship" readonly tabindex="-1" title="Edit via Slab Rates only">
                            </div>
                            <div class="col-md-3">
                                <label for="editTemuShip" class="form-label fw-bold">Temu ship</label>
                                <input type="number" step="0.01" class="form-control fw-bold bg-light" id="editTemuShip" name="temu_ship" placeholder="Temu ship" readonly tabindex="-1" title="Edit via Slab Rates only">
                            </div>
                            <div class="col-md-3">
                                <label for="editTemuGofo" class="form-label fw-bold">Temu GOFO</label>
                                <input type="number" step="0.01" class="form-control fw-bold bg-light" id="editTemuGofo" name="temu_gofo" placeholder="Temu GOFO" readonly tabindex="-1" title="Edit via Slab Rates only">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="editGofo" class="form-label fw-bold">GOFO</label>
                                <input type="number" step="0.01" class="form-control fw-bold bg-light" id="editGofo" name="gofo" placeholder="GOFO" readonly tabindex="-1" title="Edit via Slab Rates only">
                            </div>
                            <div class="col-md-3">
                                <label for="editFedex" class="form-label fw-bold">Fedex</label>
                                <input type="number" step="0.01" class="form-control fw-bold bg-light" id="editFedex" name="fedex" placeholder="Fedex" readonly tabindex="-1" title="Edit via Slab Rates only">
                            </div>
                            <div class="col-md-3">
                                <label for="editUps" class="form-label fw-bold">UPS</label>
                                <input type="number" step="0.01" class="form-control fw-bold bg-light" id="editUps" name="ups" placeholder="UPS" readonly tabindex="-1" title="Edit via Slab Rates only">
                            </div>
                            <div class="col-md-3">
                                <label for="editUsps" class="form-label fw-bold">USPS</label>
                                <input type="number" step="0.01" class="form-control fw-bold bg-light" id="editUsps" name="usps" placeholder="USPS" readonly tabindex="-1" title="Edit via Slab Rates only">
                            </div>
                            <div class="col-md-3">
                                <label for="editUni" class="form-label fw-bold">UNI</label>
                                <input type="number" step="0.01" class="form-control fw-bold bg-light" id="editUni" name="uni" placeholder="UNI" readonly tabindex="-1" title="Edit via Slab Rates only">
                            </div>
                        </div>

                        <div class="row mb-1">
                            <div class="col-12">
                                <small class="text-secondary fw-semibold">FBA ship (FBA calculation table, not Product Master)</small>
                                <div class="text-muted small">Leave both FBA fields empty when saving to keep existing FBA values. FBA manual ship updates manual fee so fee + send cost equals this total.</div>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-6">
                                <label for="editFbaShip" class="form-label fw-bold">FBA ship</label>
                                <input type="number" step="0.01" class="form-control fw-bold" id="editFbaShip" name="fba_ship_calculation" placeholder="FBA ship calculation">
                            </div>
                            <div class="col-md-6">
                                <label for="editFbaManualShip" class="form-label fw-bold">FBA manual ship</label>
                                <input type="number" step="0.01" class="form-control fw-bold" id="editFbaManualShip" name="fba_manual_ship" placeholder="Manual + send (total)">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveDimWtBtn">
                        <i class="fas fa-save me-2"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Excel Modal -->
    <div class="modal fade" id="importExcelModal" tabindex="-1" aria-labelledby="importExcelModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                    <h5 class="modal-title" id="importExcelModalLabel">
                        <i class="fas fa-upload me-2"></i>Import Shipping Master Data
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Instructions:</strong>
                        <ol class="mb-0 mt-2">
                            <li>Download the sample file below</li>
                            <li>Fill in Shipping Master fields per the sample columns (item dims, CTN L/W/H in CM, carton CBM / QTY / each, ship rates). Optional columns such as CBM, CBM (E), or CTN Weight (KG) can still be imported if present.</li>
                            <li>Upload the completed file</li>
                        </ol>
                    </div>
                    
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-primary w-100" id="downloadSampleBtn">
                            <i class="fas fa-download me-2"></i>Download Sample File
                        </button>
                    </div>

                    <div class="mb-3">
                        <label for="importFile" class="form-label fw-bold">Select Excel File</label>
                        <input type="file" class="form-control" id="importFile" accept=".xlsx,.xls,.csv">
                        <div class="form-text">Supported formats: .xlsx, .xls, .csv</div>
                        <div id="fileError" class="text-danger mt-2" style="display: none;"></div>
                    </div>

                    <div id="importProgress" class="progress mb-3" style="display: none;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                    </div>

                    <div id="importResult" class="alert" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="importBtn" disabled>
                        <i class="fas fa-upload me-2"></i>Import
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Shipping Master History Modal -->
    <div class="modal fade" id="shippingHistoryModal" tabindex="-1" aria-labelledby="shippingHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white;">
                    <h5 class="modal-title" id="shippingHistoryModalLabel">
                        <i class="bi bi-clock-history me-2"></i>Change History — <span id="shippingHistorySku" class="fw-bold"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="shippingHistoryLoading" class="text-center py-4" style="display:none;">
                        <div class="spinner-border text-info" role="status"></div>
                        <p class="mt-2 text-muted small mb-0">Loading history…</p>
                    </div>
                    <div id="shippingHistoryEmpty" class="alert alert-info mb-0" style="display:none;">
                        <i class="fas fa-info-circle me-2"></i> No edits recorded for this SKU yet. Changes made from now on will be tracked here.
                    </div>
                    <div id="shippingHistoryError" class="alert alert-danger mb-0" style="display:none;"></div>
                    <div class="table-responsive" id="shippingHistoryTableWrap" style="display:none; max-height: 65vh;">
                        <table class="table table-sm table-hover mb-0 align-middle shipping-history-table">
                            <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                <tr>
                                    <th style="white-space:nowrap; width: 24%;">Field</th>
                                    <th style="white-space:nowrap; width: 16%;">When</th>
                                    <th style="white-space:nowrap; width: 14%;">Who</th>
                                    <th>Change (old → new)</th>
                                </tr>
                            </thead>
                            <tbody id="shippingHistoryTbody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Push Data Success Modal -->
    <div class="modal fade" id="pushDataSuccessModal" tabindex="-1" aria-labelledby="pushDataSuccessModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                    <h5 class="modal-title" id="pushDataSuccessModalLabel">
                        <i class="fas fa-check-circle me-2"></i>Push Data Success
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="pushDataSuccessMessage" style="font-size: 15px; line-height: 1.6;">
                        <!-- Message will be inserted here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="pushDataOkBtn" data-bs-dismiss="modal">
                        <i class="fas fa-check me-2"></i>OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Missing Data Modal (small, single-field) — mirrors Product Master's
         "Enter Missing Data" dialog so clicking an M badge lets the user edit
         just that one value instead of opening the full edit modal. -->
    <div class="modal fade" id="missingDataModal" tabindex="-1" aria-labelledby="missingDataModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white;">
                    <h5 class="modal-title" id="missingDataModalLabel">
                        <i class="fas fa-edit me-2"></i>Enter Missing Data
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">SKU:</label>
                        <p class="form-control-plaintext mb-0" id="missingDataSku"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Field:</label>
                        <p class="form-control-plaintext mb-0" id="missingDataField"></p>
                    </div>
                    <div class="mb-3">
                        <label for="missingDataValue" class="form-label fw-bold">Enter Value:</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="missingDataValue" placeholder="Enter value here&hellip;" autocomplete="off">
                        <small class="form-text text-muted" id="missingDataHint">Enter a numeric value.</small>
                    </div>
                    <div id="missingDataError" class="alert alert-danger" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="saveMissingDataBtn">
                        <i class="fas fa-save me-1"></i>Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Slab Rates Modal -->
    <div class="modal fade" id="slabRatesModal" tabindex="-1" aria-labelledby="slabRatesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #1f2937 0%, #111827 100%); color: white;">
                    <h5 class="modal-title" id="slabRatesModalLabel">
                        <i class="fas fa-layer-group me-2"></i>Slab Rates &mdash; Shipping Carriers
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 mb-3" style="font-size: 13px;">
                        <i class="fas fa-info-circle me-2"></i>
                        Enter a rate in any <em>(slab &times; carrier)</em> cell. On <strong>Apply</strong>, each rate is
                        written to every non-parent SKU in that slab (saved on each SKU in Product Master — there is no separate slab-rates table).
                        The outer table shows the same slab rate, and on page load any SKU that differs is <strong>auto-saved</strong> to that rate
                        so other pages read the same <code>Values</code> keys (<code>ship</code>, <code>ship_bb</code>, etc.).
                        <div class="text-muted mt-1">
                            <span class="badge bg-light text-dark border me-1" style="background-color: #f8fafc;">5.49</span>
                            <span class="me-2">= filled SKUs in the slab share this value.</span>
                            <span class="badge bg-warning-subtle text-dark border me-1" style="background-color: #fffbeb;">mixed</span>
                            <span class="me-2">= SKUs have different stored rates (shows majority; Apply syncs all SKUs to that rate).</span>
                            Example: type <strong>6</strong> in Ship → Apply → all SKUs in that LB get <code>ship=6</code>, and outer Ship shows <strong>6</strong>.
                        </div>
                        <div class="text-muted mt-1">
                            Slabs use <strong>Itm wt GW Decl</strong> (ACT rounded up to the billable slab).
                            Carriers: Ship, Ship BB, TT 1 Ship, Temu ship, Temu GOFO, GOFO, Fedex, UPS, USPS, UNI.
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mb-2 gap-2 flex-wrap">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <label class="form-label mb-0 small fw-semibold" for="slabRatesScope">SKU scope:</label>
                            <select id="slabRatesScope" class="form-select form-select-sm" style="width: auto; min-width: 220px;" title="Which SKUs to update inside each slab">
                                <option value="all" selected>All SKUs in slab (overwrite)</option>
                                <option value="missing">Only SKUs missing that carrier's value</option>
                            </select>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <div class="d-flex align-items-center gap-1">
                                <label class="form-label mb-0 small fw-semibold" for="slabRatesFillRow">Fill row:</label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="slabRatesFillRow" placeholder="$" style="width: 90px;" title="Type a value and click Fill row to copy it into every empty carrier cell of one slab">
                                <select id="slabRatesFillRowTarget" class="form-select form-select-sm" style="width: auto; min-width: 160px;" title="Pick the slab to fill">
                                    <option value="">— pick slab —</option>
                                </select>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="slabRatesFillRowBtn" title="Copy the value into every empty carrier cell of the selected slab">Fill</button>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="slabRatesClearBtn" title="Clear all rate inputs">
                                <i class="fas fa-eraser me-1"></i> Clear inputs
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive" style="max-height: 62vh; border: 1px solid #e9ecef; border-radius: 8px;">
                        <table class="table table-sm mb-0 align-middle" id="slabRatesTable">
                            <thead style="position: sticky; top: 0; background: #f1f5f9; z-index: 3;">
                                <tr id="slabRatesHeadRow">
                                    <th class="slab-rates-sticky-col" style="font-size: 12px; min-width: 220px;">Weight Slab</th>
                                    <th class="text-center" style="font-size: 12px; width: 70px;"># SKUs</th>
                                    <!-- carrier <th>s injected here -->
                                </tr>
                            </thead>
                            <tbody id="slabRatesBody">
                                <tr><td colspan="13" class="text-center text-muted py-3">Loading slabs&hellip;</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div id="slabRatesProgress" class="mt-3" style="display: none;">
                        <div class="d-flex justify-content-between small mb-1">
                            <span id="slabRatesProgressLabel">Applying&hellip;</span>
                            <span id="slabRatesProgressCount">0 / 0</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-dark" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-dark" id="slabRatesApplyBtn">
                        <i class="fas fa-check me-2"></i> Apply Rates
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Store the loaded data globally
            let tableData = [];
            let filteredData = [];
            let productUniqueParents = [];
            let bulkEditList = null; // When set, save will update all these products with form values
            let isProductNavigationActive = false;
            let currentProductParentIndex = -1;

            // Same carriers as Slab Rates modal. Outer table shows these from the
            // slab majority/consensus so Ship matches the modal for that weight band.
            const SLAB_RATE_CARRIERS = [
                { key: 'ship',       label: 'Ship' },
                { key: 'ship_bb',    label: 'Ship BB' },
                { key: 'tt_ship',    label: 'TT 1 Ship' },
                { key: 'temu_ship',  label: 'Temu ship' },
                { key: 'temu_gofo',  label: 'Temu GOFO' },
                { key: 'gofo',       label: 'GOFO' },
                { key: 'fedex',      label: 'Fedex' },
                { key: 'ups',        label: 'UPS' },
                { key: 'usps',       label: 'USPS' },
                { key: 'uni',        label: 'UNI' }
            ];
            // { carrierKey: { slabKey: rate } } — rebuilt after every data load
            let slabRateIndex = {};
            let slabAutoSyncRunning = false;

            // Get CSRF token from meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            // Show loader immediately
            document.getElementById('rainbow-loader').style.display = 'block';

            // Centralized AJAX request function
            function makeRequest(url, method, data = {}) {
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                };

                if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
                    data._token = csrfToken;
                }

                return fetch(url, {
                    method: method,
                    headers: headers,
                    body: method === 'GET' ? null : JSON.stringify(data)
                });
            }

            // Escape HTML to prevent XSS
            function escapeHtml(text) {
                if (text == null) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Format number
            function formatNumber(value, decimals = 2) {
                if (value === null || value === undefined || value === '') return '-';
                const num = parseFloat(value);
                if (isNaN(num)) return '-';
                return num.toFixed(decimals);
            }

            /** Format SKU for display: add spaces between letter/digit segments (e.g. WF81202PCS4OHM -> WF 8120 2PCS 4 OHM) */
            function formatSkuDisplay(sku) {
                if (sku == null || String(sku).trim() === '') return '';
                let s = String(sku).replace(/[-_]/g, ' ');
                s = s.replace(/([A-Za-z])([0-9])/g, '$1 $2').replace(/([0-9])([A-Za-z])/g, '$1 $2');
                return s.replace(/\s+/g, ' ').trim();
            }
            /** Max characters to show for SKU/Parent in table cell; rest shown in tooltip */
            const SKU_DISPLAY_MAX_CHARS = 25;
            function formatSkuDisplayLimited(sku) {
                const full = formatSkuDisplay(sku);
                if (!full) return '';
                if (full.length <= SKU_DISPLAY_MAX_CHARS) return full;
                return full.substring(0, SKU_DISPLAY_MAX_CHARS) + '…';
            }
            function limitDisplayText(text, maxChars) {
                if (text == null || String(text).trim() === '') return '';
                const s = String(text).trim();
                if (s.length <= maxChars) return s;
                return s.substring(0, maxChars) + '…';
            }

            function getStatusDot(status) {
                const raw = String(status || '').trim();
                const s = raw.toLowerCase();
                const upper = raw.toUpperCase();
                let color = '#9ca3af';
                if (s === 'active') color = '#22c55e';
                else if (s === 'inactive') color = '#dc2626';
                else if (upper === 'DC') color = '#dc2626';
                else if (s === 'upcoming') color = '#eab308';
                else if (upper === '2BDC') color = '#2563eb';
                const title = raw || '-';
                return `<span class="status-dot" style="background-color:${color}" title="${escapeHtml(title)}"></span>`;
            }

            // Load Shipping Master data from server
            function loadData() {
                const cacheParam = '?ts=' + new Date().getTime();
                makeRequest('/shipping-master-data-view' + cacheParam, 'GET')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(async response => {
                        if (response && response.data && Array.isArray(response.data)) {
                            tableData = response.data;
                            rebuildSlabRateIndex();
                            applyFilters();
                            updateCounts();
                            refreshProductPlaybackState();
                            document.getElementById('rainbow-loader').style.display = 'none';
                            // Persist slab majority rates onto each SKU so other pages
                            // (pricing, comparison, marketplaces) read the same Values keys.
                            await syncSlabRatesToDatabase();
                        } else {
                            console.error('Invalid data format received from server');
                            document.getElementById('rainbow-loader').style.display = 'none';
                        }
                    })
                    .catch(error => {
                        console.error('Failed to load Shipping Master data: ' + error.message);
                        document.getElementById('rainbow-loader').style.display = 'none';
                    });
            }

            /** Label Type choices for the Type column. */
            const LABEL_TYPE_OPTIONS = ['ENV', 'STD', 'O-Size', 'Pallet'];
            const LABEL_TYPE_COLOR_CLASS = {
                'ENV': 'label-type-env',
                'STD': 'label-type-std',
                'O-Size': 'label-type-osize',
                'Pallet': 'label-type-pallet'
            };

            function normalizeLabelType(raw) {
                const v = String(raw == null ? '' : raw).trim();
                return LABEL_TYPE_OPTIONS.includes(v) ? v : 'STD';
            }

            function applyLabelTypeColor(dropdown, labelType) {
                if (!dropdown) return;
                const type = normalizeLabelType(labelType);
                Object.values(LABEL_TYPE_COLOR_CLASS).forEach(cls => dropdown.classList.remove(cls));
                const colorCls = LABEL_TYPE_COLOR_CLASS[type];
                if (colorCls) dropdown.classList.add(colorCls);
            }

            /** Shipping fields copied from Combo component SKUs onto package rows. */
            const COMBO_PACKAGE_AUTOFILL_FIELDS = [
                'wt_act_kg', 'wt_act', 'wt_decl',
                'l', 'w', 'h', 'l_decl', 'w_decl', 'h_decl', 'l_cm', 'w_cm', 'h_cm',
                'ctn_l', 'ctn_w', 'ctn_h', 'ctn_qty',
                'ship', 'ship_bb', 'tt_ship', 'temu_ship', 'temu_gofo',
                'gofo', 'fedex', 'ups', 'usps', 'uni',
                'fba_ship', 'fba_manual_ship', 'fba_sku', 'label_type',
                'image_path'
            ];

            function getLabelQtyNumber(item) {
                const labelQtyRaw = item?.label_qty ?? item?.['Label QTY'] ?? item?.Label_QTY;
                if (labelQtyRaw === null || labelQtyRaw === undefined || labelQtyRaw === '') return NaN;
                if (typeof labelQtyRaw === 'string' && labelQtyRaw.trim() === '') return NaN;
                return parseInt(labelQtyRaw, 10);
            }

            /** Combo when SKU/Parent contains COMBO or SKU is a "+" join of components. */
            function isComboSkuItem(item) {
                const sku = String(item?.SKU || '');
                const parent = String(item?.Parent || '');
                return /combo/i.test(sku) || /combo/i.test(parent) || sku.includes('+');
            }

            /** Split "A + B" / "A+B" Combo SKUs into component SKU strings. */
            function parseComboComponentSkus(sku) {
                if (sku == null || String(sku).trim() === '') return [];
                return String(sku)
                    .split('+')
                    .map(part => part.replace(/\u00a0/g, ' ').trim())
                    .filter(Boolean);
            }

            function findProductBySkuKey(sku) {
                const key = normalizeSkuKey(sku);
                if (!key) return null;
                return tableData.find(d => normalizeSkuKey(d.SKU) === key) || null;
            }

            function packageRowBgClass(packageIndex, packageCount) {
                if (packageCount < 2) return '';
                if (packageIndex === 2) return 'shipping-package-row-2';
                if (packageIndex === 3) return 'shipping-package-row-3';
                if (packageIndex === 4) return 'shipping-package-row-4';
                if (packageIndex > 4) return 'shipping-package-row-extra';
                return 'shipping-package-row-1';
            }

            /**
             * Label QTY >= 2 ⇒ one visual row per package.
             * Combo SKUs autopopulate dims/weight/ship fields from each "+" component SKU.
             */
            function buildShippingPackageRows(sourceItem) {
                const isParentRow = !!(sourceItem.SKU && String(sourceItem.SKU).toUpperCase().includes('PARENT'));
                const labelQtyNum = getLabelQtyNumber(sourceItem);
                const packageCount = (!isParentRow && Number.isFinite(labelQtyNum) && labelQtyNum >= 2)
                    ? labelQtyNum
                    : 1;
                const isCombo = !isParentRow && isComboSkuItem(sourceItem);
                const components = isCombo ? parseComboComponentSkus(sourceItem.SKU) : [];

                const rows = [];
                for (let i = 0; i < packageCount; i++) {
                    const packageIndex = i + 1;
                    const componentSku = components[i] || null;
                    const componentItem = componentSku ? findProductBySkuKey(componentSku) : null;
                    let displayItem = sourceItem;

                    if (componentItem) {
                        displayItem = Object.assign({}, sourceItem);
                        COMBO_PACKAGE_AUTOFILL_FIELDS.forEach(field => {
                            if (Object.prototype.hasOwnProperty.call(componentItem, field)) {
                                displayItem[field] = componentItem[field];
                            }
                        });
                    }

                    rows.push({
                        sourceItem,
                        displayItem,
                        packageIndex,
                        packageCount,
                        isExtraPackage: i > 0,
                        isCombo,
                        componentSku,
                        componentItem,
                        bgClass: packageRowBgClass(packageIndex, packageCount)
                    });
                }
                return rows;
            }

            // Render table
            function renderTable(data) {
                const tbody = document.getElementById('table-body');
                tbody.innerHTML = '';

                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="41" class="text-center">No data found</td></tr>';
                    return;
                }

                data.forEach(sourceItem => {
                    const packageRows = buildShippingPackageRows(sourceItem);
                    packageRows.forEach(pkg => {
                    const item = pkg.displayItem;
                    const row = document.createElement('tr');
                    const isParentRow = item.SKU && String(item.SKU).toUpperCase().includes('PARENT');
                    if (isParentRow) row.classList.add('shipping-parent-row');
                    if (pkg.bgClass) row.classList.add(pkg.bgClass);
                    if (pkg.isExtraPackage) row.classList.add('shipping-package-extra');
                    row.setAttribute('data-package-index', String(pkg.packageIndex));
                    row.setAttribute('data-package-count', String(pkg.packageCount));
                    // Resolve product for missing-M clicks / edits on this visual package row
                    const rowProductRef = (pkg.isExtraPackage && pkg.componentItem)
                        ? pkg.componentItem
                        : sourceItem;
                    row.setAttribute('data-id', rowProductRef.id != null ? String(rowProductRef.id) : '');
                    row.setAttribute('data-sku', rowProductRef.SKU != null ? String(rowProductRef.SKU) : '');
                    if (pkg.componentSku) {
                        row.setAttribute('data-component-sku', pkg.componentSku);
                    }
                    // Returns:
                    //   '--' for parent rows (aggregate placeholder)
                    //   '-'  for missing values on child rows (post-pass turns
                    //        these into the red "M" indicator, same as Product
                    //        Master)
                    //   formatted number otherwise (explicit 0 stays as "0").
                    const cellVal = (val, decimals) => {
                        if (isParentRow) return '--';
                        if (val === null || val === undefined || val === '' ||
                            (typeof val === 'string' && val.trim() === '')) {
                            return '-';
                        }
                        const n = parseFloat(val);
                        if (!Number.isFinite(n)) return '-';
                        return formatNumber(n, decimals);
                    };

                    // Checkbox column (primary package row only — avoid double-selecting combos)
                    const checkboxCell = document.createElement('td');
                    checkboxCell.className = 'text-center';
                    if (!pkg.isExtraPackage) {
                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.className = 'row-checkbox';
                        checkbox.value = sourceItem.SKU != null ? String(sourceItem.SKU) : '';
                        checkbox.setAttribute('data-sku', sourceItem.SKU != null ? String(sourceItem.SKU) : '');
                        checkbox.setAttribute('data-id', sourceItem.id != null ? String(sourceItem.id) : '');
                        checkbox.addEventListener('change', function() {
                            updatePushButtonState();
                        });
                        checkboxCell.appendChild(checkbox);
                    } else {
                        checkboxCell.innerHTML = '<span class="text-muted" style="font-size:10px;">↳</span>';
                    }
                    row.appendChild(checkboxCell);

                    // Image column
                    const imageCell = document.createElement('td');
                    imageCell.className = 'text-center';
                    imageCell.innerHTML = item.image_path 
                        ? `<img src="${item.image_path}" style="width:30px;height:30px;object-fit:cover;border-radius:4px;">`
                        : '-';
                    row.appendChild(imageCell);

                    // Parent column – limited characters; full value in tooltip (same as SKU)
                    const parentCell = document.createElement('td');
                    parentCell.className = 'td-parent-col';
                    parentCell.title = escapeHtml(item.Parent) || '';
                    const parentDisplay = limitDisplayText(item.Parent, SKU_DISPLAY_MAX_CHARS);
                    parentCell.textContent = parentDisplay ? escapeHtml(parentDisplay) : '-';
                    row.appendChild(parentCell);

                    // SKU column – Combo package rows also show the component SKU used for autofill
                    const skuCell = document.createElement('td');
                    skuCell.className = 'td-sku-col';
                    const comboSkuFull = sourceItem.SKU != null ? String(sourceItem.SKU) : '';
                    const componentSkuFull = pkg.componentSku ? String(pkg.componentSku) : '';
                    skuCell.title = componentSkuFull
                        ? `${comboSkuFull} — Package ${pkg.packageIndex}/${pkg.packageCount}: ${componentSkuFull}`
                        : (pkg.packageCount >= 2
                            ? `${comboSkuFull} — Package ${pkg.packageIndex}/${pkg.packageCount}`
                            : comboSkuFull);
                    const skuDisplay = formatSkuDisplayLimited(sourceItem.SKU);
                    const skuMain = document.createElement('span');
                    skuMain.textContent = skuDisplay ? skuDisplay : '-';
                    skuCell.appendChild(skuMain);
                    if (componentSkuFull) {
                        const compEl = document.createElement('span');
                        compEl.className = 'shipping-package-component';
                        compEl.textContent = formatSkuDisplayLimited(componentSkuFull) || componentSkuFull;
                        compEl.title = componentSkuFull;
                        skuCell.appendChild(compEl);
                    }
                    row.appendChild(skuCell);

                    // Status column – colored dot (same as product master)
                    const statusCell = document.createElement('td');
                    statusCell.className = 'text-center';
                    statusCell.innerHTML = getStatusDot(item.status);
                    row.appendChild(statusCell);

                    // Label Qty column (from Product Master Values.label_qty)
                    // Blank or 0 → missing ("-"/M badge); otherwise show the number.
                    // Multi-package rows also show Pkg i/n.
                    const labelQtyCell = document.createElement('td');
                    labelQtyCell.className = 'text-center shipping-rate-cell';
                    const labelQtyRaw = sourceItem.label_qty ?? sourceItem['Label QTY'] ?? sourceItem.Label_QTY;
                    const labelQtyBlank = labelQtyRaw === null || labelQtyRaw === undefined || labelQtyRaw === '' ||
                        (typeof labelQtyRaw === 'string' && labelQtyRaw.trim() === '');
                    const labelQtyNum = labelQtyBlank ? NaN : parseInt(labelQtyRaw, 10);
                    if (labelQtyBlank || (Number.isFinite(labelQtyNum) && labelQtyNum === 0)) {
                        labelQtyCell.textContent = '-';
                    } else {
                        const qtyLabel = document.createElement('div');
                        qtyLabel.textContent = Number.isFinite(labelQtyNum) ? String(labelQtyNum) : String(labelQtyRaw);
                        labelQtyCell.appendChild(qtyLabel);
                        if (labelQtyNum === 1) {
                            labelQtyCell.classList.add('label-qty-ok');
                        } else if (Number.isFinite(labelQtyNum) && labelQtyNum >= 2) {
                            labelQtyCell.classList.add('label-qty-alert');
                            const badge = document.createElement('span');
                            badge.className = 'shipping-package-badge';
                            badge.textContent = `Pkg ${pkg.packageIndex}/${pkg.packageCount}`;
                            labelQtyCell.appendChild(badge);
                        }
                    }
                    row.appendChild(labelQtyCell);

                    // Type column (Label Type) — ENV / STD / O-Size / Pallet; default STD
                    // Extra package rows show the component's type as read-only.
                    const labelTypeCell = document.createElement('td');
                    labelTypeCell.className = 'text-center shipping-rate-cell';
                    const labelTypeVal = normalizeLabelType(item.label_type);
                    const labelTypeColorCls = LABEL_TYPE_COLOR_CLASS[labelTypeVal] || 'label-type-std';
                    if (pkg.isExtraPackage) {
                        labelTypeCell.innerHTML = `<span class="label-type-dropdown ${labelTypeColorCls}" style="display:inline-block;pointer-events:none;" title="Label Type (from component)">${escapeHtml(labelTypeVal)}</span>`;
                    } else {
                        // Primary row edits the Combo/source SKU's own Label Type
                        const sourceLabelTypeVal = normalizeLabelType(sourceItem.label_type);
                        const sourceLabelTypeColorCls = LABEL_TYPE_COLOR_CLASS[sourceLabelTypeVal] || 'label-type-std';
                        labelTypeCell.innerHTML = `
                            <select class="label-type-dropdown ${sourceLabelTypeColorCls}"
                                data-sku="${escapeHtml(sourceItem.SKU || '')}"
                                data-id="${escapeHtml(String(sourceItem.id || ''))}"
                                data-prev="${escapeHtml(sourceLabelTypeVal)}"
                                title="Label Type">
                                ${LABEL_TYPE_OPTIONS.map(opt =>
                                    `<option value="${opt}"${opt === sourceLabelTypeVal ? ' selected' : ''}>${opt}</option>`
                                ).join('')}
                            </select>
                        `;
                    }
                    row.appendChild(labelTypeCell);

                    // INV column (bold; child 0 / missing = red)
                    const invCell = document.createElement('td');
                    invCell.className = 'text-center shipping-rate-cell';
                    if (isParentRow) {
                        invCell.textContent = '--';
                    } else if (item.shopify_inv === 0 || item.shopify_inv === "0") {
                        invCell.textContent = "0";
                        invCell.classList.add('shipping-rate-alert');
                    } else if (item.shopify_inv === null || item.shopify_inv === undefined || item.shopify_inv === "") {
                        invCell.textContent = "-";
                        invCell.classList.add('shipping-rate-alert');
                    } else {
                        invCell.textContent = escapeHtml(item.shopify_inv);
                    }
                    row.appendChild(invCell);

                    const setShippingNumericCell = (td, rawValue, isParentRow) => {
                        td.className = 'text-center shipping-rate-cell';
                        const emptyOrZero = (v) => {
                            if (v === null || v === undefined || v === '') return true;
                            const n = parseFloat(v);
                            return !Number.isFinite(n) || n === 0;
                        };
                        if (isParentRow) {
                            if (emptyOrZero(rawValue)) {
                                td.textContent = '-';
                            } else {
                                const n = parseFloat(rawValue);
                                td.textContent = Number.isFinite(n) ? formatNumber(n, 2) : '-';
                            }
                            return;
                        }
                        if (rawValue === null || rawValue === undefined || rawValue === '') {
                            td.textContent = '-';
                            td.classList.add('shipping-rate-alert');
                            return;
                        }
                        const n = parseFloat(rawValue);
                        if (!Number.isFinite(n)) {
                            td.textContent = '-';
                            td.classList.add('shipping-rate-alert');
                            return;
                        }
                        if (n === 0) {
                            td.textContent = formatNumber(0, 2);
                            td.classList.add('shipping-rate-alert');
                            return;
                        }
                        td.textContent = formatNumber(n, 2);
                    };

                    const shipPmCell = document.createElement('td');
                    setShippingNumericCell(shipPmCell, getOuterCarrierDisplayRate(item, 'ship', isParentRow), isParentRow);
                    shipPmCell.classList.add('shipping-ship-col');
                    annotateOuterCarrierCell(shipPmCell, item, 'ship', isParentRow);
                    row.appendChild(shipPmCell);

                    const shipBbPmCell = document.createElement('td');
                    setShippingNumericCell(shipBbPmCell, getOuterCarrierDisplayRate(item, 'ship_bb', isParentRow), isParentRow);
                    annotateOuterCarrierCell(shipBbPmCell, item, 'ship_bb', isParentRow);
                    row.appendChild(shipBbPmCell);

                    const carrierShipHighlight = [];
                    const appendCarrierShipCell = (carrierKey, extraClass = '') => {
                        const td = document.createElement('td');
                        setShippingNumericCell(td, getOuterCarrierDisplayRate(item, carrierKey, isParentRow), isParentRow);
                        annotateOuterCarrierCell(td, item, carrierKey, isParentRow);
                        if (extraClass) td.classList.add(...extraClass.split(/\s+/).filter(Boolean));
                        const value = carrierShipNumericFromCell(td);
                        if (value !== null) carrierShipHighlight.push({ td, value });
                        row.appendChild(td);
                    };

                    appendCarrierShipCell('tt_ship');
                    appendCarrierShipCell('temu_ship');
                    appendCarrierShipCell('temu_gofo');
                    appendCarrierShipCell('gofo');
                    appendCarrierShipCell('fedex', 'hide-carrier-col');
                    appendCarrierShipCell('ups', 'hide-carrier-col');
                    appendCarrierShipCell('usps', 'hide-carrier-col');
                    appendCarrierShipCell('uni', 'hide-carrier-col');
                    highlightCarrierShipMinMax(carrierShipHighlight);

                    const fbaSkuCell = document.createElement('td');
                    fbaSkuCell.className = 'td-sku-col shipping-rate-cell hide-fba-sku-col';
                    const fbaSkuVal = item.fba_sku != null && String(item.fba_sku).trim() !== '' ? String(item.fba_sku).trim() : '';
                    if (isParentRow) {
                        fbaSkuCell.textContent = fbaSkuVal ? formatSkuDisplayLimited(fbaSkuVal) : '-';
                        fbaSkuCell.title = fbaSkuVal || '';
                    } else {
                        fbaSkuCell.title = fbaSkuVal;
                        if (!fbaSkuVal) {
                            fbaSkuCell.textContent = '-';
                            fbaSkuCell.classList.add('shipping-rate-alert');
                        } else {
                            fbaSkuCell.textContent = formatSkuDisplayLimited(fbaSkuVal);
                        }
                    }
                    row.appendChild(fbaSkuCell);

                    const fbaShipCell = document.createElement('td');
                    setShippingNumericCell(fbaShipCell, item.fba_ship, isParentRow);
                    row.appendChild(fbaShipCell);

                    const fbaManualShipCell = document.createElement('td');
                    setShippingNumericCell(fbaManualShipCell, item.fba_manual_ship, isParentRow);
                    row.appendChild(fbaManualShipCell);

                    // Weight ACT (Kg) column (hidden)
                    const wtActKgCell = document.createElement('td');
                    wtActKgCell.className = 'text-center hide-item-wt-act';
                    wtActKgCell.textContent = cellVal(item.wt_act_kg, 1);
                    row.appendChild(wtActKgCell);

                    // WT ACT — below 1 lb shown as OZ, otherwise LB
                    const wtActCell = document.createElement('td');
                    wtActCell.className = 'text-center';
                    wtActCell.textContent = itemWeightActDisplay(item, isParentRow);
                    row.appendChild(wtActCell);

                    // L column (inch) - round to whole number
                    const lCell = document.createElement('td');
                    lCell.className = 'text-center';
                    lCell.textContent = cellVal(item.l, 0);
                    row.appendChild(lCell);

                    // W column (inch) - round to whole number
                    const wCell = document.createElement('td');
                    wCell.className = 'text-center';
                    wCell.textContent = cellVal(item.w, 0);
                    row.appendChild(wCell);

                    // H column (inch) - round to whole number
                    const hCell = document.createElement('td');
                    hCell.className = 'text-center';
                    hCell.textContent = cellVal(item.h, 0);
                    row.appendChild(hCell);

                    // Decl columns — fall back to ACT values when Decl is empty
                    const wtDeclCell = document.createElement('td');
                    wtDeclCell.className = 'text-center';
                    wtDeclCell.textContent = itemWeightDeclDisplay(item, isParentRow);
                    row.appendChild(wtDeclCell);

                    const lDeclCell = document.createElement('td');
                    lDeclCell.className = 'text-center';
                    lDeclCell.textContent = isParentRow ? '--' : cellVal(itemDeclValue(item, 'l_decl', 'l'), 0);
                    row.appendChild(lDeclCell);

                    const wDeclCell = document.createElement('td');
                    wDeclCell.className = 'text-center';
                    wDeclCell.textContent = isParentRow ? '--' : cellVal(itemDeclValue(item, 'w_decl', 'w'), 0);
                    row.appendChild(wDeclCell);

                    const hDeclCell = document.createElement('td');
                    hDeclCell.className = 'text-center';
                    hDeclCell.textContent = isParentRow ? '--' : cellVal(itemDeclValue(item, 'h_decl', 'h'), 0);
                    row.appendChild(hDeclCell);

                    // Length (CM) column (use stored value or convert from inch) - hidden
                    const lCmCell = document.createElement('td');
                    lCmCell.className = 'text-center item-cm-col';
                    const lCmVal = item.l_cm != null && item.l_cm !== undefined && item.l_cm !== ''
                        ? item.l_cm
                        : (parseFloat(item.l) || 0) * 2.54;
                    lCmCell.textContent = cellVal(lCmVal, 0);
                    row.appendChild(lCmCell);

                    // Width (CM) column (use stored value or convert from inch) - hidden
                    const wCmCell = document.createElement('td');
                    wCmCell.className = 'text-center item-cm-col';
                    const wCmVal = item.w_cm != null && item.w_cm !== undefined && item.w_cm !== ''
                        ? item.w_cm
                        : (parseFloat(item.w) || 0) * 2.54;
                    wCmCell.textContent = cellVal(wCmVal, 0);
                    row.appendChild(wCmCell);

                    // Height (CM) column (use stored value or convert from inch) - hidden
                    const hCmCell = document.createElement('td');
                    hCmCell.className = 'text-center item-cm-col';
                    const hCmVal = item.h_cm != null && item.h_cm !== undefined && item.h_cm !== ''
                        ? item.h_cm
                        : (parseFloat(item.h) || 0) * 2.54;
                    hCmCell.textContent = cellVal(hCmVal, 0);
                    row.appendChild(hCmCell);

                    // CTN L (CM) column (hidden by CSS)
                    const ctnLenCmCell = document.createElement('td');
                    ctnLenCmCell.className = 'text-center ctn-cm-col';
                    ctnLenCmCell.textContent = cellVal(item.ctn_l, 0);
                    row.appendChild(ctnLenCmCell);

                    // CTN W (CM) column (hidden by CSS)
                    const ctnWidCmCell = document.createElement('td');
                    ctnWidCmCell.className = 'text-center ctn-cm-col';
                    ctnWidCmCell.textContent = cellVal(item.ctn_w, 0);
                    row.appendChild(ctnWidCmCell);

                    // CTN H (CM) column (hidden by CSS)
                    const ctnHeiCmCell = document.createElement('td');
                    ctnHeiCmCell.className = 'text-center ctn-cm-col';
                    ctnHeiCmCell.textContent = cellVal(item.ctn_h, 0);
                    row.appendChild(ctnHeiCmCell);

                    // CTN CBM column (calculated: CTN L * CTN W * CTN H / 1000000)
                    const ctnCbmCalculated = (parseFloat(item.ctn_l) || 0) * (parseFloat(item.ctn_w) || 0) * (parseFloat(item.ctn_h) || 0) / 1000000;
                    const ctnCbmCell = document.createElement('td');
                    ctnCbmCell.className = 'text-center';
                    ctnCbmCell.textContent = cellVal(ctnCbmCalculated, 1);
                    row.appendChild(ctnCbmCell);

                    // CTN (QTY) column
                    const ctnQtyCell = document.createElement('td');
                    ctnQtyCell.className = 'text-center';
                    ctnQtyCell.textContent = cellVal(item.ctn_qty, 0);
                    row.appendChild(ctnQtyCell);

                    // CTN CBM each column (calculated: CTN CBM / CTN Qty)
                    const ctnQtyVal = parseFloat(item.ctn_qty) || 0;
                    const ctnCbmEachCalculated = ctnQtyVal > 0 ? ctnCbmCalculated / ctnQtyVal : 0;
                    const ctnCbmEachCell = document.createElement('td');
                    ctnCbmEachCell.className = 'text-center';
                    ctnCbmEachCell.textContent = cellVal(ctnCbmEachCalculated, 1);
                    row.appendChild(ctnCbmEachCell);

                    // Verified column – red/green dot toggle (primary package row only)
                    const verifiedSource = pkg.isExtraPackage && pkg.componentItem ? pkg.componentItem : sourceItem;
                    const isVerified = verifiedSource.verified_data === 1 || verifiedSource.verified_data === true ||
                        (verifiedSource.Values && (verifiedSource.Values.verified_data === 1 || verifiedSource.Values.verified_data === true));
                    const verifiedClass = isVerified ? 'verified' : 'not-verified';
                    const verifiedCell = document.createElement('td');
                    verifiedCell.className = 'text-center';
                    if (pkg.isExtraPackage) {
                        verifiedCell.innerHTML = `<span title="${isVerified ? 'Verified' : 'Not verified'}">${isVerified ? '🟢' : '🔴'}</span>`;
                    } else {
                        verifiedCell.innerHTML = `
                            <select class="verified-data-dropdown ${verifiedClass}"
                                data-sku="${escapeHtml(sourceItem.SKU)}" data-id="${escapeHtml(sourceItem.id)}"
                                title="${isVerified ? 'Verified' : 'Not verified'}">
                                <option value="0" ${!isVerified ? 'selected' : ''}>🔴</option>
                                <option value="1" ${isVerified ? 'selected' : ''}>🟢</option>
                            </select>
                        `;
                    }
                    row.appendChild(verifiedCell);

                    // Action column
                    // Primary row edits the Combo/source SKU; extra Combo package rows edit the component SKU.
                    const actionCell = document.createElement('td');
                    actionCell.className = 'text-center';
                    const editTarget = (pkg.isExtraPackage && pkg.componentItem) ? pkg.componentItem : sourceItem;
                    const showDelete = !pkg.isExtraPackage;
                    actionCell.innerHTML = `
                        <div class="d-inline-flex action-btns">
                            <button type="button" class="btn btn-sm btn-outline-warning edit-btn" data-id="${editTarget.id != null ? String(editTarget.id) : ''}" data-sku="${escapeHtml(editTarget.SKU)}" title="${pkg.isExtraPackage ? 'Edit component package' : 'Edit'}">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info history-btn" data-id="${editTarget.id != null ? String(editTarget.id) : ''}" data-sku="${escapeHtml(editTarget.SKU)}" title="History — see who changed what">
                                <i class="bi bi-clock-history"></i>
                            </button>
                            ${showDelete ? `
                            <button class="btn btn-sm btn-outline-danger delete-btn" data-id="${escapeHtml(sourceItem.id)}" data-sku="${escapeHtml(sourceItem.SKU)}" title="Delete">
                                <i class="bi bi-archive"></i>
                            </button>` : ''}
                        </div>
                    `;
                    row.appendChild(actionCell);
                    
                    // Add event listener for edit button
                    // Multi-select + pencil = bulk edit (no separate Bulk Edit button)
                    const editBtn = actionCell.querySelector('.edit-btn');
                    editBtn.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const sku = this.getAttribute('data-sku');
                        let product = null;
                        if (id != null && id !== '') {
                            product = tableData.find(d => String(d.id) === String(id));
                        }
                        if (!product && sku) {
                            const key = normalizeSkuKey(sku);
                            product = tableData.find(d => normalizeSkuKey(d.SKU) === key);
                        }
                        if (!product) return;
                        const selected = getSelectedNonParentProducts();
                        const clickedInSelection = selected.some(p =>
                            (p.id != null && product.id != null && String(p.id) === String(product.id)) ||
                            (p.SKU && product.SKU && normalizeSkuKey(p.SKU) === normalizeSkuKey(product.SKU))
                        );
                        if (selected.length > 1 && clickedInSelection) {
                            bulkEditList = selected;
                        } else {
                            bulkEditList = null;
                        }
                        editDimWt(product);
                    });

                    // Add event listener for history button
                    const historyBtn = actionCell.querySelector('.history-btn');
                    if (historyBtn) {
                        historyBtn.addEventListener('click', function() {
                            const id = this.getAttribute('data-id');
                            const sku = this.getAttribute('data-sku');
                            openShippingHistoryModal(id, sku);
                        });
                    }

                    if (!isParentRow) convertMissingDashesToIndicator(row);
                    tbody.appendChild(row);
                    }); // end packageRows.forEach
                });
                applyDimWtSectionFilter();
                applyShippingColumnVisibility();
            }

            /** Cell column index → field key on `item` (and on the
             *  /dim-wt-master/update payload). Used by the missing-indicator
             *  click handler to focus the right input. */
            const SHIPPING_COLUMN_INDEX_TO_FIELD = {
                5:  'label_qty',
                6:  'label_type',
                7:  'inv',
                8:  'ship',
                9:  'ship_bb',
                10: 'tt_ship',
                11: 'temu_ship',
                12: 'temu_gofo',
                13: 'gofo',
                14: 'fedex',
                15: 'ups',
                16: 'usps',
                17: 'uni',
                18: 'fba_sku',
                19: 'fba_ship',
                20: 'fba_manual_ship',
                21: 'wt_act_kg',
                22: 'wt_act',
                23: 'l',
                24: 'w',
                25: 'h',
                26: 'wt_decl',
                27: 'l_decl',
                28: 'w_decl',
                29: 'h_decl',
                30: 'l_cm',
                31: 'w_cm',
                32: 'h_cm',
                33: 'ctn_l',
                34: 'ctn_w',
                35: 'ctn_h',
                37: 'ctn_qty'
            };

            /** Human-readable field labels used by the small "Enter Missing
             *  Data" modal title. */
            const SHIPPING_FIELD_LABELS = {
                label_qty:       'Label Qty',
                label_type:      'Label Type',
                wt_act_kg:       'Item Weight ACT (Kg)',
                wt_act:          'Item WT ACT (LB)',
                wt_decl:         'Itm wt GW Decl',
                l:               'Item Length (inch)',
                w:               'Item Width (inch)',
                h:               'Item Height (inch)',
                l_decl:          'Item L IN Decl',
                w_decl:          'Item W IN Decl',
                h_decl:          'Item H IN Decl',
                l_cm:            'Item Length (CM)',
                w_cm:            'Item Width (CM)',
                h_cm:            'Item Height (CM)',
                ctn_l:           'CTN L (CM)',
                ctn_w:           'CTN W (CM)',
                ctn_h:           'CTN H (CM)',
                ctn_qty:         'CTN (QTY)',
                ship:            'Ship',
                ship_bb:         'Ship BB',
                tt_ship:         'TT 1 Ship',
                temu_ship:       'Temu ship',
                temu_gofo:       'Temu GOFO',
                gofo:            'GOFO',
                fedex:           'Fedex',
                ups:             'UPS',
                usps:            'USPS',
                uni:             'UNI',
                fba_ship:        'FBA ship',
                fba_manual_ship: 'FBA manual ship',
                inv:             'INV (Shopify)',
                fba_sku:         'FBA SKU'
            };

            /** Per-field input step (most fields are dollars / cm, so 0.01;
             *  ctn_qty / label_qty are whole-number counts). */
            const SHIPPING_FIELD_STEP = {
                ctn_qty: '1',
                label_qty: '1'
            };

            /** Fields that the small modal cannot save: they live outside the
             *  ProductMaster.Values JSON. On M click we explain that instead
             *  of opening the editor. */
            const SHIPPING_READONLY_FIELDS = {
                inv:     'INV comes from Shopify and is not editable here. Update inventory in Shopify (or in the Inventory Master).',
                fba_sku: 'FBA SKU lives in the FBA calculation table. Update it from the FBA module.',
                ship:       'Ship rates are edited only from Slab Rates (by weight LB), not per SKU.',
                ship_bb:    'Ship BB is edited only from Slab Rates (by weight LB), not per SKU.',
                tt_ship:    'TT 1 Ship is edited only from Slab Rates (by weight LB), not per SKU.',
                temu_ship:  'Temu ship is edited only from Slab Rates (by weight LB), not per SKU.',
                temu_gofo:  'Temu GOFO is edited only from Slab Rates (by weight LB), not per SKU.',
                gofo:       'GOFO is edited only from Slab Rates (by weight LB), not per SKU.',
                fedex:      'Fedex is edited only from Slab Rates (by weight LB), not per SKU.',
                ups:        'UPS is edited only from Slab Rates (by weight LB), not per SKU.',
                usps:       'USPS is edited only from Slab Rates (by weight LB), not per SKU.',
                uni:        'UNI is edited only from Slab Rates (by weight LB), not per SKU.'
            };

            /** Markup for the red "M" missing-data badge — identical look to
             *  Product Master so the two pages stay visually consistent. */
            function missingDataIndicatorHtml(field) {
                const f = field ? ` data-field="${field}"` : '';
                return `<span class="missing-data-indicator" title="Missing Data — click to edit"${f}>M</span>`;
            }

            /** Walk a non-parent row and replace any cell that shows only "-"
             *  (single dash, set by cellVal / itemWeight*Display / setShipping*)
             *  with the M badge. Cells containing form controls, links, the
             *  status dot, etc. are skipped because they have child elements.
             *  The badge gets a `data-field` matching the cell's column so the
             *  click handler can focus the right input in the edit modal. */
            function convertMissingDashesToIndicator(row) {
                if (!row) return;
                row.querySelectorAll('td').forEach(td => {
                    if (td.children.length > 0) return;
                    const text = (td.textContent || '').trim();
                    if (text !== '-') return;
                    const field = SHIPPING_COLUMN_INDEX_TO_FIELD[td.cellIndex] || '';
                    td.innerHTML = missingDataIndicatorHtml(field);
                    td.classList.add('has-missing-indicator');
                });
            }

            /** Reference to the M badge that triggered the small modal, so we
             *  can update the cell in place after a successful save. */
            let currentMissingDataButton = null;

            /** Click handler for M badges: open the small "Enter Missing Data"
             *  modal so the user can edit ONLY that one field — same UX as
             *  Product Master. The full edit modal is reachable from the
             *  pencil icon in the Action column if the user needs broader
             *  edits. */
            function setupMissingIndicatorClicks() {
                const tableEl = document.getElementById('dim-wt-master-datatable');
                if (!tableEl) return;
                tableEl.addEventListener('click', function (e) {
                    const indicator = e.target.closest('.missing-data-indicator');
                    if (!indicator) return;
                    e.preventDefault();
                    e.stopPropagation();

                    const row = indicator.closest('tr');
                    if (!row) return;
                    let product = null;
                    const checkbox = row.querySelector('.row-checkbox');
                    if (checkbox) {
                        product = findProductByRowRef(checkbox);
                    }
                    if (!product) {
                        const rowId = row.getAttribute('data-id');
                        const rowSku = row.getAttribute('data-sku');
                        if (rowId) {
                            product = tableData.find(d => String(d.id) === String(rowId)) || null;
                        }
                        if (!product && rowSku) {
                            product = findProductBySkuKey(rowSku);
                        }
                    }
                    if (!product) {
                        showToast('warning', 'Could not find the matching SKU for that row.');
                        return;
                    }

                    const fieldKey = indicator.getAttribute('data-field') || '';
                    if (SHIPPING_READONLY_FIELDS[fieldKey]) {
                        showToast('info', SHIPPING_READONLY_FIELDS[fieldKey]);
                        return;
                    }
                    if (!fieldKey || !SHIPPING_FIELD_LABELS[fieldKey]) {
                        // Calculated columns (ctn_cbm, ctn_cbm_each) — no
                        // single field to edit. Fall back to the full modal.
                        bulkEditList = null;
                        editDimWt(product);
                        return;
                    }

                    openMissingDataModal({
                        product,
                        field: fieldKey,
                        indicator
                    });
                });

                // Save handler for the small modal
                const saveBtn = document.getElementById('saveMissingDataBtn');
                if (saveBtn) {
                    saveBtn.addEventListener('click', saveMissingData);
                }

                // Reset state when the small modal is dismissed
                const missingModalEl = document.getElementById('missingDataModal');
                if (missingModalEl) {
                    missingModalEl.addEventListener('hidden.bs.modal', function () {
                        currentMissingDataButton = null;
                        const valEl = document.getElementById('missingDataValue');
                        if (valEl) valEl.value = '';
                        const errEl = document.getElementById('missingDataError');
                        if (errEl) errEl.style.display = 'none';
                    });

                    // Submit on Enter inside the value input
                    const valEl = document.getElementById('missingDataValue');
                    if (valEl) {
                        valEl.addEventListener('keydown', function (e) {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                saveMissingData();
                            }
                        });
                    }
                }
            }

            /** Open the small modal pre-configured for one field on one SKU. */
            function openMissingDataModal({ product, field, indicator }) {
                const label = SHIPPING_FIELD_LABELS[field] || field;
                const step = SHIPPING_FIELD_STEP[field] || '0.01';

                currentMissingDataButton = indicator;

                document.getElementById('missingDataSku').textContent = product.SKU || '';
                document.getElementById('missingDataField').textContent = label;
                document.getElementById('missingDataModalLabel').innerHTML =
                    '<i class="fas fa-edit me-2"></i>Enter Missing ' + escapeHtml(label);

                const valEl = document.getElementById('missingDataValue');
                valEl.value = '';
                valEl.step = step;
                valEl.placeholder = 'Enter ' + label + '…';
                valEl.setAttribute('data-sku', product.SKU || '');
                valEl.setAttribute('data-product-id', product.id != null ? String(product.id) : '');
                valEl.setAttribute('data-parent', product.Parent || '');
                valEl.setAttribute('data-field', field);

                const hintEl = document.getElementById('missingDataHint');
                if (hintEl) {
                    hintEl.textContent = step === '1'
                        ? 'Enter a whole-number value.'
                        : 'Enter a numeric value (decimals allowed).';
                }

                const errEl = document.getElementById('missingDataError');
                if (errEl) errEl.style.display = 'none';

                const modalEl = document.getElementById('missingDataModal');
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();

                setTimeout(() => valEl.focus(), 250);
            }

            /** Persist a single field via the existing /dim-wt-master/update
             *  endpoint, then update the cell in place so the M badge becomes
             *  the new value without a full table reload. */
            async function saveMissingData() {
                if (!currentMissingDataButton) {
                    showToast('danger', 'Lost reference to the cell. Please re-open the row.');
                    return;
                }

                const valEl = document.getElementById('missingDataValue');
                const errEl = document.getElementById('missingDataError');
                const saveBtn = document.getElementById('saveMissingDataBtn');
                const originalSaveHtml = saveBtn ? saveBtn.innerHTML : '';

                const raw = String(valEl.value || '').trim();
                const productId = valEl.getAttribute('data-product-id');
                const sku = valEl.getAttribute('data-sku');
                const parent = valEl.getAttribute('data-parent') || '';
                const field = valEl.getAttribute('data-field');

                if (!sku || !field) {
                    errEl.textContent = 'SKU or field is missing — please close and try again.';
                    errEl.style.display = 'block';
                    return;
                }
                if (SHIPPING_READONLY_FIELDS[field]) {
                    errEl.textContent = SHIPPING_READONLY_FIELDS[field];
                    errEl.style.display = 'block';
                    return;
                }
                if (raw === '') {
                    errEl.textContent = 'Please enter a value.';
                    errEl.style.display = 'block';
                    return;
                }
                const numValue = parseFloat(raw);
                if (!Number.isFinite(numValue) || numValue < 0) {
                    errEl.textContent = 'Please enter a valid non-negative number.';
                    errEl.style.display = 'block';
                    return;
                }

                if (saveBtn) {
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving…';
                }

                try {
                    const payload = {
                        product_id: productId ? Number(productId) : undefined,
                        sku: sku,
                        parent: parent,
                        [field]: numValue
                    };

                    const response = await fetch('/dim-wt-master/update', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify(payload)
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || data.success === false) {
                        throw new Error(data.message || ('Failed to save (HTTP ' + response.status + ')'));
                    }

                    // Mirror the saved value into the in-memory table data so
                    // subsequent renders / slab summaries reflect the change
                    // without a full reload.
                    if (Array.isArray(tableData)) {
                        const matchId = productId ? String(productId) : null;
                        const target = tableData.find(d =>
                            (matchId && String(d.id) === matchId) ||
                            (d.SKU && sku && String(d.SKU) === String(sku))
                        );
                        if (target) target[field] = numValue;
                    }

                    // Swap the M badge with the formatted value in place.
                    const cell = currentMissingDataButton.closest('td');
                    if (cell) {
                        cell.classList.remove('has-missing-indicator');
                        const display = (field === 'ctn_qty') ? String(Math.round(numValue)) : formatNumber(numValue, 2);
                        cell.innerHTML = '';
                        cell.textContent = display;
                    }

                    showToast('success', (SHIPPING_FIELD_LABELS[field] || field) + ' saved.');

                    bootstrap.Modal.getInstance(document.getElementById('missingDataModal')).hide();
                } catch (err) {
                    console.error('Missing data save failed:', err);
                    errEl.textContent = err.message || 'Failed to save data.';
                    errEl.style.display = 'block';
                } finally {
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = originalSaveHtml || '<i class="fas fa-save me-1"></i>Save';
                    }
                }
            }

            function applyDimWtSectionFilter() {
                applyShippingColumnVisibility();
            }

            // ── Column visibility (channel_tabulator_column_settings) ──
            const SHIPPING_COLUMN_CHANNEL = 'shipping_master';
            const SHIPPING_COLUMN_VISIBILITY_URL = '/tabulator-column-visibility';
            const SHIPPING_COL_LOCKED = { select: true, action: true };
            const SHIPPING_COL_CATEGORY_KEYS = ['basic', 'ship_rates', 'wt', 'dimensions', 'other'];
            const SHIPPING_COL_CATEGORY_LABELS = {
                basic: 'Basic',
                ship_rates: 'Ship Rates',
                wt: 'Wt',
                dimensions: 'Dimensions',
                other: 'Other',
            };
            /** Default show/hide — matches previous CSS-hidden + removed Carton Data columns. */
            const SHIPPING_COL_DEFAULT_VISIBILITY = {
                wt_act_kg: false,
                l_cm: false,
                w_cm: false,
                h_cm: false,
                ctn_l: false,
                ctn_w: false,
                ctn_h: false,
                ctn_cbm: false,
                ctn_qty: false,
                ctn_cbm_each: false,
                fba_sku: false,
                fedex: false,
                ups: false,
                usps: false,
                uni: false,
            };
            const SHIPPING_COL_CATEGORIES = {
                basic: ['select', 'image', 'parent', 'sku', 'status', 'label_qty', 'label_type', 'inv', 'verified', 'action'],
                ship_rates: ['ship', 'ship_bb', 'tt_ship', 'temu_ship', 'temu_gofo', 'gofo', 'fedex', 'ups', 'usps', 'uni', 'fba_sku', 'fba_ship', 'fba_manual_ship'],
                wt: ['wt_act_kg', 'wt_act', 'wt_decl'],
                dimensions: ['l', 'w', 'h', 'l_decl', 'w_decl', 'h_decl', 'l_cm', 'w_cm', 'h_cm', 'ctn_l', 'ctn_w', 'ctn_h', 'ctn_cbm', 'ctn_qty', 'ctn_cbm_each'],
                other: [],
            };

            let shippingColVisMap = {};

            function shippingColCategoryForKey(key) {
                for (const cat of SHIPPING_COL_CATEGORY_KEYS) {
                    if ((SHIPPING_COL_CATEGORIES[cat] || []).includes(key)) return cat;
                }
                return 'other';
            }

            function isShippingColumnUserVisible(key) {
                if (!key) return true;
                if (SHIPPING_COL_LOCKED[key]) return true;
                if (Object.prototype.hasOwnProperty.call(shippingColVisMap, key)) {
                    return shippingColVisMap[key] !== false;
                }
                if (Object.prototype.hasOwnProperty.call(SHIPPING_COL_DEFAULT_VISIBILITY, key)) {
                    return SHIPPING_COL_DEFAULT_VISIBILITY[key] !== false;
                }
                return true;
            }

            function applyShippingColumnVisibility() {
                const table = document.getElementById('dim-wt-master-datatable');
                if (!table) return;
                const theadRow = table.querySelector('thead tr');
                const tbody = document.getElementById('table-body');
                if (!theadRow || !tbody) return;
                const ths = theadRow.querySelectorAll('th');

                for (let i = 0; i < ths.length; i++) {
                    const th = ths[i];
                    const key = th.getAttribute('data-col-key') || '';
                    const show = isShippingColumnUserVisible(key);

                    th.classList.toggle('col-user-hidden', !show);
                    th.classList.toggle('col-user-visible', show);
                    th.style.display = '';
                    tbody.querySelectorAll('tr').forEach(tr => {
                        const cell = tr.cells[i];
                        if (!cell) return;
                        cell.classList.toggle('col-user-hidden', !show);
                        cell.classList.toggle('col-user-visible', show);
                        cell.style.display = '';
                    });
                }
            }

            function collectShippingColumnVisibilityMap() {
                const table = document.getElementById('dim-wt-master-datatable');
                const map = {};
                if (!table) return map;
                table.querySelectorAll('thead th[data-col-key]').forEach(th => {
                    const key = th.getAttribute('data-col-key');
                    if (!key || SHIPPING_COL_LOCKED[key]) return;
                    map[key] = isShippingColumnUserVisible(key);
                });
                return map;
            }

            function saveShippingColumnVisibility() {
                const visibility = collectShippingColumnVisibilityMap();
                shippingColVisMap = { ...visibility };
                return fetch(SHIPPING_COLUMN_VISIBILITY_URL, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        channel: SHIPPING_COLUMN_CHANNEL,
                        visibility,
                    }),
                }).catch(err => console.warn('shipping column visibility save failed:', err));
            }

            function fetchShippingColumnVisibility() {
                return fetch(
                    SHIPPING_COLUMN_VISIBILITY_URL + '?channel=' + encodeURIComponent(SHIPPING_COLUMN_CHANNEL),
                    { credentials: 'same-origin', headers: { Accept: 'application/json' } }
                )
                    .then(r => r.json())
                    .then(map => {
                        shippingColVisMap = (map && typeof map === 'object' && !Array.isArray(map)) ? map : {};
                        // Removed Carton Data section — keep those columns hidden
                        let cleared = false;
                        ['ctn_cbm', 'ctn_qty', 'ctn_cbm_each'].forEach(k => {
                            if (shippingColVisMap[k] !== false) {
                                shippingColVisMap[k] = false;
                                cleared = true;
                            }
                        });
                        // Always show Decl dim/wt columns (match /dim-wt-master)
                        ['wt_decl', 'l_decl', 'w_decl', 'h_decl'].forEach(k => {
                            if (shippingColVisMap[k] === false) {
                                shippingColVisMap[k] = true;
                                cleared = true;
                            }
                        });
                        if (cleared) saveShippingColumnVisibility();
                        return shippingColVisMap;
                    })
                    .catch(() => {
                        shippingColVisMap = {};
                        return {};
                    });
            }

            function getShippingCategoryToggleKeys(cat) {
                return (SHIPPING_COL_CATEGORIES[cat] || []).filter(key => key && !SHIPPING_COL_LOCKED[key]);
            }

            function syncShippingCategoryHeaderCheckbox(catCb, cat) {
                if (!catCb) return;
                const keys = getShippingCategoryToggleKeys(cat);
                if (keys.length === 0) {
                    catCb.checked = false;
                    catCb.indeterminate = false;
                    catCb.disabled = true;
                    return;
                }
                catCb.disabled = false;
                const checkedCount = keys.filter(key => isShippingColumnUserVisible(key)).length;
                catCb.checked = checkedCount === keys.length;
                catCb.indeterminate = checkedCount > 0 && checkedCount < keys.length;
            }

            function setShippingCategoryVisibility(cat, visible) {
                getShippingCategoryToggleKeys(cat).forEach(key => {
                    shippingColVisMap[key] = !!visible;
                });
                applyShippingColumnVisibility();
                saveShippingColumnVisibility();
            }

            function buildShippingColumnDropdown() {
                const menu = document.getElementById('shipping-column-dropdown-menu');
                const table = document.getElementById('dim-wt-master-datatable');
                if (!menu || !table) return;
                menu.innerHTML = '';

                const showAllLi = document.createElement('li');
                showAllLi.innerHTML = '<a class="dropdown-item py-1" href="#" id="shipping-show-all-columns-btn"><i class="fa fa-eye"></i> Show All</a>';
                menu.appendChild(showAllLi);

                const hintLi = document.createElement('li');
                hintLi.innerHTML = '<div class="px-2 pb-1 text-muted" style="font-size:0.7rem;">Use group checkboxes to select / deselect all</div>';
                menu.appendChild(hintLi);

                const groupsLi = document.createElement('li');
                const groupsWrap = document.createElement('div');
                groupsWrap.className = 'col-vis-groups';

                const lists = {};
                const categoryHeaderCbs = {};
                SHIPPING_COL_CATEGORY_KEYS.forEach(cat => {
                    const group = document.createElement('div');
                    group.className = 'col-vis-group';
                    group.dataset.category = cat;

                    const titleEl = document.createElement('label');
                    titleEl.className = 'col-vis-group-title';
                    const catCb = document.createElement('input');
                    catCb.type = 'checkbox';
                    catCb.className = 'col-vis-group-check';
                    catCb.dataset.category = cat;
                    catCb.title = 'Select / deselect all in ' + SHIPPING_COL_CATEGORY_LABELS[cat];
                    catCb.addEventListener('change', () => {
                        setShippingCategoryVisibility(cat, catCb.checked);
                        buildShippingColumnDropdown();
                    });
                    titleEl.appendChild(catCb);
                    titleEl.appendChild(document.createTextNode(SHIPPING_COL_CATEGORY_LABELS[cat]));
                    group.appendChild(titleEl);

                    const list = document.createElement('ul');
                    list.className = 'col-vis-group-list';
                    group.appendChild(list);
                    groupsWrap.appendChild(group);
                    lists[cat] = list;
                    categoryHeaderCbs[cat] = catCb;
                });

                const ths = table.querySelectorAll('thead th[data-col-key]');
                ths.forEach(th => {
                    const key = th.getAttribute('data-col-key');
                    const label = th.getAttribute('data-col-label') || key;
                    const cat = shippingColCategoryForKey(key);
                    const locked = !!SHIPPING_COL_LOCKED[key];

                    const li = document.createElement('li');
                    li.className = 'col-vis-item';
                    const lab = document.createElement('label');
                    if (locked) lab.className = 'col-vis-locked';

                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.value = key;
                    cb.checked = isShippingColumnUserVisible(key);
                    cb.disabled = locked;
                    cb.style.marginRight = '6px';
                    if (!locked) {
                        cb.addEventListener('change', () => {
                            shippingColVisMap[key] = !!cb.checked;
                            applyShippingColumnVisibility();
                            saveShippingColumnVisibility();
                            syncShippingCategoryHeaderCheckbox(categoryHeaderCbs[cat], cat);
                        });
                    }

                    lab.appendChild(cb);
                    lab.appendChild(document.createTextNode(label));
                    li.appendChild(lab);
                    (lists[cat] || lists.other).appendChild(li);
                });

                SHIPPING_COL_CATEGORY_KEYS.forEach(cat => {
                    const catCb = categoryHeaderCbs[cat];
                    const titleEl = catCb ? catCb.closest('.col-vis-group-title') : null;
                    if (getShippingCategoryToggleKeys(cat).length === 0 && titleEl) {
                        titleEl.classList.add('col-vis-group-empty');
                    }
                    syncShippingCategoryHeaderCheckbox(catCb, cat);
                });

                groupsLi.appendChild(groupsWrap);
                menu.appendChild(groupsLi);

                const showAllBtn = document.getElementById('shipping-show-all-columns-btn');
                if (showAllBtn) {
                    showAllBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        table.querySelectorAll('thead th[data-col-key]').forEach(th => {
                            const key = th.getAttribute('data-col-key');
                            if (!key || SHIPPING_COL_LOCKED[key]) return;
                            shippingColVisMap[key] = true;
                        });
                        applyShippingColumnVisibility();
                        saveShippingColumnVisibility().then(() => buildShippingColumnDropdown());
                    });
                }
            }

            function setupShippingColumnVisibility() {
                fetchShippingColumnVisibility().then(() => {
                    applyShippingColumnVisibility();
                    buildShippingColumnDropdown();
                });
            }

            // Update counts
            function updateCounts() {
                const parentSet = new Set();
                let skuCount = 0;
                let wtActKgMissingCount = 0;
                let wtActMissingCount = 0;
                let wtDeclMissingCount = 0;
                let lMissingCount = 0;
                let wMissingCount = 0;
                let hMissingCount = 0;
                tableData.forEach(item => {
                    if (item.Parent) parentSet.add(item.Parent);
                    if (item.SKU && !String(item.SKU).toUpperCase().includes('PARENT'))
                        skuCount++;
                    
                    // Skip parent SKUs when counting missing data
                    const isParentSku = item.SKU && String(item.SKU).toUpperCase().includes('PARENT');
                    if (isParentSku) {
                        return; // Skip parent SKUs
                    }
                    
                    // Count missing data for each column (only for child SKUs)
                    if (isMissing(item.wt_act_kg)) wtActKgMissingCount++;
                    if (isMissing(item.wt_act)) wtActMissingCount++;
                    if (isMissing(item.wt_decl)) wtDeclMissingCount++;
                    if (isMissing(item.l)) lMissingCount++;
                    if (isMissing(item.w)) wMissingCount++;
                    if (isMissing(item.h)) hMissingCount++;
                });
                
                const setHeaderCount = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = `(${val})`; };
                setHeaderCount('parentCount', parentSet.size);
                setHeaderCount('skuCount', skuCount);
                setHeaderCount('wtActKgMissingCount', wtActKgMissingCount);
                setHeaderCount('wtActMissingCount', wtActMissingCount);
                setHeaderCount('wtDeclMissingCount', wtDeclMissingCount);
                setHeaderCount('lMissingCount', lMissingCount);
                setHeaderCount('wMissingCount', wMissingCount);
                setHeaderCount('hMissingCount', hMissingCount);
            }

            function refreshProductPlaybackState() {
                productUniqueParents = [...new Set((tableData || []).map(item => item.Parent).filter(Boolean))];
                updateProductButtonStates();
            }

            function setupProductPlaybackListeners() {
                const playBackward = document.getElementById('play-backward');
                const playForward = document.getElementById('play-forward');
                const playPause = document.getElementById('play-pause');
                const playAuto = document.getElementById('play-auto');
                if (playBackward) playBackward.addEventListener('click', productPreviousParent);
                if (playForward) playForward.addEventListener('click', productNextParent);
                if (playPause) playPause.addEventListener('click', productStopNavigation);
                if (playAuto) playAuto.addEventListener('click', productStartNavigation);
            }

            function productStartNavigation() {
                if (productUniqueParents.length === 0) return;
                isProductNavigationActive = true;
                currentProductParentIndex = 0;
                showCurrentProductParent();
                const playPause = document.getElementById('play-pause');
                const playAuto = document.getElementById('play-auto');
                if (playAuto) { playAuto.style.display = 'none'; }
                if (playPause) { playPause.style.display = 'inline-flex'; playPause.classList.remove('btn-light'); }
                updateProductButtonStates();
            }

            function productStopNavigation() {
                isProductNavigationActive = false;
                currentProductParentIndex = -1;
                const playPause = document.getElementById('play-pause');
                const playAuto = document.getElementById('play-auto');
                if (playPause) { playPause.style.display = 'none'; }
                if (playAuto) { playAuto.style.display = 'inline-flex'; playAuto.classList.remove('btn-success', 'btn-warning', 'btn-danger'); playAuto.classList.add('btn-light'); }
                applyFilters();
                updateProductButtonStates();
            }

            function productNextParent() {
                if (!isProductNavigationActive) return;
                if (currentProductParentIndex >= productUniqueParents.length - 1) return;
                currentProductParentIndex++;
                showCurrentProductParent();
            }

            function productPreviousParent() {
                if (!isProductNavigationActive) return;
                if (currentProductParentIndex <= 0) return;
                currentProductParentIndex--;
                showCurrentProductParent();
            }

            function showCurrentProductParent() {
                if (!isProductNavigationActive || currentProductParentIndex === -1) return;
                const currentParent = productUniqueParents[currentProductParentIndex];
                filteredData = tableData.filter(item => item.Parent === currentParent && matchesRowTypeFilter(item));
                applyCurrentSort();
                renderTable(filteredData);
                updateProductButtonStates();
            }

            function updateProductButtonStates() {
                const playBackward = document.getElementById('play-backward');
                const playForward = document.getElementById('play-forward');
                const playAuto = document.getElementById('play-auto');
                if (playBackward) {
                    playBackward.disabled = !isProductNavigationActive || currentProductParentIndex <= 0;
                    playBackward.classList.toggle('btn-primary', isProductNavigationActive);
                    playBackward.classList.toggle('btn-light', !isProductNavigationActive);
                }
                if (playForward) {
                    playForward.disabled = !isProductNavigationActive || currentProductParentIndex >= productUniqueParents.length - 1;
                    playForward.classList.toggle('btn-primary', isProductNavigationActive);
                    playForward.classList.toggle('btn-light', !isProductNavigationActive);
                }
                if (playAuto) playAuto.title = isProductNavigationActive ? 'Show all' : 'Start parent navigation';
            }

            // Check if value is missing (null, undefined, empty, or 0)
            function isMissing(value) {
                return value === null || value === undefined || value === '' || value === 0 || parseFloat(value) === 0;
            }

            /** Match ProductMaster SKU to checkbox / attribute (NBSP vs space, trim). */
            function normalizeSkuKey(s) {
                if (s == null) return '';
                return String(s).replace(/\u00a0/g, ' ').trim();
            }

            function findProductByRowRef(checkbox) {
                const id = checkbox.getAttribute('data-id');
                if (id != null && id !== '') {
                    const row = tableData.find(d => String(d.id) === String(id));
                    if (row) return row;
                }
                const sku = checkbox.getAttribute('data-sku');
                if (!sku) return null;
                const key = normalizeSkuKey(sku);
                return tableData.find(d => normalizeSkuKey(d.SKU) === key) || null;
            }

            function isParentSkuItem(item) {
                return !!(item.SKU && String(item.SKU).toUpperCase().includes('PARENT'));
            }

            /** Rows filter: all | parent | sku (default sku = child rows only). */
            function matchesRowTypeFilter(item) {
                const el = document.getElementById('shippingRowTypeFilter');
                const mode = el ? el.value : 'sku';
                if (mode === 'sku') return !isParentSkuItem(item);
                if (mode === 'parent') return isParentSkuItem(item);
                return true;
            }

            function marketplaceShipFieldStrictBlank(v) {
                return v === null || v === undefined || v === '' || (typeof v === 'string' && v.trim() === '');
            }

            function roundCarrierShipValue(n) {
                return Math.round(n * 100) / 100;
            }

            /** Numeric value shown in a carrier ship cell (matches displayed text, includes 0). */
            function carrierShipNumericFromCell(td) {
                const text = (td.textContent || '').trim();
                if (text === '-' || text === '') return null;
                const n = parseFloat(text.replace(/,/g, ''));
                return Number.isFinite(n) ? roundCarrierShipValue(n) : null;
            }

            function parseComparableCarrierShipValue(raw) {
                if (raw === null || raw === undefined || raw === '') return null;
                const n = parseFloat(String(raw).replace(/,/g, ''));
                if (!Number.isFinite(n)) return null;
                return roundCarrierShipValue(n);
            }

            const CARRIER_SHIP_RANK_STYLES = [
                { cls: 'shipping-rate-low', color: '#198754' },
                { cls: 'shipping-rate-low-2', color: '#0d6efd' },
                { cls: 'shipping-rate-low-3', color: '#ca8a04' }
            ];

            function applyCarrierShipRankStyle(entry, style) {
                entry.td.classList.add(style.cls);
                entry.td.style.color = style.color;
            }

            function highlightCarrierShipMinMax(entries) {
                if (!entries || entries.length === 0) return;
                if (entries.length === 1) {
                    applyCarrierShipRankStyle(entries[0], { cls: 'shipping-rate-high', color: '#dc3545' });
                    return;
                }
                const max = Math.max(...entries.map(e => e.value));
                const uniqueAsc = [...new Set(entries.map(e => e.value))].sort((a, b) => a - b);
                if (uniqueAsc.length === 1) return;

                entries.forEach(e => {
                    if (e.value === max) {
                        applyCarrierShipRankStyle(e, { cls: 'shipping-rate-high', color: '#dc3545' });
                    }
                });

                for (let i = 0; i < Math.min(3, uniqueAsc.length); i++) {
                    const rankValue = uniqueAsc[i];
                    if (rankValue === max) continue;
                    const style = CARRIER_SHIP_RANK_STYLES[i];
                    entries.forEach(e => {
                        if (e.value === rankValue) applyCarrierShipRankStyle(e, style);
                    });
                }
            }

            /** Same rules as setShippingNumericCell: parent shows "-" when empty/0; child "-" when blank/invalid, "0.00" when zero. */
            function marketplaceShipDisplayKind(raw, isParent) {
                const blank = marketplaceShipFieldStrictBlank(raw);
                const n = parseFloat(raw);
                const finite = Number.isFinite(n);
                if (isParent) {
                    if (blank || !finite || n === 0) return 'dash';
                    return 'value';
                }
                if (blank || !finite) return 'dash';
                if (n === 0) return 'zero';
                return 'value';
            }

            function matchesMarketplaceShipColFilter(item, fieldName, mode) {
                if (!mode || mode === 'all') return true;
                const isP = isParentSkuItem(item);
                // Filter against the same value the outer cell shows (slab rate).
                const raw = (typeof getOuterCarrierDisplayRate === 'function')
                    ? getOuterCarrierDisplayRate(item, fieldName, isP)
                    : item[fieldName];
                const kind = marketplaceShipDisplayKind(raw, isP);
                if (mode === 'zero') return kind === 'zero';
                if (mode === 'dash') return kind === 'dash';
                if (mode === 'missing') return kind !== 'value';
                return true;
            }

            /** OZ → LB (16 oz = 1 lb), rounded to 2 decimals — matches conversion table. */
            function wtActOzToLb(oz) {
                return Math.round((oz / 16) * 100) / 100;
            }

            const KG_TO_LB = 2.2046226218;

            /** Item Weight ACT → lb: use WT ACT (lb), else convert Kg to lb. */
            function itemWeightActLbResolved(item) {
                const lb = parseFloat(item.wt_act);
                if (Number.isFinite(lb) && lb > 0) {
                    return Math.round(lb * 100) / 100;
                }
                const kg = parseFloat(item.wt_act_kg);
                if (Number.isFinite(kg) && kg > 0) {
                    return Math.round(kg * KG_TO_LB * 100) / 100;
                }
                return null;
            }

            function itemWeightActOzFromLb(lb) {
                if (lb === null || !Number.isFinite(lb)) return null;
                return Math.round(lb * 16 * 100) / 100;
            }

            function itemWeightActMissing(item) {
                return isMissing(item.wt_act) && isMissing(item.wt_act_kg);
            }

            /** Below 1 lb → ounces with OZ; 1 lb and above → pounds with LB. */
            function itemWeightActDisplay(item, isParentRow) {
                if (isParentRow) return '--';
                if (itemWeightActMissing(item)) return '-';
                const lb = itemWeightActLbResolved(item);
                if (lb === null) return '-';
                if (lb < 1) {
                    const oz = itemWeightActOzFromLb(lb);
                    return oz === null ? '-' : `${formatNumber(oz, 1)} OZ`;
                }
                return `${formatNumber(lb, 1)} LB`;
            }

            /** Decl field value, falling back to ACT when Decl is empty (first-time copy). */
            function itemDeclValue(item, declKey, actKey) {
                if (!isMissing(item[declKey])) return item[declKey];
                return item[actKey];
            }

            function itemWeightDeclLbResolved(item) {
                const declLb = parseFloat(item.wt_decl);
                if (Number.isFinite(declLb) && declLb > 0) {
                    return Math.round(declLb * 100) / 100;
                }
                return itemWeightActLbResolved(item);
            }

            /** 1–15 oz upper limits (lb) from conversion table. */
            const WT_ACT_OZ_LB_UPPER = [0.06, 0.13, 0.19, 0.25, 0.31, 0.38, 0.44, 0.50, 0.56, 0.63, 0.69, 0.75, 0.81, 0.88, 0.94];

            /** Oz bands shown in Item WT ACT (LB) filter dropdown. */
            const WT_ACT_OZ_FILTER_OPTIONS = [2, 4, 6, 12];

            /** Custom oz slab ranges (oz min/max); others use adjacent table limits. */
            const WT_ACT_OZ_FILTER_SLABS = {
                2: { ozMin: 0.01, ozMax: 2, label: '0.01–2 oz (0.01 – 0.125 lb)' },
                4: { ozMin: 2.01, ozMax: 4, label: '2.01–4 oz (0.126 – 0.25 lb)' },
                6: { ozMin: 4.01, ozMax: 8, label: '4.01–8 oz (0.251 – 0.5 lb)' },
                12: { ozMin: 8.01, ozMax: 12, label: '8.01–12 oz (0.51 – 0.75 lb)' },
            };

            const WT_ACT_OZ_1599_SLAB = { ozMin: 12.01, ozMax: 15.99, label: '12.01–15.99 oz (0.751 – 1 lb)' };

            /** Oz slab ceilings used when rounding Declared Weight up to the next slab. */
            const WT_ACT_DECL_OZ_SLAB_CAPS = [2, 4, 8, 12, 15.99];

            function wtActOzFilterSlabBounds(oz) {
                const custom = WT_ACT_OZ_FILTER_SLABS[oz];
                if (custom) {
                    return {
                        ozMin: custom.ozMin,
                        ozMax: custom.ozMax,
                        lbMin: custom.ozMin === 0.01 ? 0.01 : custom.ozMin / 16,
                        lbMax: custom.ozMax / 16,
                    };
                }
                return {
                    ozMin: oz - 1,
                    ozMax: oz,
                    lbMin: WT_ACT_OZ_LB_UPPER[oz - 2],
                    lbMax: WT_ACT_OZ_LB_UPPER[oz - 1],
                };
            }

            function wtActOzFilterSlabLabel(oz) {
                const custom = WT_ACT_OZ_FILTER_SLABS[oz];
                if (custom && custom.label) return custom.label;
                const b = wtActOzFilterSlabBounds(oz);
                return `${b.ozMin}–${b.ozMax} oz (${wtActOzToLb(b.ozMin)} – ${wtActOzToLb(b.ozMax)} lb)`;
            }

            function wtActOz1599SlabLabel() {
                const s = WT_ACT_OZ_1599_SLAB;
                if (s.label) return s.label;
                return `${s.ozMin}–${s.ozMax} oz (${wtActOzToLb(s.ozMin)} – ${wtActOzToLb(s.ozMax)} lb)`;
            }

            /** Upward bands (lb); labels show oz range + converted lb unless `label` is set. */
            const WT_ACT_UPWARD_LB_BANDS = [
                { key: 'lb_101_2', lbMin: 1, lbMax: 2, label: '1 lb – 2 lb' },
                { key: 'lb_201_3', lbMin: 2.01, lbMax: 3, label: '2.01 lb – 3 lb' },
                { key: 'lb_301_4', lbMin: 3.01, lbMax: 4, label: '3.01 lb – 4 lb' },
                { key: 'lb_401_5', lbMin: 4.01, lbMax: 5, label: '4.01 lb – 5 lb' },
                { key: 'lb_501_6', lbMin: 5.01, lbMax: 6, label: '5.01 lb – 6 lb' },
                { key: 'lb_601_7', lbMin: 6.01, lbMax: 7, label: '6.01 lb – 7 lb' },
                { key: 'lb_701_8', lbMin: 7.01, lbMax: 8, label: '7.01 lb – 8 lb' },
                { key: 'lb_801_9', lbMin: 8.01, lbMax: 9, label: '8.01 lb – 9 lb' },
                { key: 'lb_901_10', lbMin: 9.01, lbMax: 10, label: '9.01 lb – 10 lb' },
                { key: 'lb_1001_11', lbMin: 10.01, lbMax: 11, label: '10.01 lb – 11 lb' },
                { key: 'lb_1101_12', lbMin: 11.01, lbMax: 12, label: '11.01 lb – 12 lb' },
                { key: 'lb_1201_13', lbMin: 12.01, lbMax: 13, label: '12.01 lb – 13 lb' },
                { key: 'lb_1301_14', lbMin: 13.01, lbMax: 14, label: '13.01 lb – 14 lb' },
                { key: 'lb_1401_20', lbMin: 14.01, lbMax: 20, label: '14.01 lb – 20 lb' },
                { key: 'lb_20_30', lbMin: 20.01, lbMax: 25, label: '20.01 lb – 25 lb' },
                { key: 'lb_2501_30', lbMin: 25.01, lbMax: 30, label: '25.01 lb – 30 lb' },
                { key: 'lb_30_40', lbMin: 30.01, lbMax: 40, label: '30.01 lb – 40 lb' },
                { key: 'lb_40_50', lbMin: 40.01, lbMax: 50, label: '40.01 lb – 50 lb' },
                { key: 'lb_gt50', lbMin: 50.01, lbMax: null, label: '> 50.01 lb' }
            ];

            /**
             * Round ACT weight UP to the billable Declared slab ceiling.
             * < 1 lb → next oz slab cap (2 / 4 / 8 / 12 / 15.99 oz).
             * ≥ 1 lb → containing upward LB band max (1.1 → 2, 14.5 → 20).
             */
            function roundWeightLbUpToSlab(lb) {
                if (lb === null || !Number.isFinite(lb) || lb <= 0) return null;

                if (lb < 1) {
                    const oz = itemWeightActOzFromLb(lb);
                    if (oz === null) return null;
                    for (let i = 0; i < WT_ACT_DECL_OZ_SLAB_CAPS.length; i++) {
                        const cap = WT_ACT_DECL_OZ_SLAB_CAPS[i];
                        if (oz <= cap + 1e-9) {
                            // Keep precision so 2 oz = 0.125 stays inside oz_2 (lbMax 0.125).
                            // 15.99 oz → ~1 lb billable → lands in the 1–2 lb slab.
                            const exact = cap / 16;
                            return cap >= 15.99 ? 1 : Math.round(exact * 10000) / 10000;
                        }
                    }
                    return 1;
                }

                for (let i = 0; i < WT_ACT_UPWARD_LB_BANDS.length; i++) {
                    const band = WT_ACT_UPWARD_LB_BANDS[i];
                    if (band.lbMax == null) {
                        if (lb >= (band.lbMin != null ? band.lbMin : 0)) {
                            return Math.ceil(lb - 1e-9);
                        }
                        continue;
                    }
                    if (lb <= band.lbMax + 1e-9) {
                        return band.lbMax;
                    }
                }

                return Math.ceil(lb - 1e-9);
            }

            /**
             * Declared LB for display / slabs:
             * prefer saved wt_decl; otherwise ACT rounded up to the billable slab.
             * (Previously always recomputed from ACT, so saved Decl looked like it never persisted.)
             */
            function itemWeightDeclLbRounded(item) {
                const declRaw = parseFloat(item?.wt_decl);
                if (Number.isFinite(declRaw) && declRaw > 0) {
                    return roundWeightLbUpToSlab(declRaw);
                }
                return roundWeightLbUpToSlab(itemWeightActLbResolved(item));
            }

            /**
             * Weight used for Slab Rates banding: Declared (billable) LB.
             * Falls back to ACT when Declared cannot be resolved.
             */
            function itemWeightForSlabLb(item) {
                const decl = itemWeightDeclLbRounded(item);
                if (decl !== null && Number.isFinite(decl) && decl > 0) return decl;
                return itemWeightActLbResolved(item);
            }

            function itemWeightDeclDisplay(item, isParentRow) {
                if (isParentRow) return '--';
                const lb = itemWeightDeclLbRounded(item);
                if (lb === null) return '-';
                if (lb < 1) {
                    const oz = itemWeightActOzFromLb(lb);
                    return oz === null ? '-' : `${formatNumber(oz, 1)} OZ`;
                }
                return `${formatNumber(lb, 1)} LB`;
            }

            function wtActLbBandOzMin(lb) {
                return Math.ceil(lb * 16 - 1e-9);
            }

            function wtActLbBandOzMax(lb) {
                return Math.floor(lb * 16 + 1e-9);
            }

            function wtActUpwardBandPrevMaxLb(index) {
                return index === 0 ? 1 : WT_ACT_UPWARD_LB_BANDS[index - 1].lbMax;
            }

            function wtActUpwardBandLabel(band, index) {
                if (band.label) return band.label;
                if (band.lbMax === null) {
                    const ozMin = Math.floor(wtActUpwardBandPrevMaxLb(index) * 16) + 1;
                    return `> ${ozMin} oz (> ${wtActOzToLb(ozMin)} lb)`;
                }
                const ozMin = Math.floor(wtActUpwardBandPrevMaxLb(index) * 16) + 1;
                const ozMax = Math.floor(band.lbMax * 16);
                return `${ozMin} oz – ${ozMax} oz (${wtActOzToLb(ozMin)} – ${wtActOzToLb(ozMax)} lb)`;
            }

            function populateWtActLbFilterOptions() {
                const sel = document.getElementById('filterWtAct');
                if (!sel) return;
                while (sel.options.length > 2) {
                    sel.remove(2);
                }
                const add = (value, label) => {
                    const o = document.createElement('option');
                    o.value = value;
                    o.textContent = label;
                    sel.appendChild(o);
                };
                add('lb_0', '0 lb');
                WT_ACT_OZ_FILTER_OPTIONS.forEach(oz => {
                    add(`oz_${oz}`, wtActOzFilterSlabLabel(oz));
                });
                add('oz_1599', wtActOz1599SlabLabel());
                WT_ACT_UPWARD_LB_BANDS.forEach((b, i) => add(b.key, wtActUpwardBandLabel(b, i)));
            }

            function matchesWtActOzLbBand(w, band) {
                if (band === 'oz_1599') {
                    const s = WT_ACT_OZ_1599_SLAB;
                    return w >= s.ozMin / 16 && w <= s.ozMax / 16;
                }
                const m = /^oz_(\d+)$/.exec(band);
                if (!m) return false;
                const oz = parseInt(m[1], 10);
                if (oz < 1 || oz > 15) return false;
                const b = wtActOzFilterSlabBounds(oz);
                if (WT_ACT_OZ_FILTER_SLABS[oz]) {
                    return w >= b.lbMin && w <= b.lbMax;
                }
                if (oz === 1) return w >= 0.01 && w <= b.lbMax;
                return w > b.lbMin && w <= b.lbMax;
            }

            function matchesWtActUpwardLbBand(w, band) {
                const idx = WT_ACT_UPWARD_LB_BANDS.findIndex(b => b.key === band);
                if (idx === -1) return false;
                const def = WT_ACT_UPWARD_LB_BANDS[idx];
                if (def.lbMax === null) {
                    const lower = def.lbMin != null ? def.lbMin : wtActUpwardBandPrevMaxLb(idx);
                    return def.lbMin != null ? w >= lower : w > lower;
                }
                if (def.lbMin != null) {
                    return w >= def.lbMin && w <= def.lbMax;
                }
                const lowerExclusive = wtActUpwardBandPrevMaxLb(idx);
                return w > lowerExclusive && w <= def.lbMax;
            }

            /** Match a resolved lb weight against a slab/filter band key. */
            function matchesLbWeightBandValue(w, band) {
                if (band === 'lb_0') {
                    return w === null || w === 0;
                }
                if (w === null || !Number.isFinite(w)) return false;
                if (band === 'oz_1599' || /^oz_\d+$/.test(band)) {
                    return matchesWtActOzLbBand(w, band);
                }
                if (WT_ACT_UPWARD_LB_BANDS.some(b => b.key === band)) {
                    return matchesWtActUpwardLbBand(w, band);
                }
                return true;
            }

            /** Item WT ACT (lb) preset bands (filterWtAct select values). */
            function matchesWtActLbBand(item, band) {
                if (!band || band === 'all') return true;
                if (band === 'missing') {
                    return itemWeightActMissing(item);
                }
                if (band === 'lb_0') {
                    const w = itemWeightActLbResolved(item);
                    return w === null || w === 0;
                }
                return matchesLbWeightBandValue(itemWeightActLbResolved(item), band);
            }

            /**
             * Slab Rates banding — uses Declared (billable) weight so outer Ship
             * lines up with the LB slab the Declared column rounds into.
             */
            function matchesSlabWeightBand(item, band) {
                if (!band || band === 'all') return true;
                if (band === 'missing' || band === 'lb_0') {
                    const act = itemWeightActLbResolved(item);
                    return act === null || act === 0;
                }
                return matchesLbWeightBandValue(itemWeightForSlabLb(item), band);
            }

            // Apply all filters
            function applyFilters() {
                const filterSTATUS = document.getElementById('filterSTATUS');
                const filterStatusValue = filterSTATUS ? filterSTATUS.value : 'all';
                const filterWtActKg = document.getElementById('filterWtActKg').value;
                const filterWtAct = document.getElementById('filterWtAct').value;
                const filterWtDecl = document.getElementById('filterWtDecl').value;
                const filterL = document.getElementById('filterL').value;
                const filterW = document.getElementById('filterW').value;
                const filterH = document.getElementById('filterH').value;
                const filterLDecl = document.getElementById('filterLDecl')?.value || 'all';
                const filterWDecl = document.getElementById('filterWDecl')?.value || 'all';
                const filterHDecl = document.getElementById('filterHDecl')?.value || 'all';
                const filterLabelQty = document.getElementById('filterLabelQty')?.value || 'all';
                const filterLabelType = document.getElementById('filterLabelType')?.value || 'all';
                const filterShipCol = document.getElementById('filterShipCol')?.value || 'all';
                const filterShipBbCol = document.getElementById('filterShipBbCol')?.value || 'all';
                const filterTtShipCol = document.getElementById('filterTtShipCol')?.value || 'all';
                const filterTemuShipCol = document.getElementById('filterTemuShipCol')?.value || 'all';
                const filterGofoCol = document.getElementById('filterGofoCol')?.value || 'all';
                const filterTemuGofoCol = document.getElementById('filterTemuGofoCol')?.value || 'all';
                const filterFedexCol = document.getElementById('filterFedexCol')?.value || 'all';
                const filterUpsCol = document.getElementById('filterUpsCol')?.value || 'all';
                const filterUspsCol = document.getElementById('filterUspsCol')?.value || 'all';
                const filterUniCol = document.getElementById('filterUniCol')?.value || 'all';
                const hasMissingDataFilter = filterStatusValue === 'missing' || filterLabelQty === 'missing' || filterWtActKg === 'missing' || filterWtAct === 'missing' || filterWtAct === 'lb_0' || filterWtDecl === 'missing' ||
                                            filterL === 'missing' || filterW === 'missing' ||
                                            filterH === 'missing' || filterLDecl === 'missing' || filterWDecl === 'missing' || filterHDecl === 'missing';

                const parentSearchVal = (document.getElementById('parentSearch')?.value || '').toLowerCase();
                const skuSearchVal = (document.getElementById('skuSearch')?.value || '').toLowerCase();
                const fbaSkuSearchVal = (document.getElementById('fbaSkuSearch')?.value || '').toLowerCase();

                filteredData = tableData.filter(item => {
                    if (!matchesRowTypeFilter(item)) return false;

                    // Exclude parent SKUs when any missing data filter is active
                    if (hasMissingDataFilter) {
                        if (isParentSkuItem(item)) return false;
                    }

                    if (parentSearchVal && !(item.Parent || '').toLowerCase().includes(parentSearchVal)) return false;
                    if (skuSearchVal && !(item.SKU || '').toLowerCase().includes(skuSearchVal)) return false;
                    if (fbaSkuSearchVal && !String(item.fba_sku || '').toLowerCase().includes(fbaSkuSearchVal)) return false;

                    // STATUS filter (same as product master: by value or missing)
                    if (filterStatusValue && filterStatusValue !== 'all') {
                        const statusVal = (item.status != null ? item.status : (item.Values && item.Values.status));
                        const raw = String(statusVal ?? '').trim();
                        if (filterStatusValue === 'missing') {
                            if (raw !== '') return false;
                        } else {
                            if (raw.toLowerCase() !== String(filterStatusValue).toLowerCase()) return false;
                        }
                    }

                    // Label Qty filter — blank and 0 both count as Missing
                    if (filterLabelQty && filterLabelQty !== 'all') {
                        const labelQtyRaw = item.label_qty ?? item['Label QTY'] ?? item.Label_QTY;
                        const blank = labelQtyRaw === null || labelQtyRaw === undefined || labelQtyRaw === '' ||
                            (typeof labelQtyRaw === 'string' && labelQtyRaw.trim() === '');
                        const num = blank ? NaN : parseInt(labelQtyRaw, 10);
                        const isLabelQtyMissing = blank || (Number.isFinite(num) && num === 0);
                        if (filterLabelQty === 'missing') {
                            if (!isLabelQtyMissing) return false;
                        } else if (filterLabelQty === '2') {
                            if (!Number.isFinite(num) || num !== 2) return false;
                        } else if (filterLabelQty === '3') {
                            if (!Number.isFinite(num) || num !== 3) return false;
                        } else if (filterLabelQty === 'has') {
                            if (isLabelQtyMissing || !Number.isFinite(num)) return false;
                        }
                    }

                    // Label Type filter
                    if (filterLabelType && filterLabelType !== 'all') {
                        if (normalizeLabelType(item.label_type) !== filterLabelType) return false;
                    }

                    // Weight ACT (Kg) filter
                    if (filterWtActKg === 'missing' && !isMissing(item.wt_act_kg)) {
                        return false;
                    }

                    // WT ACT (lb) filter — resolved lb (from lb or converted kg)
                    if (filterWtAct !== 'all' && !matchesWtActLbBand(item, filterWtAct)) {
                        return false;
                    }

                    // WT DECL filter (missing only when no Decl and no ACT fallback)
                    if (filterWtDecl === 'missing' && itemWeightDeclLbResolved(item) !== null) {
                        return false;
                    }

                    // L filter
                    if (filterL === 'missing' && !isMissing(item.l)) {
                        return false;
                    }

                    // W filter
                    if (filterW === 'missing' && !isMissing(item.w)) {
                        return false;
                    }

                    // H filter
                    if (filterH === 'missing' && !isMissing(item.h)) {
                        return false;
                    }

                    if (filterLDecl === 'missing' && !isMissing(itemDeclValue(item, 'l_decl', 'l'))) {
                        return false;
                    }

                    if (filterWDecl === 'missing' && !isMissing(itemDeclValue(item, 'w_decl', 'w'))) {
                        return false;
                    }

                    if (filterHDecl === 'missing' && !isMissing(itemDeclValue(item, 'h_decl', 'h'))) {
                        return false;
                    }

                    if (!matchesMarketplaceShipColFilter(item, 'ship', filterShipCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'ship_bb', filterShipBbCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'tt_ship', filterTtShipCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'temu_ship', filterTemuShipCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'gofo', filterGofoCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'temu_gofo', filterTemuGofoCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'fedex', filterFedexCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'ups', filterUpsCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'usps', filterUspsCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'uni', filterUniCol)) return false;

                    return true;
                });
                applyCurrentSort();
                renderTable(filteredData);
            }

            // Debounce helper for search inputs
            function debounce(fn, ms) {
                let t;
                return function() {
                    clearTimeout(t);
                    t = setTimeout(() => fn.apply(this, arguments), ms);
                };
            }

            // Click-to-sort on table headers (no sort icons shown)
            let currentSortKey = null;
            let currentSortDir = 1; // 1 = asc, -1 = desc
            let currentSortType = 'text';

            function getShippingSortValue(item, key) {
                if (key === 'status') {
                    const s = (item.status != null && item.status !== '') ? item.status : (item.Values && item.Values.status);
                    return String(s || '');
                }
                if (key === 'label_qty') {
                    return item.label_qty ?? item['Label QTY'] ?? item.Label_QTY;
                }
                if (key === 'label_type') {
                    return normalizeLabelType(item.label_type);
                }
                if (key === 'shopify_inv') {
                    return item.shopify_inv;
                }
                if (key === 'wt_act_lb') {
                    return itemWeightActLbResolved(item);
                }
                if (key === 'wt_decl_lb') {
                    return itemWeightDeclLbRounded(item);
                }
                if (key === 'l_decl') {
                    return itemDeclValue(item, 'l_decl', 'l');
                }
                if (key === 'w_decl') {
                    return itemDeclValue(item, 'w_decl', 'w');
                }
                if (key === 'h_decl') {
                    return itemDeclValue(item, 'h_decl', 'h');
                }
                if (key === 'l_cm') {
                    if (item.l_cm != null && item.l_cm !== '') return item.l_cm;
                    const inch = parseFloat(item.l);
                    return Number.isFinite(inch) ? inch * 2.54 : null;
                }
                if (key === 'w_cm') {
                    if (item.w_cm != null && item.w_cm !== '') return item.w_cm;
                    const inch = parseFloat(item.w);
                    return Number.isFinite(inch) ? inch * 2.54 : null;
                }
                if (key === 'h_cm') {
                    if (item.h_cm != null && item.h_cm !== '') return item.h_cm;
                    const inch = parseFloat(item.h);
                    return Number.isFinite(inch) ? inch * 2.54 : null;
                }
                if (key === 'ctn_cbm') {
                    return (parseFloat(item.ctn_l) || 0) * (parseFloat(item.ctn_w) || 0) * (parseFloat(item.ctn_h) || 0) / 1000000;
                }
                if (key === 'ctn_cbm_each') {
                    const cbm = (parseFloat(item.ctn_l) || 0) * (parseFloat(item.ctn_w) || 0) * (parseFloat(item.ctn_h) || 0) / 1000000;
                    const qty = parseFloat(item.ctn_qty) || 0;
                    return qty > 0 ? cbm / qty : 0;
                }
                if (key === 'verified_data') {
                    let v = item.verified_data;
                    if ((v == null) && item.Values) v = item.Values.verified_data;
                    return (v === 1 || v === true) ? 1 : 0;
                }
                return item[key];
            }

            function applyCurrentSort() {
                if (!currentSortKey || !Array.isArray(filteredData) || filteredData.length === 0) return;
                const key = currentSortKey;
                const type = currentSortType;
                const dir = currentSortDir;
                filteredData.sort((a, b) => {
                    let av = getShippingSortValue(a, key);
                    let bv = getShippingSortValue(b, key);
                    if (type === 'num') {
                        av = parseFloat(av);
                        bv = parseFloat(bv);
                        if (isNaN(av)) av = -Infinity;
                        if (isNaN(bv)) bv = -Infinity;
                        return (av - bv) * dir;
                    }
                    av = String(av || '').toLowerCase();
                    bv = String(bv || '').toLowerCase();
                    return av.localeCompare(bv) * dir;
                });
            }

            function setupSort() {
                const table = document.getElementById('dim-wt-master-datatable');
                if (!table) return;
                const ths = table.querySelectorAll('thead th');
                // Column index -> { key, type }. null / missing = not sortable.
                const sortMap = {
                    2: { key: 'Parent', type: 'text' },
                    3: { key: 'SKU', type: 'text' },
                    4: { key: 'status', type: 'text' },
                    5: { key: 'label_qty', type: 'num' },
                    6: { key: 'label_type', type: 'text' },
                    7: { key: 'shopify_inv', type: 'num' },
                    8: { key: 'ship', type: 'num' },
                    9: { key: 'ship_bb', type: 'num' },
                    10: { key: 'tt_ship', type: 'num' },
                    11: { key: 'temu_ship', type: 'num' },
                    12: { key: 'temu_gofo', type: 'num' },
                    13: { key: 'gofo', type: 'num' },
                    14: { key: 'fedex', type: 'num' },
                    15: { key: 'ups', type: 'num' },
                    16: { key: 'usps', type: 'num' },
                    17: { key: 'uni', type: 'num' },
                    18: { key: 'fba_sku', type: 'text' },
                    19: { key: 'fba_ship', type: 'num' },
                    20: { key: 'fba_manual_ship', type: 'num' },
                    21: { key: 'wt_act_kg', type: 'num' },
                    22: { key: 'wt_act_lb', type: 'num' },
                    23: { key: 'l', type: 'num' },
                    24: { key: 'w', type: 'num' },
                    25: { key: 'h', type: 'num' },
                    26: { key: 'wt_decl_lb', type: 'num' },
                    27: { key: 'l_decl', type: 'num' },
                    28: { key: 'w_decl', type: 'num' },
                    29: { key: 'h_decl', type: 'num' },
                    30: { key: 'l_cm', type: 'num' },
                    31: { key: 'w_cm', type: 'num' },
                    32: { key: 'h_cm', type: 'num' },
                    33: { key: 'ctn_l', type: 'num' },
                    34: { key: 'ctn_w', type: 'num' },
                    35: { key: 'ctn_h', type: 'num' },
                    36: { key: 'ctn_cbm', type: 'num' },
                    37: { key: 'ctn_qty', type: 'num' },
                    38: { key: 'ctn_cbm_each', type: 'num' },
                    39: { key: 'verified_data', type: 'num' },
                };

                ths.forEach((th, idx) => {
                    const cfg = sortMap[idx];
                    if (!cfg) return;
                    th.style.cursor = 'pointer';
                    th.title = (th.title ? th.title + ' — ' : '') + 'Click to sort';
                    th.addEventListener('click', function(e) {
                        // Don't sort when interacting with header filters / controls
                        if (e.target.closest('input, select, button, a, textarea, label')) return;
                        if (currentSortKey === cfg.key) {
                            currentSortDir = -currentSortDir;
                        } else {
                            currentSortKey = cfg.key;
                            currentSortType = cfg.type;
                            currentSortDir = 1;
                        }
                        applyCurrentSort();
                        renderTable(filteredData);
                    });
                });
            }

            // Setup search and filter listeners (called once at init)
            function setupSearch() {
                const parentSearch = document.getElementById('parentSearch');
                const skuSearch = document.getElementById('skuSearch');
                const fbaSkuSearch = document.getElementById('fbaSkuSearch');
                const applyFiltersDebounced = debounce(applyFilters, 180);
                if (parentSearch) parentSearch.addEventListener('input', applyFiltersDebounced);
                if (skuSearch) skuSearch.addEventListener('input', applyFiltersDebounced);
                if (fbaSkuSearch) fbaSkuSearch.addEventListener('input', applyFiltersDebounced);

                const filterSTATUSEl = document.getElementById('filterSTATUS');
                if (filterSTATUSEl) filterSTATUSEl.addEventListener('change', applyFilters);
                const filterIds = ['filterLabelQty', 'filterLabelType', 'filterWtActKg', 'filterWtAct', 'filterWtDecl', 'filterL', 'filterW', 'filterH', 'filterLDecl', 'filterWDecl', 'filterHDecl'];
                filterIds.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.addEventListener('change', applyFilters);
                });
                ['filterShipCol', 'filterShipBbCol', 'filterTtShipCol', 'filterTemuShipCol', 'filterTemuGofoCol', 'filterGofoCol', 'filterFedexCol', 'filterUpsCol', 'filterUspsCol', 'filterUniCol'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.addEventListener('change', applyFilters);
                });
                const rowTypeFilterEl = document.getElementById('shippingRowTypeFilter');
                if (rowTypeFilterEl) rowTypeFilterEl.addEventListener('change', function() {
                    if (isProductNavigationActive) {
                        showCurrentProductParent();
                    } else {
                        applyFilters();
                    }
                });
            }

            // Toast notification function
            function showToast(type, message) {
                // Remove existing toasts
                document.querySelectorAll('.custom-toast').forEach(t => t.remove());
                
                const toast = document.createElement('div');
                toast.className = `custom-toast toast align-items-center text-bg-${type} border-0 show position-fixed top-0 end-0 m-4`;
                toast.style.zIndex = 2000;
                toast.setAttribute('role', 'alert');
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                `;
                document.body.appendChild(toast);

                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 500);
                }, 3000);

                toast.querySelector('[data-bs-dismiss="toast"]').onclick = () => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 500);
                };
            }

            // Setup Excel export function
            function setupExcelExport() {
                document.getElementById('downloadExcel').addEventListener('click', function() {
                    // Columns to export (excluding Image, Action, and Parent)
                    const columns = ["SKU", "Status", "Label Qty", "Type", "INV", "Ship", "Ship BB", "TT 1 Ship", "Temu ship", "Temu GOFO", "GOFO", "Fedex", "UPS", "USPS", "UNI", "FBA SKU", "FBA ship", "FBA manual ship", "Weight ACT (Kg)", "Item WT ACT (OZ / LB)", "Length (inch)", "Width (inch)", "Height (Inch)", "Itm wt GW Decl", "Item L IN Decl", "Item W IN Decl", "Item H IN Decl", "Length (CM)", "Width (CM)", "Height (CM)", "CTN L (CM)", "CTN W (CM)", "CTN H (CM)", "CTN (CBM)", "CTN (QTY)", "CTN (CBM/Each)"];

                    // Column definitions with their data keys
                    const columnDefs = {
                        "SKU": {
                            key: "SKU"
                        },
                        "Label Qty": {
                            key: "label_qty"
                        },
                        "Type": {
                            key: "label_type"
                        },
                        "Status": {
                            key: "status"
                        },
                        "INV": {
                            key: "shopify_inv"
                        },
                        "Ship": {
                            key: "ship"
                        },
                        "Ship BB": {
                            key: "ship_bb"
                        },
                            "TT 1 Ship": {
                                key: "tt_ship"
                        },
                        "Temu ship": {
                            key: "temu_ship"
                        },
                        "Temu GOFO": {
                            key: "temu_gofo"
                        },
                        "GOFO": {
                            key: "gofo"
                        },
                        "Fedex": {
                            key: "fedex"
                        },
                        "UPS": {
                            key: "ups"
                        },
                        "USPS": {
                            key: "usps"
                        },
                        "UNI": {
                            key: "uni"
                        },
                        "FBA SKU": {
                            key: "fba_sku"
                        },
                        "FBA ship": {
                            key: "fba_ship"
                        },
                        "FBA manual ship": {
                            key: "fba_manual_ship"
                        },
                        "Weight ACT (Kg)": {
                            key: "wt_act_kg"
                        },
                        "Item WT ACT (OZ / LB)": {
                            computed: "item_weight_act"
                        },
                        "Length (inch)": {
                            key: "l"
                        },
                        "Width (inch)": {
                            key: "w"
                        },
                        "Height (Inch)": {
                            key: "h"
                        },
                        "Itm wt GW Decl": {
                            computed: "item_weight_decl"
                        },
                        "Item L IN Decl": {
                            computed: "l_decl"
                        },
                        "Item W IN Decl": {
                            computed: "w_decl"
                        },
                        "Item H IN Decl": {
                            computed: "h_decl"
                        },
                        "Length (CM)": {
                            key: "l_cm"
                        },
                        "Width (CM)": {
                            key: "w_cm"
                        },
                        "Height (CM)": {
                            key: "h_cm"
                        },
                        "CTN L (CM)": {
                            key: "ctn_l"
                        },
                        "CTN W (CM)": {
                            key: "ctn_w"
                        },
                        "CTN H (CM)": {
                            key: "ctn_h"
                        },
                        "CTN (CBM)": {
                            key: "ctn_cbm"
                        },
                        "CTN (QTY)": {
                            key: "ctn_qty"
                        },
                        "CTN (CBM/Each)": {
                            key: "ctn_cbm_each"
                        }
                    };

                    // Show loader or indicate download is in progress
                    document.getElementById('downloadExcel').innerHTML =
                        '<i class="fas fa-spinner fa-spin"></i>';
                    document.getElementById('downloadExcel').disabled = true;

                    // Use setTimeout to avoid UI freeze for large datasets
                    setTimeout(() => {
                        try {
                            // Use filteredData if available, otherwise use tableData
                            const dataToExport = filteredData.length > 0 ? filteredData : tableData;

                            // Create worksheet data array
                            const wsData = [];

                            // Add header row
                            wsData.push(columns);

                            // Add data rows - exclude parent SKUs
                            dataToExport.forEach(item => {
                                // Skip parent SKUs (SKU contains "PARENT")
                                if (item.SKU && String(item.SKU).toUpperCase().includes('PARENT')) {
                                    return;
                                }
                                
                                const row = [];
                                columns.forEach(col => {
                                    const colDef = columnDefs[col];
                                    if (colDef) {
                                        if (colDef.computed === 'item_weight_act') {
                                            const display = itemWeightActDisplay(item, false);
                                            row.push(display === '-' ? '' : display);
                                            return;
                                        }
                                        if (colDef.computed === 'item_weight_decl') {
                                            const display = itemWeightDeclDisplay(item, false);
                                            row.push(display === '-' ? '' : display);
                                            return;
                                        }
                                        if (colDef.computed === 'l_decl' || colDef.computed === 'w_decl' || colDef.computed === 'h_decl') {
                                            const actKey = colDef.computed === 'l_decl' ? 'l' : (colDef.computed === 'w_decl' ? 'w' : 'h');
                                            const v = itemDeclValue(item, colDef.computed, actKey);
                                            row.push(v === null || v === undefined || v === '' ? '' : (parseFloat(v) || 0));
                                            return;
                                        }
                                        const key = colDef.key;
                                        let value = item[key] !== undefined && item[key] !== null ? item[key] : '';

                                        if (key === 'label_type') {
                                            value = normalizeLabelType(value);
                                        }
                                        // CTN CBM: calculated as CTN L * CTN W * CTN H / 1000000
                                        if (key === 'ctn_cbm') {
                                            value = (parseFloat(item.ctn_l) || 0) * (parseFloat(item.ctn_w) || 0) * (parseFloat(item.ctn_h) || 0) / 1000000;
                                        }
                                        // CTN CBM each: calculated as CTN CBM / CTN Qty
                                        if (key === 'ctn_cbm_each') {
                                            const cbm = (parseFloat(item.ctn_l) || 0) * (parseFloat(item.ctn_w) || 0) * (parseFloat(item.ctn_h) || 0) / 1000000;
                                            const qty = parseFloat(item.ctn_qty) || 0;
                                            value = qty > 0 ? cbm / qty : 0;
                                        }
                                        // Format INV column
                                        if (key === "shopify_inv") {
                                            if (value === 0 || value === "0") {
                                                value = 0;
                                            } else if (value === null || value === undefined || value === "") {
                                                value = '';
                                            } else {
                                                value = parseFloat(value) || 0;
                                            }
                                        }
                                        else if (key === "fba_sku") {
                                            value = value !== undefined && value !== null && value !== '' ? String(value) : '';
                                        }
                                        else if (key === "wt_act" || key === "wt_decl") {
                                            value = value === '' || value === null || value === undefined ? '' : parseFloat((parseFloat(value) || 0).toFixed(2));
                                        }
                                        // Format numeric columns (WT ACT KG, L, W, H, CBM, CTN fields, etc.)
                                        else if (["wt_act_kg", "l", "w", "h", "l_cm", "w_cm", "h_cm", "ctn_l", "ctn_w", "ctn_h", "ctn_cbm", "ctn_qty", "ctn_cbm_each", "ship", "tt_ship", "temu_ship", "gofo", "temu_gofo", "fedex", "ups", "usps", "uni", "fba_ship", "fba_manual_ship"].includes(key)) {
                                            value = value === '' || value === null || value === undefined ? '' : (parseFloat(value) || 0);
                                        }

                                        row.push(value);
                                    } else {
                                        row.push('');
                                    }
                                });
                                wsData.push(row);
                            });

                            // Create workbook and worksheet
                            const wb = XLSX.utils.book_new();
                            const ws = XLSX.utils.aoa_to_sheet(wsData);

                            // Set column widths
                            const wscols = columns.map(col => {
                                // Adjust width based on column type
                                if (["SKU"].includes(col)) {
                                    return { wch: 20 }; // Wider for text columns
                                } else if (["Status"].includes(col)) {
                                    return { wch: 12 };
                                } else if (["FBA SKU", "Weight ACT (Kg)", "Item WT ACT (OZ / LB)", "Itm wt GW Decl", "Length (inch)", "Width (inch)", "Height (Inch)", "Item L IN Decl", "Item W IN Decl", "Item H IN Decl", "Length (CM)", "Width (CM)", "Height (CM)", "CTN (CBM)", "CTN (CBM/Each)", "Ship", "Ship BB", "TT 1 Ship", "Temu ship", "Temu GOFO", "GOFO", "Fedex", "UPS", "USPS", "UNI", "FBA ship", "FBA manual ship"].includes(col)) {
                                    return { wch: 15 }; // Width for weight and CBM columns
                                } else {
                                    return { wch: 12 }; // Default width for numeric columns
                                }
                            });
                            ws['!cols'] = wscols;

                            // Style the header row
                            const headerRange = XLSX.utils.decode_range(ws['!ref']);
                            for (let C = headerRange.s.c; C <= headerRange.e.c; ++C) {
                                const cell = XLSX.utils.encode_cell({
                                    r: 0,
                                    c: C
                                });
                                if (!ws[cell]) continue;

                                // Add header style
                                ws[cell].s = {
                                    fill: {
                                        fgColor: {
                                            rgb: "2C6ED5"
                                        }
                                    },
                                    font: {
                                        bold: true,
                                        color: {
                                            rgb: "FFFFFF"
                                        }
                                    },
                                    alignment: {
                                        horizontal: "center"
                                    }
                                };
                            }

                            // Add the worksheet to the workbook
                            XLSX.utils.book_append_sheet(wb, ws, "Shipping Master");

                            // Generate Excel file and trigger download
                            XLSX.writeFile(wb, "shipping_master_export.xlsx");

                            // Show success toast
                            showToast('success', 'Excel file downloaded successfully!');
                        } catch (error) {
                            console.error("Excel export error:", error);
                            showToast('danger', 'Failed to export Excel file.');
                        } finally {
                            // Reset button state
                            document.getElementById('downloadExcel').innerHTML =
                                '<i class="fas fa-download"></i>';
                            document.getElementById('downloadExcel').disabled = false;
                        }
                    }, 100); // Small timeout to allow UI to update
                });
            }

            // Setup import functionality
            function setupImport() {
                const importFile = document.getElementById('importFile');
                const importBtn = document.getElementById('importBtn');
                const downloadSampleBtn = document.getElementById('downloadSampleBtn');
                const importModal = document.getElementById('importExcelModal');
                const fileError = document.getElementById('fileError');
                const importProgress = document.getElementById('importProgress');
                const importResult = document.getElementById('importResult');

                // Enable/disable import button based on file selection
                importFile.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        const file = this.files[0];
                        const fileName = file.name.toLowerCase();
                        const validExtensions = ['.xlsx', '.xls', '.csv'];
                        const isValid = validExtensions.some(ext => fileName.endsWith(ext));

                        if (isValid) {
                            importBtn.disabled = false;
                            fileError.style.display = 'none';
                        } else {
                            importBtn.disabled = true;
                            fileError.textContent = 'Please select a valid Excel file (.xlsx, .xls, or .csv)';
                            fileError.style.display = 'block';
                        }
                    } else {
                        importBtn.disabled = true;
                    }
                });

                // Download sample file
                downloadSampleBtn.addEventListener('click', function() {
                    // Create sample data with all columns
                    const sampleData = [
                        ['SKU', 'Ship', 'Ship BB', 'TT 1 Ship', 'Temu ship', 'GOFO', 'Fedex', 'UPS', 'USPS', 'UNI', 'Weight ACT (Kg)', 'WT ACT (LB)', 'WT DECL (LB)', 'Length (inch)', 'Width (inch)', 'Height (Inch)', 'Length (CM)', 'Width (CM)', 'Height (CM)', 'CTN L (CM)', 'CTN W (CM)', 'CTN H (CM)', 'CTN (CBM)', 'CTN (QTY)', 'CTN (CBM/Each)'],
                        ['SKU001', '3.25', '3.10', '2.95', '3.15', '1.50', '4.20', '3.90', '2.80', '3.10', '6.2', '1.5', '1.2', '10.5', '8.3', '5.2', '26.67', '21.08', '13.21', '30', '25', '20', '0.015', '12', '0.00125'],
                        ['SKU002', '4.10', '3.95', '3.80', '4.00', '2.00', '5.10', '4.75', '3.50', '4.00', '9.1', '2.0', '1.8', '12.0', '9.0', '6.0', '30.48', '22.86', '15.24', '35', '28', '22', '0.0216', '15', '0.00144'],
                        ['SKU003', '2.80', '2.65', '2.60', '2.70', '1.20', '3.50', '3.20', '2.40', '2.70', '5.4', '1.2', '1.0', '9.5', '7.5', '4.5', '24.13', '19.05', '11.43', '28', '24', '18', '0.0121', '10', '0.00121']
                    ];

                    // Create workbook
                    const wb = XLSX.utils.book_new();
                    const ws = XLSX.utils.aoa_to_sheet(sampleData);

                    // Set column widths
                    ws['!cols'] = [
                        { wch: 15 }, // SKU
                        { wch: 12 }, // Ship
                        { wch: 12 }, // Ship BB
                        { wch: 12 }, // TT 1 Ship
                        { wch: 12 }, // Temu ship
                        { wch: 10 }, // GOFO
                        { wch: 10 }, // Fedex
                        { wch: 10 }, // UPS
                        { wch: 10 }, // USPS
                        { wch: 10 }, // UNI
                        { wch: 16 }, // Weight ACT (Kg)
                        { wch: 14 }, // WT ACT (LB)
                        { wch: 14 }, // WT DECL (LB)
                        { wch: 14 }, // Length (inch)
                        { wch: 12 }, // Width (inch)
                        { wch: 14 }, // Height (Inch)
                        { wch: 12 }, // Length (CM)
                        { wch: 12 }, // Width (CM)
                        { wch: 12 }, // Height (CM)
                        { wch: 14 }, // CTN L (CM)
                        { wch: 14 }, // CTN W (CM)
                        { wch: 14 }, // CTN H (CM)
                        { wch: 15 }, // CTN (CBM)
                        { wch: 12 }, // CTN (QTY)
                        { wch: 18 }  // CTN (CBM/Each)
                    ];

                    // Style header row
                    const headerRange = XLSX.utils.decode_range(ws['!ref']);
                    for (let C = headerRange.s.c; C <= headerRange.e.c; ++C) {
                        const cell = XLSX.utils.encode_cell({ r: 0, c: C });
                        if (!ws[cell]) continue;
                        ws[cell].s = {
                            fill: { fgColor: { rgb: "2C6ED5" } },
                            font: { bold: true, color: { rgb: "FFFFFF" } },
                            alignment: { horizontal: "center" }
                        };
                    }

                    XLSX.utils.book_append_sheet(wb, ws, "Shipping Master Sample");
                    XLSX.writeFile(wb, "shipping_master_sample.xlsx");
                    
                    showToast('success', 'Sample file downloaded successfully!');
                });

                // Handle import
                importBtn.addEventListener('click', async function() {
                    const file = importFile.files[0];
                    if (!file) {
                        showToast('danger', 'Please select a file to import');
                        return;
                    }

                    // Disable button and show progress
                    importBtn.disabled = true;
                    importProgress.style.display = 'block';
                    importResult.style.display = 'none';
                    fileError.style.display = 'none';

                    const formData = new FormData();
                    formData.append('excel_file', file);
                    formData.append('_token', csrfToken);

                    try {
                        const response = await fetch('/dim-wt-master/import', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: formData
                            
                        });
 
                        const result = await response.json();

                        // Update progress bar
                        const progressBar = importProgress.querySelector('.progress-bar');
                        progressBar.style.width = '100%';

                        if (response.ok && result.success) {
                            importResult.className = 'alert alert-success';
                            importResult.innerHTML = `
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Import Successful!</strong><br>
                                ${result.message || `Successfully imported ${result.imported || 0} records.`}
                                ${result.errors && result.errors.length > 0 ? `<br><small>Errors: ${result.errors.length}</small>` : ''}
                            `;
                            importResult.style.display = 'block';

                            // Reload data after successful import
                            setTimeout(() => {
                                loadData();
                                // Close modal after a delay
                                setTimeout(() => {
                                    const modal = bootstrap.Modal.getInstance(importModal);
                                    if (modal) modal.hide();
                                    // Reset form
                                    importFile.value = '';
                                    importBtn.disabled = true;
                                    importProgress.style.display = 'none';
                                    importResult.style.display = 'none';
                                    progressBar.style.width = '0%';
                                }, 2000);
                            }, 1000);
                        } else {
                            importResult.className = 'alert alert-danger';
                            importResult.innerHTML = `
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Import Failed!</strong><br>
                                ${result.message || 'An error occurred during import.'}
                            `;
                            importResult.style.display = 'block';
                            importBtn.disabled = false;
                        }
                    } catch (error) {
                        console.error('Import error:', error);
                        importResult.className = 'alert alert-danger';
                        importResult.innerHTML = `
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Import Failed!</strong><br>
                            ${error.message || 'An error occurred during import.'}
                        `;
                        importResult.style.display = 'block';
                        importBtn.disabled = false;
                    } finally {
                        // Reset progress bar after a delay
                        setTimeout(() => {
                            const progressBar = importProgress.querySelector('.progress-bar');
                            progressBar.style.width = '0%';
                        }, 2000);
                    }
                });

                // Reset form when modal is closed
                importModal.addEventListener('hidden.bs.modal', function() {
                    importFile.value = '';
                    importBtn.disabled = true;
                    importProgress.style.display = 'none';
                    importResult.style.display = 'none';
                    fileError.style.display = 'none';
                    const progressBar = importProgress.querySelector('.progress-bar');
                    if (progressBar) progressBar.style.width = '0%';
                });
            }

            // Select All checkbox functionality
            function setupSelectAll() {
                const selectAllCheckbox = document.getElementById('selectAll');
                selectAllCheckbox.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('.row-checkbox');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = selectAllCheckbox.checked;
                    });
                    updatePushButtonState();
                });
            }

            // Update Push Button State
            function updatePushButtonState() {
                const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
                const pushBtn = document.getElementById('pushDataBtn');
                if (checkedBoxes.length > 0) {
                    pushBtn.disabled = false;
                    pushBtn.innerHTML = `<i class="fas fa-cloud-upload-alt me-1"></i> Push Data (${checkedBoxes.length})`;
                } else {
                    pushBtn.disabled = true;
                    pushBtn.innerHTML = '<i class="fas fa-cloud-upload-alt me-1"></i> Push Data';
                }
            }

            /** Checked non-parent products (for bulk edit via Action column pencil). */
            function getSelectedNonParentProducts() {
                const selected = [];
                const seenIds = new Set();
                document.querySelectorAll('.row-checkbox:checked').forEach(checkbox => {
                    const sku = checkbox.getAttribute('data-sku');
                    if (!sku || String(sku).toUpperCase().includes('PARENT')) return;
                    const item = findProductByRowRef(checkbox);
                    if (!item) return;
                    const idKey = item.id != null ? String(item.id) : ('sku:' + normalizeSkuKey(item.SKU));
                    if (seenIds.has(idKey)) return;
                    seenIds.add(idKey);
                    selected.push(item);
                });
                return selected;
            }

            // Push Data functionality
            function setupPushData() {
                document.getElementById('pushDataBtn').addEventListener('click', async function() {
                    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
                    
                    if (checkedBoxes.length === 0) {
                        showToast('warning', 'Please select at least one SKU to push data');
                        return;
                    }

                    // Get selected SKUs and their data
                    const selectedSkus = [];
                    checkedBoxes.forEach(checkbox => {
                        const item = findProductByRowRef(checkbox);
                        if (item && item.SKU) {
                            selectedSkus.push({
                                sku: item.SKU,
                                id: item.id,
                                wt_act_kg: item.wt_act_kg || null,
                                wt_act: item.wt_act || null,
                                wt_decl: (() => {
                                    const d = itemWeightDeclLbRounded(item);
                                    return d != null ? d : null;
                                })(),
                                l: item.l || null,
                                w: item.w || null,
                                h: item.h || null,
                                l_decl: item.l_decl || item.l || null,
                                w_decl: item.w_decl || item.w || null,
                                h_decl: item.h_decl || item.h || null,
                                l_cm: item.l_cm || null,
                                w_cm: item.w_cm || null,
                                h_cm: item.h_cm || null
                            });
                        }
                    });

                    if (selectedSkus.length === 0) {
                        showToast('warning', 'No valid SKUs found to push');
                        return;
                    }

                    // Confirm action with details
                    const skuList = selectedSkus.map(s => s.sku).join(', ');
                    const confirmMessage = `Are you sure you want to push Shipping Master data (dimensions & weight) for ${selectedSkus.length} SKU(s) to ALL marketplaces?\n\n` +
                        `Selected SKUs: ${skuList.substring(0, 100)}${skuList.length > 100 ? '...' : ''}\n\n` +
                        `Data to be updated:\n` +
                        `- Weight (Weight ACT (Kg), WT ACT (LB), WT DECL (LB))\n` +
                        `- Dimensions (Length/Width/Height in inch and CM)\n\n` +
                        `This will update the data in: Amazon, eBay, Shopify, Walmart, Doba, Temu, and all other connected marketplaces.`;
                    
                    if (!confirm(confirmMessage)) {
                        return;
                    }

                    const pushBtn = document.getElementById('pushDataBtn');
                    const originalText = pushBtn.innerHTML;
                    
                    try {
                        pushBtn.disabled = true;
                        pushBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Pushing...';
                        
                        const response = await makeRequest('/dim-wt-master/push-data', 'POST', {
                            skus: selectedSkus
                        });

                        const data = await response.json();
                        
                        if (!response.ok) {
                            throw new Error(data.message || 'Failed to push data');
                        }

                        // Build detailed success message
                        let messageHtml = `<div class="mb-3">`;
                        messageHtml += `<p class="mb-2"><strong><i class="fas fa-database text-info me-2"></i>Data saved to database for ${selectedSkus.length} SKU(s).</strong></p>`;
                        
                        if (data.results) {
                            const implementedPlatforms = ['amazon', 'shopify', 'ebay', 'ebay2', 'ebay3', 'walmart'];
                            const hasSuccess = Object.values(data.results).some(r => r.success > 0);
                            const hasFailures = Object.values(data.results).some(r => r.failed > 0);
                            
                            messageHtml += `<div class="mt-3">`;
                            messageHtml += `<p class="mb-2"><strong>Platform Update Results:</strong></p>`;
                            messageHtml += `<ul class="list-unstyled mb-0">`;
                            
                            Object.entries(data.results).forEach(([platform, result]) => {
                                const platformName = platform.charAt(0).toUpperCase() + platform.slice(1).replace(/_/g, ' ');
                                const isImplemented = implementedPlatforms.includes(platform.toLowerCase());
                                
                                if (result.success > 0) {
                                    messageHtml += `<li class="mb-1">`;
                                    messageHtml += `<i class="fas fa-check-circle text-success me-2"></i>`;
                                    messageHtml += `<strong>${platformName}:</strong> `;
                                    messageHtml += `<span class="text-success">${result.success} updated successfully</span>`;
                                    if (result.failed > 0) {
                                        messageHtml += `, <span class="text-danger">${result.failed} failed</span>`;
                                    }
                                    messageHtml += `</li>`;
                                } else if (result.failed > 0) {
                                    messageHtml += `<li class="mb-1">`;
                                    messageHtml += `<i class="fas fa-times-circle text-danger me-2"></i>`;
                                    messageHtml += `<strong>${platformName}:</strong> `;
                                    messageHtml += `<span class="text-danger">${result.failed} failed</span>`;
                                    messageHtml += `</li>`;
                                } else if (!isImplemented) {
                                    messageHtml += `<li class="mb-1 text-muted">`;
                                    messageHtml += `<i class="fas fa-clock me-2"></i>`;
                                    messageHtml += `<strong>${platformName}:</strong> API integration pending`;
                                    messageHtml += `</li>`;
                                }
                            });
                            
                            messageHtml += `</ul>`;
                            
                            if (hasSuccess) {
                                messageHtml += `<div class="alert alert-success mt-3 mb-0">`;
                                messageHtml += `<i class="fas fa-check-circle me-2"></i>`;
                                messageHtml += `<strong>Success!</strong> Shipping Master data has been updated on the marketplace platforms above.`;
                                messageHtml += `</div>`;
                            }
                            
                            if (data.errors && data.errors.length > 0) {
                                messageHtml += `<div class="alert alert-warning mt-2 mb-0">`;
                                messageHtml += `<i class="fas fa-exclamation-triangle me-2"></i>`;
                                messageHtml += `<strong>Some errors occurred:</strong>`;
                                messageHtml += `<ul class="mb-0 mt-2 small">`;
                                data.errors.slice(0, 5).forEach(error => {
                                    messageHtml += `<li>${error}</li>`;
                                });
                                if (data.errors.length > 5) {
                                    messageHtml += `<li><em>... and ${data.errors.length - 5} more errors</em></li>`;
                                }
                                messageHtml += `</ul>`;
                                messageHtml += `</div>`;
                            }
                            
                            messageHtml += `</div>`;
                        }
                        messageHtml += `</div>`;

                        // Show success modal
                        const successModal = document.getElementById('pushDataSuccessModal');
                        const messageDiv = document.getElementById('pushDataSuccessMessage');
                        messageDiv.innerHTML = messageHtml;
                        
                        const modal = new bootstrap.Modal(successModal);
                        modal.show();
                        
                        // Uncheck all checkboxes
                        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
                        document.getElementById('selectAll').checked = false;
                        updatePushButtonState();
                        
                    } catch (error) {
                        console.error('Error pushing data:', error);
                        showToast('danger', error.message || 'Failed to push data to platforms');
                    } finally {
                        pushBtn.innerHTML = originalText;
                        pushBtn.disabled = false;
                        updatePushButtonState();
                    }
                });
            }

            // Calculate CTN (CBM) = CTN L (CM) × CTN W (CM) × CTN H (CM) / 1000000
            function calculateCtnCbm(ctnL, ctnW, ctnH) {
                if (!ctnL || !ctnW || !ctnH) return 0;
                const l = parseFloat(ctnL) || 0;
                const w = parseFloat(ctnW) || 0;
                const h = parseFloat(ctnH) || 0;
                return (l * w * h) / 1000000;
            }

            // Calculate CTN (CBM/Each) = CTN (CBM) / CTN (QTY)
            function calculateCtnCbmEach(ctnCbm, ctnQty) {
                if (!ctnCbm || !ctnQty || parseFloat(ctnQty) === 0) return 0;
                const cbm = parseFloat(ctnCbm) || 0;
                const qty = parseFloat(ctnQty) || 0;
                return qty > 0 ? cbm / qty : 0;
            }

            // Edit Shipping Master (modal)
            function editDimWt(product) {
                const modalEl = document.getElementById('editDimWtModal');
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                document.getElementById('editDimWtModalLabel').textContent = (bulkEditList && bulkEditList.length > 1)
                    ? ('Bulk Edit (' + bulkEditList.length + ' items)')
                    : 'Edit Shipping Master';
                
                // Populate form fields
                document.getElementById('editProductId').value = product.id || '';
                document.getElementById('editSku').value = product.SKU || '';
                document.getElementById('editParent').value = product.Parent || '';
                const labelQtyVal = product.label_qty ?? product['Label QTY'] ?? product.Label_QTY;
                document.getElementById('editLabelQty').value =
                    (labelQtyVal !== null && labelQtyVal !== undefined && labelQtyVal !== '') ? labelQtyVal : '';
                document.getElementById('editLabelType').value = normalizeLabelType(product.label_type);
                document.getElementById('editWtActKg').value = product.wt_act_kg || '';
                document.getElementById('editWtAct').value = product.wt_act || '';
                // Decl: show saved wt_decl when present; otherwise seed from ACT rounded to slab
                const declStored = parseFloat(product.wt_decl);
                if (Number.isFinite(declStored) && declStored > 0) {
                    document.getElementById('editWtDecl').value = declStored;
                } else {
                    const declLbForEdit = roundWeightLbUpToSlab(itemWeightActLbResolved(product));
                    document.getElementById('editWtDecl').value = declLbForEdit != null ? declLbForEdit : '';
                }
                document.getElementById('editL').value = product.l || '';
                document.getElementById('editW').value = product.w || '';
                document.getElementById('editH').value = product.h || '';
                document.getElementById('editLDecl').value = product.l_decl || product.l || '';
                document.getElementById('editWDecl').value = product.w_decl || product.w || '';
                document.getElementById('editHDecl').value = product.h_decl || product.h || '';
                document.getElementById('editLCm').value = product.l_cm || '';
                document.getElementById('editWCm').value = product.w_cm || '';
                document.getElementById('editHCm').value = product.h_cm || '';
                document.getElementById('editCtnL').value = product.ctn_l || '';
                document.getElementById('editCtnW').value = product.ctn_w || '';
                document.getElementById('editCtnH').value = product.ctn_h || '';
                // Populate auto-calculated inch fields
                // Auto-populate Item CM fields from inch values if CM is missing
                const lValInch = parseFloat(product.l) || 0;
                const wValInch = parseFloat(product.w) || 0;
                const hValInch = parseFloat(product.h) || 0;
                if (!product.l_cm && lValInch) {
                    document.getElementById('editLCm').value = (lValInch * 2.54).toFixed(2);
                }
                if (!product.w_cm && wValInch) {
                    document.getElementById('editWCm').value = (wValInch * 2.54).toFixed(2);
                }
                if (!product.h_cm && hValInch) {
                    document.getElementById('editHCm').value = (hValInch * 2.54).toFixed(2);
                }

                const ctnLVal = parseFloat(product.ctn_l) || 0;
                const ctnWVal = parseFloat(product.ctn_w) || 0;
                const ctnHVal = parseFloat(product.ctn_h) || 0;
                document.getElementById('editCtnLInch').value = ctnLVal ? (ctnLVal / 2.54).toFixed(2) : '';
                document.getElementById('editCtnWInch').value = ctnWVal ? (ctnWVal / 2.54).toFixed(2) : '';
                document.getElementById('editCtnHInch').value = ctnHVal ? (ctnHVal / 2.54).toFixed(2) : '';
                document.getElementById('editCtnQty').value = product.ctn_qty || '';
                document.getElementById('editCtnWeightKg').value = product.ctn_weight_kg || '';

                const shipNum = (v) => (v !== null && v !== undefined && v !== '' && !Number.isNaN(parseFloat(v))) ? String(parseFloat(v)) : '';
                document.getElementById('editShip').value = shipNum(product.ship);
                document.getElementById('editShipBb').value = shipNum(product.ship_bb);
                document.getElementById('editTtShip').value = shipNum(product.tt_ship);
                document.getElementById('editTemuShip').value = shipNum(product.temu_ship);
                document.getElementById('editGofo').value = shipNum(product.gofo);
                document.getElementById('editTemuGofo').value = shipNum(product.temu_gofo);
                document.getElementById('editFedex').value = shipNum(product.fedex);
                document.getElementById('editUps').value = shipNum(product.ups);
                document.getElementById('editUsps').value = shipNum(product.usps);
                document.getElementById('editUni').value = shipNum(product.uni);
                document.getElementById('editFbaShip').value = shipNum(product.fba_ship);
                document.getElementById('editFbaManualShip').value = shipNum(product.fba_manual_ship);
                
                // Setup save button handler
                const saveBtn = document.getElementById('saveDimWtBtn');
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                
                newSaveBtn.addEventListener('click', async function() {
                    await saveDimWt();
                });
                
                modal.show();
            }

            // Save Shipping Master (single or bulk)
            async function saveDimWt() {
                const saveBtn = document.getElementById('saveDimWtBtn');
                if (!saveBtn) return;
                const originalText = saveBtn.innerHTML;
                const bulkTargets = (bulkEditList && bulkEditList.length > 1) ? bulkEditList.slice() : null;
                
                try {
                    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
                    saveBtn.disabled = true;
                    
                    // Calculate CTN CBM and CTN CBM/Each
                    const ctnL = parseFloat(document.getElementById('editCtnL').value) || 0;
                    const ctnW = parseFloat(document.getElementById('editCtnW').value) || 0;
                    const ctnH = parseFloat(document.getElementById('editCtnH').value) || 0;
                    const ctnQty = parseFloat(document.getElementById('editCtnQty').value) || 0;
                    const ctnCbm = calculateCtnCbm(ctnL, ctnW, ctnH);
                    const ctnCbmEach = calculateCtnCbmEach(ctnCbm, ctnQty);

                    const wtDeclRaw = document.getElementById('editWtDecl').value.trim();
                    let wtDeclSave = null;
                    if (wtDeclRaw !== '') {
                        const wtDeclNum = parseFloat(wtDeclRaw);
                        if (Number.isFinite(wtDeclNum) && wtDeclNum > 0) {
                            wtDeclSave = roundWeightLbUpToSlab(wtDeclNum);
                        }
                    }

                    const baseFormData = {
                        wt_act_kg: document.getElementById('editWtActKg').value.trim() || null,
                        wt_act: document.getElementById('editWtAct').value.trim() || null,
                        wt_decl: wtDeclSave,
                        l: document.getElementById('editL').value.trim() || null,
                        w: document.getElementById('editW').value.trim() || null,
                        h: document.getElementById('editH').value.trim() || null,
                        l_decl: document.getElementById('editLDecl').value.trim() || null,
                        w_decl: document.getElementById('editWDecl').value.trim() || null,
                        h_decl: document.getElementById('editHDecl').value.trim() || null,
                        l_cm: document.getElementById('editLCm').value.trim() || null,
                        w_cm: document.getElementById('editWCm').value.trim() || null,
                        h_cm: document.getElementById('editHCm').value.trim() || null,
                        ctn_l: document.getElementById('editCtnL').value.trim() || null,
                        ctn_w: document.getElementById('editCtnW').value.trim() || null,
                        ctn_h: document.getElementById('editCtnH').value.trim() || null,
                        ctn_cbm: ctnCbm > 0 ? ctnCbm : null,
                        ctn_qty: document.getElementById('editCtnQty').value.trim() || null,
                        ctn_cbm_each: ctnCbmEach > 0 ? ctnCbmEach : null,
                        ctn_weight_kg: document.getElementById('editCtnWeightKg').value.trim() || null,
                        ctn_weight_lb: (parseFloat(document.getElementById('editCtnWeightKg').value) || 0) * 2.21
                    };

                    const addNumericIfPresent = (inputId, propName) => {
                        const t = document.getElementById(inputId).value.trim();
                        if (t === '') return;
                        const n = parseFloat(t);
                        if (Number.isFinite(n)) baseFormData[propName] = n;
                    };
                    addNumericIfPresent('editLabelQty', 'label_qty');
                    baseFormData.label_type = normalizeLabelType(document.getElementById('editLabelType').value);
                    // Marketplace ship fields are read-only in Edit — only Slab Rates may change them.

                    const fbaShipStr = document.getElementById('editFbaShip').value.trim();
                    const fbaManualStr = document.getElementById('editFbaManualShip').value.trim();
                    if (fbaShipStr !== '' || fbaManualStr !== '') {
                        if (fbaShipStr !== '') {
                            const n = parseFloat(fbaShipStr);
                            if (Number.isFinite(n)) baseFormData.fba_ship_calculation = n;
                        }
                        if (fbaManualStr !== '') {
                            const n = parseFloat(fbaManualStr);
                            if (Number.isFinite(n)) baseFormData.fba_manual_ship = n;
                        }
                    }
                    
                    if (bulkTargets && bulkTargets.length > 0) {
                        let successCount = 0;
                        let failCount = 0;
                        for (const product of bulkTargets) {
                            const formData = {
                                ...baseFormData,
                                product_id: product.id,
                                sku: product.SKU,
                                parent: product.Parent || ''
                            };
                            try {
                                const response = await fetch('/dim-wt-master/update', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken
                                    },
                                    body: JSON.stringify(formData)
                                });
                                const data = await response.json();
                                if (response.ok) successCount++; else failCount++;
                            } catch (e) {
                                failCount++;
                            }
                        }
                        bulkEditList = null;
                        document.getElementById('editDimWtModalLabel').textContent = 'Edit Shipping Master';
                        if (failCount === 0) {
                            showToast('success', successCount + ' item(s) updated successfully!');
                        } else {
                            showToast('warning', successCount + ' updated, ' + failCount + ' failed.');
                        }
                        const modal = bootstrap.Modal.getInstance(document.getElementById('editDimWtModal'));
                        modal.hide();
                        loadData();
                        updatePushButtonState();
                        return;
                    }
                    
                    const productIdRaw = document.getElementById('editProductId').value;
                    const productId = parseInt(productIdRaw, 10);
                    if (!Number.isFinite(productId) || productId <= 0) {
                        throw new Error('Missing product id — reopen Edit and try again.');
                    }

                    const formData = {
                        ...baseFormData,
                        product_id: productId,
                        sku: document.getElementById('editSku').value,
                        parent: document.getElementById('editParent').value
                    };
                    
                    const response = await fetch('/dim-wt-master/update', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(formData)
                    });
                    
                    const data = await response.json().catch(() => ({}));
                    
                    if (!response.ok) {
                        const msg = data.message
                            || (data.errors ? Object.values(data.errors).flat().join(' ') : null)
                            || 'Failed to save data';
                        throw new Error(msg);
                    }
                    
                    showToast('success', 'Shipping Master updated successfully!');
                    
                    // Patch in-memory row so Decl/ACT show saved values immediately
                    const sku = formData.sku;
                    if (Array.isArray(tableData) && sku) {
                        const target = tableData.find(d =>
                            String(d.id) === String(productId) || String(d.SKU) === String(sku)
                        );
                        if (target) {
                            Object.keys(baseFormData).forEach(k => {
                                if (baseFormData[k] !== undefined) target[k] = baseFormData[k];
                            });
                            if (baseFormData.label_type) target.label_type = baseFormData.label_type;
                            if (baseFormData.label_qty !== undefined) target.label_qty = baseFormData.label_qty;
                        }
                    }

                    const modal = bootstrap.Modal.getInstance(document.getElementById('editDimWtModal'));
                    modal.hide();
                    
                    loadData();
                } catch (error) {
                    console.error('Error saving:', error);
                    showToast('danger', error.message || 'Failed to save data');
                } finally {
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                }
            }

            /*
             * ============================================================================
             * Shipping Master change history
             * ----------------------------------------------------------------------------
             * GET /shipping-master/history/{id} returns the per-field edit log written by
             * CategoryController::updateDimWtMaster (one row per changed field per save).
             * The History button in the Action column triggers openShippingHistoryModal().
             * ============================================================================
             */
            function shippingHistoryFmtValue(v) {
                if (v === null || v === undefined || v === '') {
                    return '<span class="shm-empty">empty</span>';
                }
                return escapeHtml(String(v));
            }

            async function openShippingHistoryModal(productId, sku) {
                const modalEl = document.getElementById('shippingHistoryModal');
                if (!modalEl) return;
                const skuLabel = document.getElementById('shippingHistorySku');
                const loadingEl = document.getElementById('shippingHistoryLoading');
                const emptyEl = document.getElementById('shippingHistoryEmpty');
                const errorEl = document.getElementById('shippingHistoryError');
                const tableWrap = document.getElementById('shippingHistoryTableWrap');
                const tbody = document.getElementById('shippingHistoryTbody');

                if (skuLabel) skuLabel.textContent = sku || '';
                if (loadingEl) loadingEl.style.display = 'block';
                if (emptyEl) emptyEl.style.display = 'none';
                if (errorEl) { errorEl.style.display = 'none'; errorEl.textContent = ''; }
                if (tableWrap) tableWrap.style.display = 'none';
                if (tbody) tbody.innerHTML = '';

                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();

                if (productId == null || productId === '') {
                    if (loadingEl) loadingEl.style.display = 'none';
                    if (errorEl) {
                        errorEl.textContent = 'This row does not have an internal id, so history cannot be loaded.';
                        errorEl.style.display = 'block';
                    }
                    return;
                }

                try {
                    const response = await fetch(`/shipping-master/history/${encodeURIComponent(productId)}`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Failed to load history.');
                    }

                    const rows = Array.isArray(data.history) ? data.history : [];
                    if (rows.length === 0) {
                        if (emptyEl) emptyEl.style.display = 'block';
                        return;
                    }

                    // Group rows by field (value-wise) but render them in one
                    // compact line per edit — the field name only appears on
                    // the first row of each group, subsequent rows show "↳".
                    const groups = new Map();
                    rows.forEach(r => {
                        const key = r.field || '';
                        if (!groups.has(key)) {
                            groups.set(key, { label: r.field_label || r.field || '', items: [] });
                        }
                        groups.get(key).items.push(r);
                    });

                    const parts = [];
                    groups.forEach((group, fieldKey) => {
                        group.items.forEach((r, idx) => {
                            const isFirst = idx === 0;
                            const isLatest = idx === 0;
                            const rowClass = isFirst ? 'shm-field-first' : 'shm-field-cont';
                            const fieldCell = isFirst
                                ? `<i class="bi bi-tag-fill shm-field-icon"></i>${escapeHtml(group.label)}`
                                : `<span style="padding-left:14px;">↳</span>`;
                            parts.push(`
                                <tr class="${rowClass}" data-field="${escapeHtml(fieldKey)}">
                                    <td class="shm-field-cell">${fieldCell}</td>
                                    <td class="shm-when">${isLatest ? '<span class="shm-latest-dot" title="latest"></span>' : ''}${escapeHtml(r.updated_at || '')}</td>
                                    <td class="shm-who"><span class="badge bg-secondary">${escapeHtml(r.updated_by || 'N/A')}</span></td>
                                    <td>
                                        <span class="shm-old">${shippingHistoryFmtValue(r.old_value)}</span>
                                        <i class="bi bi-arrow-right shm-arrow"></i>
                                        <span class="shm-new">${shippingHistoryFmtValue(r.new_value)}</span>
                                    </td>
                                </tr>
                            `);
                        });
                    });

                    tbody.innerHTML = parts.join('');
                    if (tableWrap) tableWrap.style.display = 'block';
                } catch (err) {
                    console.error('Shipping history load error:', err);
                    if (errorEl) {
                        errorEl.textContent = err.message || 'Failed to load history.';
                        errorEl.style.display = 'block';
                    }
                } finally {
                    if (loadingEl) loadingEl.style.display = 'none';
                }
            }

            // Verified column – red/green dot toggle (event delegation)
            function setupVerifiedDropdowns() {
                document.addEventListener('change', function(e) {
                    if (!e.target || !e.target.classList.contains('verified-data-dropdown')) return;
                    const dropdown = e.target;
                    const sku = dropdown.getAttribute('data-sku');
                    const isVerified = dropdown.value === '1';
                    dropdown.classList.toggle('verified', isVerified);
                    dropdown.classList.toggle('not-verified', !isVerified);
                    dropdown.title = isVerified ? 'Verified' : 'Not verified';
                    const verifiedValue = isVerified ? 1 : 0;
                    makeRequest('/product_master/update-verified', 'POST', { sku: sku, verified_data: verifiedValue })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                const product = tableData.find(d => d.SKU === sku);
                                if (product) {
                                    product.verified_data = verifiedValue;
                                    if (product.Values) product.Values.verified_data = verifiedValue;
                                    else if (!product.Values) product.Values = { verified_data: verifiedValue };
                                }
                            } else {
                                showToast('danger', data.message || 'Failed to update verified status');
                                dropdown.value = verifiedValue === 1 ? '0' : '1';
                                dropdown.classList.toggle('verified', verifiedValue === 0);
                                dropdown.classList.toggle('not-verified', verifiedValue === 1);
                            }
                        })
                        .catch(() => {
                            showToast('danger', 'Failed to update verified status');
                            dropdown.value = verifiedValue === 1 ? '0' : '1';
                            dropdown.classList.toggle('verified', verifiedValue !== 1);
                            dropdown.classList.toggle('not-verified', verifiedValue === 1);
                            dropdown.title = verifiedValue === 1 ? 'Not verified' : 'Verified';
                        });
                });
            }
            setupVerifiedDropdowns();

            // Type column – Label Type dropdown (ENV / STD / O-Size / Pallet)
            function setupLabelTypeDropdowns() {
                document.addEventListener('change', async function(e) {
                    if (!e.target || !e.target.classList.contains('label-type-dropdown')) return;
                    const dropdown = e.target;
                    const sku = dropdown.getAttribute('data-sku');
                    const productId = dropdown.getAttribute('data-id');
                    const prev = dropdown.getAttribute('data-prev') || 'STD';
                    const labelType = normalizeLabelType(dropdown.value);
                    dropdown.value = labelType;
                    applyLabelTypeColor(dropdown, labelType);
                    dropdown.disabled = true;
                    try {
                        const response = await fetch('/dim-wt-master/update', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({
                                product_id: productId ? Number(productId) : undefined,
                                sku: sku,
                                label_type: labelType
                            })
                        });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || data.success === false) {
                            throw new Error(data.message || 'Failed to update Label Type');
                        }
                        dropdown.setAttribute('data-prev', labelType);
                        const product = tableData.find(d =>
                            (productId && String(d.id) === String(productId)) || d.SKU === sku
                        );
                        if (product) {
                            product.label_type = labelType;
                            if (product.Values && typeof product.Values === 'object') {
                                product.Values.label_type = labelType;
                            }
                        }
                        showToast('success', 'Label Type updated');
                    } catch (err) {
                        const restored = normalizeLabelType(prev);
                        dropdown.value = restored;
                        applyLabelTypeColor(dropdown, restored);
                        showToast('danger', err.message || 'Failed to update Label Type');
                    } finally {
                        dropdown.disabled = false;
                    }
                });
            }
            setupLabelTypeDropdowns();

            populateWtActLbFilterOptions();

            // Initialize (search and playback listeners once to avoid duplicates on reload)
            setupSearch();
            setupSort();
            setupShippingColumnVisibility();
            setupProductPlaybackListeners();
            loadData();
            setupExcelExport();
            setupImport();
            setupSelectAll();
            setupPushData();
            setupSlabRates();
            setupMissingIndicatorClicks();
            // Reset bulk edit state when edit modal is closed (e.g. without saving)
            document.getElementById('editDimWtModal').addEventListener('hidden.bs.modal', function() {
                bulkEditList = null;
                document.getElementById('editDimWtModalLabel').textContent = 'Edit Shipping Master';
            });

            // Slab Rates: apply per-carrier rates to all SKUs in a weight slab.
            // Bands use Declared (billable) weight — ACT rounded up to the slab
            // ceiling — so outer Ship matches the LB slab Declared rounds into.

            function getSlabDefinitions() {
                const slabs = [{ key: 'lb_0', label: '0 lb' }];
                WT_ACT_OZ_FILTER_OPTIONS.forEach(oz => {
                    slabs.push({ key: `oz_${oz}`, label: wtActOzFilterSlabLabel(oz) });
                });
                slabs.push({ key: 'oz_1599', label: wtActOz1599SlabLabel() });
                WT_ACT_UPWARD_LB_BANDS.forEach((b, i) => {
                    slabs.push({ key: b.key, label: wtActUpwardBandLabel(b, i) });
                });
                return slabs;
            }

            function getNonParentItemsInSlab(slabKey) {
                if (!Array.isArray(tableData)) return [];
                return tableData.filter(item =>
                    item && !isParentSkuItem(item) && matchesSlabWeightBand(item, slabKey)
                );
            }

            /** Declared-weight slab key for one SKU (same banding as Slab Rates modal). */
            function resolveItemSlabKey(item) {
                if (!item || isParentSkuItem(item)) return null;
                const slabs = getSlabDefinitions();
                for (let i = 0; i < slabs.length; i++) {
                    if (matchesSlabWeightBand(item, slabs[i].key)) return slabs[i].key;
                }
                return null;
            }

            /**
             * Rebuild slab majority/consensus rates from current tableData.
             * Outer Ship columns read from this so they match the Slab modal.
             * There is no separate "slab rates" DB table — rates live per SKU
             * in Values.ship; the modal (and this index) are the group summary.
             */
            function rebuildSlabRateIndex() {
                const next = {};
                SLAB_RATE_CARRIERS.forEach(c => { next[c.key] = {}; });
                if (!Array.isArray(tableData) || tableData.length === 0) {
                    slabRateIndex = next;
                    return;
                }
                getSlabDefinitions().forEach(slab => {
                    const items = getNonParentItemsInSlab(slab.key);
                    if (items.length === 0) return;
                    SLAB_RATE_CARRIERS.forEach(c => {
                        const summary = computeSlabCarrierSummary(items, c.key);
                        const rate = summary.consensusValue !== null
                            ? summary.consensusValue
                            : summary.majorityValue;
                        if (rate !== null && Number.isFinite(rate)) {
                            next[c.key][slab.key] = rate;
                        }
                    });
                });
                slabRateIndex = next;
            }

            /** Rate shown on the outer table = Slab modal rate for that weight band. */
            function getOuterCarrierDisplayRate(item, carrierKey, isParentRow) {
                if (!item || isParentRow) return item ? item[carrierKey] : null;
                const slabKey = resolveItemSlabKey(item);
                if (slabKey && slabRateIndex[carrierKey] && slabRateIndex[carrierKey][slabKey] != null) {
                    return slabRateIndex[carrierKey][slabKey];
                }
                return item[carrierKey];
            }

            /** Tooltip when SKU-stored value differs from the slab rate shown. */
            function annotateOuterCarrierCell(td, item, carrierKey, isParentRow) {
                if (!td || !item || isParentRow) return;
                const slabKey = resolveItemSlabKey(item);
                const slabRate = (slabKey && slabRateIndex[carrierKey])
                    ? slabRateIndex[carrierKey][slabKey]
                    : null;
                if (slabRate == null || !Number.isFinite(slabRate)) return;
                const storedRaw = item[carrierKey];
                const stored = (storedRaw === null || storedRaw === undefined || storedRaw === '')
                    ? null
                    : parseFloat(storedRaw);
                const storedNorm = Number.isFinite(stored) ? normalizeSlabRate(stored) : null;
                const slabNorm = normalizeSlabRate(slabRate);
                if (storedNorm !== slabNorm) {
                    const storedLabel = storedNorm === null ? 'missing' : ('$' + formatSlabRate(storedNorm));
                    td.title = `Slab rate $${formatSlabRate(slabNorm)} (SKU stored ${storedLabel}). Saving to Product Master…`;
                } else {
                    td.title = `Slab rate $${formatSlabRate(slabNorm)} (saved)`;
                }
            }

            /**
             * Write slab majority/consensus rates onto each SKU's Values.{carrier}
             * so outer table AND other pages use the same saved keys.
             */
            async function syncSlabRatesToDatabase() {
                if (slabAutoSyncRunning) return;
                if (!Array.isArray(tableData) || tableData.length === 0) return;

                const perSku = new Map();
                tableData.forEach(item => {
                    if (!item || isParentSkuItem(item) || item.id == null) return;
                    const slabKey = resolveItemSlabKey(item);
                    if (!slabKey) return;

                    const fields = {};
                    SLAB_RATE_CARRIERS.forEach(c => {
                        const slabRate = slabRateIndex[c.key] ? slabRateIndex[c.key][slabKey] : null;
                        if (slabRate == null || !Number.isFinite(slabRate)) return;
                        const rate = normalizeSlabRate(slabRate);
                        if (rate === null) return;

                        const raw = item[c.key];
                        if (raw === null || raw === undefined || raw === '') {
                            fields[c.key] = rate;
                            return;
                        }
                        const n = parseFloat(raw);
                        if (!Number.isFinite(n) || normalizeSlabRate(n) !== rate) {
                            fields[c.key] = rate;
                        }
                    });

                    if (Object.keys(fields).length === 0) return;
                    perSku.set(String(item.id), { item, fields });
                });

                if (perSku.size === 0) return;

                slabAutoSyncRunning = true;
                const entries = Array.from(perSku.values());
                showToast('info', `Saving slab rates to ${entries.length} SKU(s)…`);

                let success = 0;
                let failed = 0;
                const concurrency = 6;

                for (let i = 0; i < entries.length; i += concurrency) {
                    const batch = entries.slice(i, i + concurrency);
                    const results = await Promise.all(batch.map(async ({ item, fields }) => {
                        try {
                            const response = await fetch('/dim-wt-master/update', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken
                                },
                                body: JSON.stringify({
                                    product_id: item.id,
                                    sku: item.SKU,
                                    parent: item.Parent || '',
                                    ...fields
                                })
                            });
                            if (!response.ok) return false;
                            // Keep in-memory row in sync with what we just saved
                            Object.keys(fields).forEach(k => { item[k] = fields[k]; });
                            return true;
                        } catch (e) {
                            return false;
                        }
                    }));
                    results.forEach(ok => { if (ok) success++; else failed++; });
                }

                rebuildSlabRateIndex();
                applyFilters();
                updateCounts();

                if (failed === 0) {
                    showToast('success', `Saved slab rates on ${success} SKU(s). Other pages will use these Values keys.`);
                } else {
                    showToast('warning', `Saved ${success} SKU(s), ${failed} failed.`);
                }
                slabAutoSyncRunning = false;
            }

            function isCarrierValueMissing(item, carrierKey) {
                const v = item ? item[carrierKey] : null;
                if (v === null || v === undefined || v === '') return true;
                const n = parseFloat(v);
                return !Number.isFinite(n);
            }

            // Round to 2 decimals so 5.4900 and 5.49 are treated as equal when
            // deciding whether all SKUs in a slab share the same carrier rate.
            function normalizeSlabRate(n) {
                if (!Number.isFinite(n)) return null;
                return Math.round(n * 100) / 100;
            }

            /** Summarize what the table currently holds for one (slab, carrier).
             *  - consensusValue: single value shared by all filled SKUs (missing OK).
             *  - majorityValue: most common filled value (ties → lower rate).
             *  - uniformValue: every SKU filled and same value (legacy alias of consensus with no missing).
             *  - distinctValues / filled / missing. */
            function computeSlabCarrierSummary(items, carrierKey) {
                const distinctSet = new Map(); // rounded -> count
                let filled = 0;
                let missing = 0;
                items.forEach(it => {
                    const raw = it ? it[carrierKey] : null;
                    if (raw === null || raw === undefined || raw === '') { missing++; return; }
                    const n = parseFloat(raw);
                    if (!Number.isFinite(n)) { missing++; return; }
                    filled++;
                    const r = normalizeSlabRate(n);
                    distinctSet.set(r, (distinctSet.get(r) || 0) + 1);
                });
                const distinctValues = Array.from(distinctSet.keys()).sort((a, b) => a - b);
                const consensusValue = (filled > 0 && distinctValues.length === 1)
                    ? distinctValues[0]
                    : null;
                let majorityValue = null;
                if (filled > 0) {
                    let bestCount = -1;
                    distinctValues.forEach(v => {
                        const c = distinctSet.get(v) || 0;
                        if (c > bestCount) {
                            bestCount = c;
                            majorityValue = v;
                        }
                    });
                }
                const uniformValue = (consensusValue !== null && missing === 0) ? consensusValue : null;
                return { uniformValue, consensusValue, majorityValue, distinctValues, filled, missing };
            }

            function formatSlabRate(n) {
                if (!Number.isFinite(n)) return '';
                return (Math.round(n * 100) / 100).toFixed(2);
            }

            function buildSlabRatesTableHead() {
                const headRow = document.getElementById('slabRatesHeadRow');
                if (!headRow) return;
                // Remove any previously injected carrier headers (keep first two columns)
                while (headRow.children.length > 2) headRow.removeChild(headRow.lastChild);
                SLAB_RATE_CARRIERS.forEach(c => {
                    const th = document.createElement('th');
                    th.className = 'text-center slab-rates-carrier-col';
                    th.style.fontSize = '12px';
                    th.title = `${c.label} rate ($)`;
                    th.textContent = c.label;
                    headRow.appendChild(th);
                });
            }

            function populateFillRowSlabTarget(slabs) {
                const sel = document.getElementById('slabRatesFillRowTarget');
                if (!sel) return;
                while (sel.options.length > 1) sel.remove(1);
                slabs.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.key;
                    opt.textContent = s.label;
                    sel.appendChild(opt);
                });
            }

            function buildSlabRatesTable() {
                buildSlabRatesTableHead();
                const body = document.getElementById('slabRatesBody');
                if (!body) return;
                const slabs = getSlabDefinitions();
                populateFillRowSlabTarget(slabs);
                body.innerHTML = '';
                const carrierCols = SLAB_RATE_CARRIERS.length;
                slabs.forEach(slab => {
                    const items = getNonParentItemsInSlab(slab.key);
                    const total = items.length;
                    const tr = document.createElement('tr');
                    tr.setAttribute('data-slab-key', slab.key);
                    if (total === 0) tr.classList.add('slab-row-empty');

                    const tdSlab = document.createElement('td');
                    tdSlab.className = 'slab-rates-sticky-col';
                    tdSlab.style.fontSize = '12px';
                    tdSlab.textContent = slab.label;
                    tr.appendChild(tdSlab);

                    const tdCount = document.createElement('td');
                    tdCount.className = 'text-center slab-count-cell';
                    tdCount.style.fontSize = '12px';
                    tdCount.innerHTML = `<span class="badge bg-secondary" title="${total} non-parent SKU(s) match this slab">${total}</span>`;
                    tr.appendChild(tdCount);

                    SLAB_RATE_CARRIERS.forEach(c => {
                        const td = document.createElement('td');
                        td.className = 'slab-rates-carrier-cell';
                        const summary = total === 0
                            ? { uniformValue: null, consensusValue: null, majorityValue: null, distinctValues: [], filled: 0, missing: 0 }
                            : computeSlabCarrierSummary(items, c.key);
                        const inp = document.createElement('input');
                        inp.type = 'number';
                        inp.step = '0.01';
                        inp.min = '0';
                        inp.className = 'form-control form-control-sm slab-rate-input';
                        inp.setAttribute('data-slab-key', slab.key);
                        inp.setAttribute('data-carrier-key', c.key);
                        inp.setAttribute('data-missing-count', String(summary.missing || 0));
                        if (summary.majorityValue !== null) {
                            inp.setAttribute('data-majority', formatSlabRate(summary.majorityValue));
                        }
                        inp.placeholder = '—';

                        const baseInfo = total === 0
                            ? 'No SKUs in this slab'
                            : `${c.label} — ${total} SKU(s) in slab, ${summary.missing} missing this value`;

                        if (summary.consensusValue !== null) {
                            // All filled SKUs share one value (some may still be missing).
                            const formatted = formatSlabRate(summary.consensusValue);
                            inp.value = formatted;
                            inp.setAttribute('data-original', formatted);
                            inp.classList.add('slab-rate-prefilled');
                            if (summary.missing > 0) {
                                inp.setAttribute('data-needs-missing-fill', '1');
                                inp.title = `${baseInfo}\nFilled SKUs all have ${c.label} = $${formatted} (${summary.filled} filled, ${summary.missing} missing).\nUse Apply with scope "Only missing" to fill the rest.`;
                            } else {
                                inp.title = `${baseInfo}\nAll ${total} SKU(s) currently have ${c.label} = $${formatted}.\nEdit the value to overwrite; leave as-is to skip.`;
                            }
                        } else if (summary.distinctValues.length > 0) {
                            // Mixed: at least two different filled values.
                            // Prefill majority so Apply can overwrite outliers
                            // (e.g. 44×$7 + 1×$8 → show 7; set 6 → every SKU becomes 6).
                            const sample = summary.distinctValues
                                .slice(0, 6)
                                .map(v => '$' + formatSlabRate(v))
                                .join(', ');
                            const moreCount = summary.distinctValues.length - 6;
                            const more = moreCount > 0 ? ` +${moreCount} more` : '';
                            const maj = summary.majorityValue !== null ? formatSlabRate(summary.majorityValue) : '';
                            inp.placeholder = 'mixed';
                            inp.classList.add('slab-rate-mixed');
                            inp.setAttribute('data-is-mixed', '1');
                            inp.setAttribute('data-original', '');
                            if (maj !== '') {
                                inp.value = maj;
                            }
                            inp.title = `${baseInfo}\nMIXED values: ${sample}${more}\n${summary.filled} filled, ${summary.missing} missing.`
                                + (maj ? `\nShowing majority $${maj}.` : '')
                                + `\nType the rate you want (e.g. 6), then Apply — every SKU in this LB slab gets that rate, including outliers.`;
                        } else {
                            inp.setAttribute('data-original', '');
                            inp.title = baseInfo;
                        }

                        if (total === 0) inp.disabled = true;

                        // Mark only cells the user actually typed in. Apply must
                        // not touch other carrier columns (Ship vs Temu, etc.).
                        inp.addEventListener('input', function () {
                            inp.classList.remove('slab-rate-prefilled');
                            inp.classList.remove('slab-rate-mixed');
                            inp.removeAttribute('data-needs-missing-fill');
                            inp.setAttribute('data-user-edited', '1');
                        });

                        td.appendChild(inp);
                        tr.appendChild(td);
                    });

                    body.appendChild(tr);
                });

                // Adjust the loading-placeholder colspan (now 2 + N carriers)
                const placeholder = body.querySelector('td[colspan]');
                if (placeholder) placeholder.setAttribute('colspan', String(2 + carrierCols));
            }

            function openSlabRatesModal() {
                if (!Array.isArray(tableData) || tableData.length === 0) {
                    showToast('warning', 'Data is still loading. Please try again in a moment.');
                    return;
                }
                buildSlabRatesTable();
                const progress = document.getElementById('slabRatesProgress');
                if (progress) progress.style.display = 'none';
                const modalEl = document.getElementById('slabRatesModal');
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            }

            async function applySlabRates() {
                const inputs = document.querySelectorAll('#slabRatesBody .slab-rate-input');
                const scope = (document.getElementById('slabRatesScope') || {}).value || 'all';

                // Group writes by SKU so each SKU is sent once with every relevant carrier.
                // perSku[id] = { item, fields: { carrierKey: rate, ... } }
                const perSku = new Map();
                let totalCellsApplied = 0;
                let totalWritesPlanned = 0;
                const carriersTouched = new Set();
                const slabsTouched = new Set();

                inputs.forEach(inp => {
                    // Apply writes:
                    //  1) cells the user typed
                    //  2) mixed cells (majority shown) — sync outliers so outer
                    //     Ship matches the LB slab rate without re-typing
                    //  3) consensus cells with missing SKUs — fill the gaps
                    const userEdited = inp.getAttribute('data-user-edited') === '1';
                    const isMixed = inp.getAttribute('data-is-mixed') === '1';
                    const needsMissing = inp.getAttribute('data-needs-missing-fill') === '1';
                    if (!userEdited && !isMixed && !needsMissing) return;

                    const raw = String(inp.value || '').trim();
                    if (raw === '') return;
                    const rate = parseFloat(raw);
                    if (!Number.isFinite(rate) || rate < 0) return;
                    const slabKey = inp.getAttribute('data-slab-key');
                    const carrierKey = inp.getAttribute('data-carrier-key');
                    if (!slabKey || !carrierKey) return;

                    let items = getNonParentItemsInSlab(slabKey);
                    if (scope === 'missing') {
                        items = items.filter(it => isCarrierValueMissing(it, carrierKey));
                    } else if (!userEdited && isMixed) {
                        // Untouched mixed: only rewrite SKUs that differ / are missing
                        items = items.filter(it => {
                            if (isCarrierValueMissing(it, carrierKey)) return true;
                            const n = parseFloat(it[carrierKey]);
                            return !Number.isFinite(n) || normalizeSlabRate(n) !== normalizeSlabRate(rate);
                        });
                    } else if (!userEdited && needsMissing) {
                        items = items.filter(it => isCarrierValueMissing(it, carrierKey));
                    }
                    // userEdited + scope all → every SKU in the slab
                    if (items.length === 0) return;

                    totalCellsApplied++;
                    carriersTouched.add(carrierKey);
                    slabsTouched.add(slabKey);

                    items.forEach(item => {
                        const id = String(item.id);
                        if (!perSku.has(id)) perSku.set(id, { item, fields: {} });
                        perSku.get(id).fields[carrierKey] = rate;
                        totalWritesPlanned++;
                    });
                });

                if (perSku.size === 0) {
                    showToast('warning', 'Nothing to apply. Type a rate, or Apply on mixed / partially-filled slab cells to sync outer Ship with the LB slab rate.');
                    return;
                }

                const skuList = Array.from(perSku.values()).map(v => v.item.SKU);
                const previewSkus = skuList.slice(0, 5).join(', ');
                const moreSkus = skuList.length > 5 ? `, +${skuList.length - 5} more` : '';
                const carrierLabel = Array.from(carriersTouched)
                    .map(k => (SLAB_RATE_CARRIERS.find(c => c.key === k) || {}).label || k)
                    .join(', ');
                const confirmMsg =
                    `Apply rates to ${perSku.size} SKU(s) across ${slabsTouched.size} slab(s)?\n\n` +
                    `Carriers updated: ${carrierLabel}\n` +
                    `Total cell writes: ${totalWritesPlanned}\n\n` +
                    `Sample SKUs: ${previewSkus}${moreSkus}\n\n` +
                    (scope === 'missing'
                        ? 'Scope: only SKUs missing that carrier value will be updated.'
                        : 'Scope: existing carrier values will be overwritten.');
                if (!confirm(confirmMsg)) return;

                const applyBtn = document.getElementById('slabRatesApplyBtn');
                const progressWrap = document.getElementById('slabRatesProgress');
                const progressBar = progressWrap ? progressWrap.querySelector('.progress-bar') : null;
                const progressCount = document.getElementById('slabRatesProgressCount');
                const progressLabel = document.getElementById('slabRatesProgressLabel');
                const originalText = applyBtn ? applyBtn.innerHTML : '';

                if (applyBtn) { applyBtn.disabled = true; applyBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Applying…'; }
                if (progressWrap) progressWrap.style.display = 'block';
                if (progressLabel) progressLabel.textContent = 'Applying slab rates…';

                const entries = Array.from(perSku.values());
                let success = 0;
                let failed = 0;

                for (let i = 0; i < entries.length; i++) {
                    const { item, fields } = entries[i];
                    const payload = {
                        product_id: item.id,
                        sku: item.SKU,
                        parent: item.Parent || '',
                        ...fields
                    };
                    try {
                        const response = await fetch('/dim-wt-master/update', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify(payload)
                        });
                        if (response.ok) success++; else failed++;
                    } catch (e) {
                        failed++;
                    }

                    const done = i + 1;
                    const pct = Math.round((done / entries.length) * 100);
                    if (progressBar) progressBar.style.width = pct + '%';
                    if (progressCount) progressCount.textContent = `${done} / ${entries.length}`;
                }

                if (applyBtn) { applyBtn.disabled = false; applyBtn.innerHTML = originalText; }

                if (failed === 0) {
                    showToast('success', `Applied to ${success} SKU(s) across ${slabsTouched.size} slab(s).`);
                } else {
                    showToast('warning', `${success} updated, ${failed} failed.`);
                }

                const modalEl = document.getElementById('slabRatesModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                loadData();
            }

            function fillSlabRow() {
                const valueEl = document.getElementById('slabRatesFillRow');
                const slabSel = document.getElementById('slabRatesFillRowTarget');
                if (!valueEl || !slabSel) return;
                const raw = String(valueEl.value || '').trim();
                const slabKey = slabSel.value;
                if (raw === '' || !slabKey) {
                    showToast('warning', 'Enter a value and pick a slab to fill.');
                    return;
                }
                const n = parseFloat(raw);
                if (!Number.isFinite(n) || n < 0) {
                    showToast('warning', 'Enter a valid non-negative number to fill.');
                    return;
                }
                const row = document.querySelector(`#slabRatesBody tr[data-slab-key="${CSS.escape(slabKey)}"]`);
                if (!row) return;
                let filled = 0;
                row.querySelectorAll('.slab-rate-input').forEach(inp => {
                    if (inp.disabled) return;
                    if (String(inp.value || '').trim() === '') {
                        inp.value = String(n);
                        inp.classList.remove('slab-rate-prefilled');
                        inp.classList.remove('slab-rate-mixed');
                        inp.removeAttribute('data-needs-missing-fill');
                        inp.setAttribute('data-user-edited', '1');
                        filled++;
                    }
                });
                if (filled === 0) showToast('info', 'No empty carrier cells were filled (all already had values).');
            }

            function setupSlabRates() {
                const openBtn = document.getElementById('slabRatesBtn');
                if (openBtn) openBtn.addEventListener('click', openSlabRatesModal);

                const applyBtn = document.getElementById('slabRatesApplyBtn');
                if (applyBtn) applyBtn.addEventListener('click', applySlabRates);

                const clearBtn = document.getElementById('slabRatesClearBtn');
                if (clearBtn) clearBtn.addEventListener('click', function () {
                    document.querySelectorAll('#slabRatesBody .slab-rate-input').forEach(i => { i.value = ''; });
                });

                const fillBtn = document.getElementById('slabRatesFillRowBtn');
                if (fillBtn) fillBtn.addEventListener('click', fillSlabRow);
            }
        });
    </script>
    <script>
        // Auto conversions between inch and CM
        document.addEventListener('DOMContentLoaded', function () {
            const inchToCm = (inch) => (parseFloat(inch) || 0) * 2.54;
            const cmToInch = (cm) => (parseFloat(cm) || 0) / 2.54;

            // Item dimensions: inch -> CM
            const lInchInput = document.getElementById('editL');
            const wInchInput = document.getElementById('editW');
            const hInchInput = document.getElementById('editH');
            const lCmInput = document.getElementById('editLCm');
            const wCmInput = document.getElementById('editWCm');
            const hCmInput = document.getElementById('editHCm');

            if (lInchInput && lCmInput) {
                lInchInput.addEventListener('input', function () {
                    const val = inchToCm(this.value);
                    lCmInput.value = val ? val.toFixed(2) : '';
                });
            }
            if (wInchInput && wCmInput) {
                wInchInput.addEventListener('input', function () {
                    const val = inchToCm(this.value);
                    wCmInput.value = val ? val.toFixed(2) : '';
                });
            }
            if (hInchInput && hCmInput) {
                hInchInput.addEventListener('input', function () {
                    const val = inchToCm(this.value);
                    hCmInput.value = val ? val.toFixed(2) : '';
                });
            }

            // Item dimensions: CM -> inch (for manual CM entry)
            if (lCmInput && lInchInput) {
                lCmInput.addEventListener('input', function () {
                    const val = cmToInch(this.value);
                    lInchInput.value = val ? val.toFixed(2) : '';
                });
            }
            if (wCmInput && wInchInput) {
                wCmInput.addEventListener('input', function () {
                    const val = cmToInch(this.value);
                    wInchInput.value = val ? val.toFixed(2) : '';
                });
            }
            if (hCmInput && hInchInput) {
                hCmInput.addEventListener('input', function () {
                    const val = cmToInch(this.value);
                    hInchInput.value = val ? val.toFixed(2) : '';
                });
            }

            // CTN dimensions: CM -> inch (display only)
            const ctnLInput = document.getElementById('editCtnL');
            const ctnWInput = document.getElementById('editCtnW');
            const ctnHInput = document.getElementById('editCtnH');
            const ctnLInchInput = document.getElementById('editCtnLInch');
            const ctnWInchInput = document.getElementById('editCtnWInch');
            const ctnHInchInput = document.getElementById('editCtnHInch');

            if (ctnLInput && ctnLInchInput) {
                ctnLInput.addEventListener('input', function () {
                    const val = cmToInch(this.value);
                    ctnLInchInput.value = val ? val.toFixed(2) : '';
                });
            }
            if (ctnWInput && ctnWInchInput) {
                ctnWInput.addEventListener('input', function () {
                    const val = cmToInch(this.value);
                    ctnWInchInput.value = val ? val.toFixed(2) : '';
                });
            }
            if (ctnHInput && ctnHInchInput) {
                ctnHInput.addEventListener('input', function () {
                    const val = cmToInch(this.value);
                    ctnHInchInput.value = val ? val.toFixed(2) : '';
                });
            }
        });
    </script>
@endsection

