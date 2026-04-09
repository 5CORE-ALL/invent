@extends('layouts.vertical', ['title' => 'Dashboard', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<!-- task dashboard css -->
<style>
        /* Menu cards ~30% smaller than original (0.7 scale on spacing/type) */
        .dashboard-card {
            background: #ffffff !important;
            border-radius: 8px !important;
            padding: 14px !important;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06) !important;
            transition: all 0.2s ease !important;
            position: relative !important;
            overflow: visible !important;
            border: 1px solid #e5e7eb !important;
            height: 100% !important;
            display: flex !important;
            flex-direction: column !important;
        }

        .dashboard-card:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
            border-color: #d1d5db !important;
        }

        .card-actions {
            position: absolute !important;
            top: 12px !important;
            right: 12px !important;
            z-index: 10 !important;
        }

        .eye-icon-btn {
            width: 36px !important;
            height: 36px !important;
            border-radius: 50% !important;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border: none !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3) !important;
        }

        .eye-icon-btn:hover {
            transform: scale(1.1) !important;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.5) !important;
        }

        .eye-icon-btn i {
            color: white !important;
            font-size: 18px !important;
        }        .dashboard-card::before {
            content: '' !important;
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            height: 3px !important;
            background: #3b82f6 !important;
            border-radius: 8px 8px 0 0 !important;
        }

        .card-header {
            display: flex !important;
            justify-content: space-between !important;
            align-items: flex-start !important;
            margin-bottom: 8px !important;
            margin-top: 6px !important;
        }

        .card-icon {
            width: 36px !important;
            height: 36px !important;
            border-radius: 7px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 1.12em !important;
            margin-bottom: 0 !important;
            box-shadow: none !important;
            transition: all 0.2s ease !important;
        }
        
        .dashboard-card:hover .card-icon {
            transform: scale(1.05) !important;
        }

        .card-title {
            font-size: 0.875rem !important;
            font-weight: 600 !important;
            color: #111827 !important;
            margin-bottom: 3px !important;
            letter-spacing: -0.01em !important;
            line-height: 1.35 !important;
        }

        .card-description {
            color: #6b7280 !important;
            font-size: 0.75rem !important;
            line-height: 1.45 !important;
            margin-bottom: 0 !important;
        }

        .card-badge {
            padding: 3px 8px !important;
            border-radius: 14px !important;
            font-size: 0.7rem !important;
            font-weight: 600 !important;
            box-shadow: none !important;
            white-space: nowrap !important;
            line-height: 1.5 !important;
        }

        .badge-cyan {
            background: #cffafe;
            color: #0891b2;
        }

        .badge-green {
            background: #d1fae5;
            color: #059669;
        }

        .badge-orange {
            background: #fed7aa;
            color: #ea580c;
        }

        .badge-pink {
            background: #fce7f3;
            color: #db2777;
        }

        .badge-purple {
            background: #e9d5ff;
            color: #9333ea;
        }

        .badge-blue {
            background: #dbeafe;
            color: #2563eb;
        }

        .badge-gray {
            background: #e5e7eb;
            color: #4b5563;
        }

        .badge-red {
            background: #fecaca;
            color: #dc2626;
        }

        .badge-yellow {
            background: #fef3c7;
            color: #d97706;
        }

        .badge-teal {
            background: #ccfbf1;
            color: #0d9488;
        }

        .badge-indigo {
            background: #e0e7ff;
            color: #4f46e5;
        }

        .badge-brown {
            background: #fef3c7;
            color: #92400e;
        }

        .subcards-preview {
            display: flex !important;
            gap: 6px !important;
            margin-top: 10px !important;
            padding-top: 10px !important;
            border-top: 1px solid #e5e7eb !important;
            flex-wrap: wrap !important;
        }

        .subcard-item {
            padding: 4px 7px !important;
            border-radius: 4px !important;
            background: #f9fafb !important;
            font-size: 0.7rem !important;
            color: #6b7280 !important;
            display: flex !important;
            align-items: center !important;
            gap: 4px !important;
            border: none !important;
            transition: all 0.15s ease !important;
            font-weight: 500 !important;
        }
        
        .subcard-item:hover {
            background: #f3f4f6 !important;
            color: #374151 !important;
        }

        .graphs-section {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)) !important;
            gap: 20px !important;
            margin-bottom: 30px !important;
        }

        .graph-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }

        .graph-title {
            font-size: 1.2em;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 20px;
        }

        .chart-container {
            position: relative;
            height: 250px;
        }

        .bar {
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            height: 100%;
            gap: 10px;
        }

        .bar-item {
            flex: 1;
            background: #3b82f6;
            border-radius: 8px 8px 0 0;
            position: relative;
            transition: all 0.3s;
        }

        .bar-item:hover {
            opacity: 0.8;
            transform: translateY(-5px);
        }

        .bar-label {
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.8em;
            color: #6b7280;
            white-space: nowrap;
        }

        .bar-value {
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.85em;
            font-weight: 600;
            color: #1f2937;
        }

        .donut-chart {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: conic-gradient(
                #3b82f6 0deg 120deg,
                #10b981 120deg 240deg,
                #f59e0b 240deg 300deg,
                #ef4444 300deg 360deg
            );
            position: relative;
            margin: 0 auto;
        }

        .donut-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .donut-value {
            font-size: 2em;
            font-weight: 700;
            color: #1f2937;
        }

        .donut-label {
            font-size: 0.8em;
            color: #6b7280;
        }

        .legend {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }

        .legend-text {
            font-size: 0.85em;
            color: #4b5563;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: 700;
            color: #3b82f6;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.9em;
            margin-top: 8px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(30px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 0;
            border-radius: 20px;
            max-width: 700px;
            width: 90%;
            max-height: 85vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
        }

        .modal-title {
            font-size: 1.5em;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-title-icon {
            font-size: 1.3em;
        }

        .close-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            color: white;
        }

        .close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 30px;
            overflow-y: auto;
            flex: 1;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
        }

        .menu-item {
            background: linear-gradient(135deg, #f6f8fb 0%, #ffffff 100%);
            padding: 18px 20px;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #1f2937;
            position: relative;
            overflow: hidden;
        }

        .menu-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .menu-item:hover {
            transform: translateX(8px);
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }

        .menu-item:hover::before {
            transform: scaleY(1);
        }

        .menu-item-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3em;
            flex-shrink: 0;
        }

        .menu-item-text {
            font-weight: 600;
            font-size: 0.95em;
            flex: 1;
        }

        .menu-item-arrow {
            color: #9ca3af;
            font-size: 1.2em;
            transition: transform 0.3s ease;
        }

        .menu-item:hover .menu-item-arrow {
            transform: translateX(4px);
            color: #667eea;
        }

        .no-items {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }

        .no-items i {
            font-size: 3em;
            color: #d1d5db;
            margin-bottom: 15px;
        }

        .modal-search {
            margin-bottom: 20px;
            position: relative;
        }

        .modal-search input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.95em;
            transition: all 0.3s ease;
        }

        .modal-search input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .modal-search i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .chart-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }

        .chart-modal.active {
            display: flex;
        }

        .chart-modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 95%;
            height: 90vh;
            max-width: 1400px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
        }

        .chart-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }

        .chart-modal-title {
            font-size: 1.5em;
            font-weight: 600;
            color: #1f2937;
        }

        .chart-modal-body {
            flex: 1;
            position: relative;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }

        /* Fullscreen pie: pie + single-column legend with figures (no Chart.js multi-column wrap) */
        .chart-modal-pie-wrap {
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            gap: 1rem;
            flex: 1;
            min-height: 0;
            height: 100%;
        }
        .chart-modal-canvas-wrap {
            flex: 1 1 45%;
            min-width: 260px;
            min-height: min(72vh, 560px);
            height: min(72vh, 560px);
            position: relative;
        }
        .chart-modal-legend-col {
            flex: 1 1 280px;
            max-width: 100%;
            max-height: min(75vh, 620px);
            overflow-x: hidden;
            overflow-y: auto;
            padding: 4px 8px 8px 8px;
            margin: 0;
            border-left: 1px solid #e5e7eb;
            list-style: none;
        }
        .pie-legend-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 12px;
            line-height: 1.25;
            color: #374151;
        }
        .pie-legend-swatch {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
            border: 1px solid rgba(0,0,0,0.08);
        }
        .pie-legend-name {
            flex: 1 1 auto;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .pie-legend-val {
            flex: 0 0 auto;
            font-variant-numeric: tabular-nums;
            text-align: right;
            white-space: nowrap;
            color: #111827;
            font-weight: 600;
        }
        @media (max-width: 700px) {
            .chart-modal-legend-col {
                border-left: none;
                border-top: 1px solid #e5e7eb;
                padding-left: 4px;
                max-height: 40vh;
            }
        }

        .dashboard-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)) !important;
            gap: 14px !important;
            margin-top: 21px !important;
            margin-bottom: 21px !important;
            padding: 0 !important;
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr !important;
            }

            .graphs-section {
                grid-template-columns: 1fr;
            }

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }

            .top-header {
                flex-direction: column;
                gap: 15px;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }

        @media (min-width: 1025px) and (max-width: 1400px) {
            .dashboard-grid {
                grid-template-columns: repeat(3, 1fr) !important;
            }
        }

        @media (min-width: 1401px) {
            .dashboard-grid {
                grid-template-columns: repeat(4, 1fr) !important;
            }
        }

        /* Top KPI cards: single row, equal width, centered text */
        .dashboard-kpi-strip {
            flex-wrap: nowrap;
        }
        .dashboard-kpi-strip > .col {
            flex: 1 1 0;
            min-width: 0;
        }
        .dashboard-kpi-strip .kpi-card-link {
            display: block;
            height: 100%;
            text-decoration: none;
        }
        .dashboard-kpi-strip .card.widget-flat .card-body {
            text-align: center;
        }
        .dashboard-kpi-strip .card.widget-flat .kpi-icon {
            font-size: 1.35rem;
            opacity: 0.95;
            margin-bottom: 0.35rem;
            line-height: 1;
        }
        .dashboard-kpi-strip .card.widget-flat h2 {
            font-size: clamp(1.25rem, 3.5vw, 1.75rem);
        }

        /* Match All Marketplace Master summary pills; ~30% smaller than default fs-6 p-2 */
        #dashboard-summary-stats .badge {
            font-size: 0.8125rem !important;
            padding: 0.4rem 0.65rem !important;
            line-height: 1.25 !important;
        }
        #dashboard-summary-stats h6 {
            font-size: 0.8rem;
            color: #6b7280;
        }

        /* Chart cards: ~50% smaller footprint than previous (height + chrome) */
        .dashboard-charts-row {
            --dashboard-chart-h: clamp(130px, 19vh, 240px);
        }
        .dashboard-charts-row .dashboard-chart-card {
            position: relative;
            border: 1px solid #e8ecf1 !important;
            border-radius: 10px !important;
            overflow: hidden;
            background: linear-gradient(165deg, #ffffff 0%, #f8fafc 55%, #ffffff 100%);
            box-shadow:
                0 1px 2px rgba(15, 23, 42, 0.04),
                0 8px 24px -4px rgba(15, 23, 42, 0.08) !important;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }
        .dashboard-charts-row .dashboard-chart-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            border-radius: 10px 10px 0 0;
            z-index: 1;
        }
        .dashboard-charts-row .dashboard-chart-card:hover {
            box-shadow:
                0 4px 6px rgba(15, 23, 42, 0.05),
                0 14px 32px -6px rgba(15, 23, 42, 0.12) !important;
        }
        .dashboard-charts-row .dashboard-chart-card--pie::before {
            background: linear-gradient(90deg, #0d9488, #14b8a6) !important;
        }
        .dashboard-charts-row .dashboard-chart-card--bar::before {
            background: linear-gradient(90deg, #4f46e5, #6366f1) !important;
        }
        .dashboard-charts-row .dashboard-chart-badge-teal {
            background: linear-gradient(135deg, #0d9488, #0f766e) !important;
            color: #fff !important;
        }
        .dashboard-charts-row .dashboard-chart-badge-indigo {
            background: linear-gradient(135deg, #4f46e5, #4338ca) !important;
            color: #fff !important;
        }
        .dashboard-charts-row .dashboard-chart-card .card-body {
            padding: 0.5rem 0.6rem 0.55rem;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .dashboard-charts-row .dashboard-chart-head {
            margin-bottom: 0.2rem;
        }
        .dashboard-charts-row .dashboard-chart-head .header-title {
            font-size: 0.8125rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #0f172a;
            line-height: 1.2;
        }
        .dashboard-charts-row .dashboard-chart-sub {
            font-size: 0.65rem;
            color: #64748b;
            margin-top: 0.08rem;
            line-height: 1.3;
        }
        .dashboard-charts-row .dashboard-chart-sub .dashboard-chart-total-amount {
            font-size: 2em;
            font-weight: 600;
            color: #0f172a;
            letter-spacing: -0.02em;
        }
        .dashboard-charts-row .dashboard-chart-canvas-wrap {
            position: relative;
            width: 100%;
            min-height: var(--dashboard-chart-h);
            height: var(--dashboard-chart-h);
            max-width: 100%;
            margin: 0 auto;
            flex: 1 1 auto;
        }
        .dashboard-charts-row .dashboard-chart-badge {
            font-size: 0.5rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.12rem 0.35rem;
            border-radius: 4px;
        }
        /* Chart cards: icon-only actions (top right) */
        .dashboard-charts-row .dashboard-chart-head-actions .btn-chart-icon {
            width: 1.65rem;
            height: 1.65rem;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 7px;
            line-height: 1;
            transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }
        .dashboard-charts-row .dashboard-chart-card--pie .dashboard-chart-head-actions .btn-chart-icon {
            border: 1px solid rgba(13, 148, 136, 0.35);
            background: linear-gradient(180deg, #f0fdfa 0%, #ecfdf5 100%);
            color: #0f766e;
        }
        .dashboard-charts-row .dashboard-chart-card--pie .dashboard-chart-head-actions .btn-chart-icon:hover {
            background: #ccfbf1;
            border-color: #0d9488;
            color: #115e59;
        }
        .dashboard-charts-row .dashboard-chart-card--bar .dashboard-chart-head-actions .btn-chart-icon {
            border: 1px solid rgba(79, 70, 229, 0.4);
            background: linear-gradient(180deg, #eef2ff 0%, #e0e7ff 100%);
            color: #4338ca;
        }
        .dashboard-charts-row .dashboard-chart-card--bar .dashboard-chart-head-actions .btn-chart-icon:hover {
            background: #c7d2fe;
            border-color: #6366f1;
            color: #312e81;
        }
        .dashboard-charts-row .dashboard-chart-head-actions .btn-chart-icon i {
            font-size: 0.95rem;
        }
        .dashboard-charts-row .dashboard-chart-head-actions {
            position: relative;
            z-index: 2;
        }
        @media (max-width: 991.98px) {
            .dashboard-charts-row {
                --dashboard-chart-h: clamp(110px, 21vw, 170px);
            }
        }
</style>
@endsection

@section('content')
@include('layouts.shared/page-title', ['sub_title' => 'Menu', 'page_title' => 'Dashboard'])

    <div id="dashboard-summary-stats" class="mt-2 mb-3 p-3 bg-light rounded">
        <h6 class="mb-1">Summary Statistics</h6>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge bg-primary fs-6 p-2" style="color: white; font-weight: bold;">
                Channels: <span id="total-channels">0</span>
            </span>
            <span class="badge bg-success fs-6 p-2" style="color: black; font-weight: bold;" title="Sum of Sales column">
                Sales: <span id="total-l30-sales">$0</span>
            </span>
            <span class="badge bg-info fs-6 p-2" style="color: black; font-weight: bold;" title="Sum of Orders column">
                Orders: <span id="total-l30-orders">0</span>
            </span>
            <span class="badge bg-primary fs-6 p-2" style="color: white; font-weight: bold;">
                Qty items: <span id="total-qty">0</span>
            </span>
            <span class="badge bg-warning fs-6 p-2" style="color: black; font-weight: bold;" title="Blended Gprofit%">
                GPFT%: <span id="avg-gprofit">0%</span>
            </span>
            <span class="badge bg-warning fs-6 p-2" style="color: black; font-weight: bold; border: 1px solid rgba(0,0,0,.25);" title="Gross profit $">
                GPFT: <span id="total-gross-pft">$0</span>
            </span>
            <span class="badge bg-danger fs-6 p-2" style="color: white; font-weight: bold;">
                G ROI: <span id="avg-groi">0%</span>
            </span>
            <span class="badge bg-secondary fs-6 p-2" style="color: white; font-weight: bold;">
                Ads Spend: <span id="total-ad-spend">$0</span>
            </span>
            <span class="badge bg-info fs-6 p-2" style="color: black; font-weight: bold;">
                Total Views: <span id="total-views-badge">0</span>
            </span>
            <span class="badge bg-primary fs-6 p-2" style="color: white; font-weight: bold;">
                CVR %: <span id="cvr-pct-badge">0%</span>
            </span>
            <span class="badge bg-warning fs-6 p-2" style="color: black; font-weight: bold;">
                NPFT: <span id="total-pft">$0</span>
            </span>
            <span class="badge bg-warning fs-6 p-2" style="color: black; font-weight: bold;">
                NPFT: <span id="avg-npft">0%</span>
            </span>
            <span class="badge bg-primary fs-6 p-2" style="color: white; font-weight: bold;">
                NROI: <span id="avg-nroi">0%</span>
            </span>
            <span class="badge bg-info fs-6 p-2" style="color: black; font-weight: bold;">
                Clicks: <span id="total-clicks">0</span>
            </span>
            <span class="badge bg-danger fs-6 p-2" style="color: white; font-weight: bold;">
                Missing M: <span id="total-nmap">0</span>
            </span>
            <span class="badge bg-danger fs-6 p-2" style="color: white; font-weight: bold;">
                Missing L : <span id="total-miss">0</span>
            </span>
            <span class="badge bg-info fs-6 p-2" style="color: black; font-weight: bold;" title="Sum of (Inventory × Amazon Price)">
                INV Val: $<span id="inventory-value-amazon">0</span>
            </span>
            <span class="badge bg-warning fs-6 p-2" style="color: black; font-weight: bold;" title="Sum of (Shopify inventory × LP)">
                Inv@LP: $<span id="inv-at-lp">0</span>
            </span>
            <span class="badge bg-success fs-6 p-2" style="color: black; font-weight: bold;" title="Sum of shopify_skus.inv">
                Shopify Inv: <span id="dashboard-shopify-inv-sum">0</span>
            </span>
            <span class="badge bg-success fs-6 p-2" style="color: black; font-weight: bold;" title="product_master.Values → lp (JSON), inv-weighted">
                LP: <span id="dashboard-shopify-lp-avg">$0</span>
            </span>
            <span class="badge bg-secondary fs-6 p-2" style="color: white; font-weight: bold;" title="INV Val ÷ Sales">
                TAT: <span id="tat-badge">0</span>
            </span>
            <span class="badge bg-info fs-6 p-2" style="color: black; font-weight: bold;">
                Ratings &amp; Reviews: <span id="ratings-reviews-badge">0 ★ | 0</span>
            </span>
            <span class="badge bg-dark fs-6 p-2" style="color: white; font-weight: bold;">
                Seller Rating &amp; Reviews: <span id="seller-ratings-reviews-badge">0 ★ | 0</span>
            </span>
        </div>
    </div>

    <div class="row g-2 dashboard-charts-row align-items-stretch mb-1">
        <div class="col-12 col-md-6 col-lg-3 d-flex">
            <div class="card dashboard-chart-card dashboard-chart-card--pie w-100 h-100 border-0">
                <div class="card-body">
                    <div class="dashboard-chart-head d-flex flex-wrap align-items-start justify-content-between gap-2">
                        <div class="min-w-0 flex-grow-1">
                            <h5 class="header-title mb-0">Sales by Channels</h5>
                            <p class="dashboard-chart-sub mb-0">Total: <span id="dashboard-chart-l30-total" class="dashboard-chart-total-amount">—</span></p>
                        </div>
                        <div class="dashboard-chart-head-actions d-flex align-items-center gap-1 flex-shrink-0 align-self-start">
                            <button type="button" class="btn btn-chart-icon" id="dashboard-pie-refresh-btn" title="Refresh L30 charts" aria-label="Refresh">
                                <i class="ri-refresh-line" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="btn btn-chart-icon" id="dashboard-pie-fullscreen-btn" title="L30 fullscreen legend" aria-label="Fullscreen">
                                <i class="ri-fullscreen-line" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div class="pt-1">
                        <div dir="ltr" class="dashboard-chart-canvas-wrap channel-sales-chart-wrap">
                            <canvas id="channelSalesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3 d-flex">
            <div class="card dashboard-chart-card dashboard-chart-card--pie w-100 h-100 border-0">
                <div class="card-body">
                    <div class="dashboard-chart-head d-flex flex-wrap align-items-start justify-content-between gap-2">
                        <div class="min-w-0 flex-grow-1">
                            <h5 class="header-title mb-0">Sales by Channels</h5>
                            <p class="dashboard-chart-sub mb-0">Total: <span id="dashboard-chart-l30-bar-total" class="dashboard-chart-total-amount">—</span></p>
                        </div>
                        <div class="dashboard-chart-head-actions d-flex align-items-center gap-1 flex-shrink-0 align-self-start">
                            <button type="button" class="btn btn-chart-icon" id="dashboard-l30-bar-refresh-btn" title="Refresh L30 charts" aria-label="Refresh">
                                <i class="ri-refresh-line" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div class="pt-1">
                        <div dir="ltr" class="dashboard-chart-canvas-wrap channel-sales-bar-chart-wrap">
                            <canvas id="channelSalesBarChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3 d-flex">
            <div class="card dashboard-chart-card dashboard-chart-card--bar w-100 h-100 border-0">
                <div class="card-body">
                    <div class="dashboard-chart-head d-flex flex-wrap align-items-start justify-content-between gap-2">
                        <div class="min-w-0 flex-grow-1">
                            <h5 class="header-title mb-0">Y Sales by Channel</h5>
                            <p class="dashboard-chart-sub mb-0">Total: <span id="dashboard-chart-y-total" class="dashboard-chart-total-amount">—</span></p>
                        </div>
                        <div class="dashboard-chart-head-actions d-flex align-items-center gap-1 flex-shrink-0 align-self-start">
                            <button type="button" class="btn btn-chart-icon" id="dashboard-y-refresh-btn" title="Refresh Y charts" aria-label="Refresh">
                                <i class="ri-refresh-line" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="btn btn-chart-icon" id="dashboard-y-fullscreen-btn" title="Y Sales fullscreen legend" aria-label="Fullscreen">
                                <i class="ri-fullscreen-line" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div class="pt-1">
                        <div dir="ltr" id="daily-sales-chart-wrap" class="dashboard-chart-canvas-wrap">
                            <canvas id="dailySalesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3 d-flex">
            <div class="card dashboard-chart-card dashboard-chart-card--bar w-100 h-100 border-0">
                <div class="card-body">
                    <div class="dashboard-chart-head d-flex flex-wrap align-items-start justify-content-between gap-2">
                        <div class="min-w-0 flex-grow-1">
                            <h5 class="header-title mb-0">Y Sales by Channel</h5>
                            <p class="dashboard-chart-sub mb-0">Total: <span id="dashboard-chart-y-bar-total" class="dashboard-chart-total-amount">—</span></p>
                        </div>
                        <div class="dashboard-chart-head-actions d-flex align-items-center gap-1 flex-shrink-0 align-self-start">
                            <button type="button" class="btn btn-chart-icon" id="dashboard-y-bar-refresh-btn" title="Refresh Y charts" aria-label="Refresh">
                                <i class="ri-refresh-line" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div class="pt-1">
                        <div dir="ltr" class="dashboard-chart-canvas-wrap daily-sales-bar-chart-wrap">
                            <canvas id="dailySalesBarChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- task dashboard -->
      <div class="dashboard-grid">
        <div class="dashboard-card" onclick="openModal('Tasks')">
            <div class="card-icon" style="background: #cffafe;">✓</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Tasks</div>
                    <div class="card-description">Manage your tasks, assigned tasks, and track progress</div>
                </div>
                <span class="card-badge badge-cyan">31 Items</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">📋 My Tasks</span>
                <span class="subcard-item">📊 Task Summary</span>
                <span class="subcard-item">✓ Completed</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('My Team')">
            <div class="card-icon" style="background: #d1fae5;">👥</div>
            <div class="card-header">
                <div>
                    <div class="card-title">My Team</div>
                    <div class="card-description">View team members and performance metrics</div>
                </div>
                <span class="card-badge badge-green">1 Members</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">👤 Members</span>
                <span class="subcard-item">📊 Performance</span>
                <span class="subcard-item">🎯 Goals</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('Inventory')">
            <div class="card-icon" style="background: #fed7aa;">📦</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Inventory</div>
                    <div class="card-description">Inventory values</div>
                </div>
                <span class="card-badge badge-orange">1 Metrics</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">📈 Stock Levels</span>
                <span class="subcard-item">💰 Valuation</span>
            </div>
        </div>

        <!-- <div class="dashboard-card" onclick="openModal('Sales')">
            <div class="card-icon" style="background: #fef3c7;">💰</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Sales</div>
                    <div class="card-description">Track sales performance</div>
                </div>
                <span class="card-badge badge-brown">286,435</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">🛒 E-Commerce</span>
                <span class="subcard-item">🛍️ Shopify</span>
                <span class="subcard-item">📱 Social Media</span>
                <span class="subcard-item">📦 Amazon</span>
            </div>
        </div> -->

        <div class="dashboard-card" onclick="openModal('Operations')">
            <div class="card-icon" style="background: #fce7f3;">⏰</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Operations</div>
                    <div class="card-description">Track customer, Shipping & Reviews analyze</div>
                </div>
                <span class="card-badge badge-pink">3 Metrics</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">🚚 Shipping</span>
                <span class="subcard-item">⭐ Reviews</span>
                <span class="subcard-item">👥 Customers</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('Human Resources')">
            <div class="card-icon" style="background: #e9d5ff;">👨‍💼</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Human Resources</div>
                    <div class="card-description">Employee management & attendance tracking</div>
                </div>
                <span class="card-badge badge-purple">3 Metrics</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">👥 Employees</span>
                <span class="subcard-item">📅 Attendance</span>
                <span class="subcard-item">💼 Payroll</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('Software & IT')">
            <div class="card-icon" style="background: #ccfbf1;">💻</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Software & IT</div>
                    <div class="card-description">Generate reports and view analytics</div>
                </div>
                <span class="card-badge badge-teal">12 Items</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">🖥️ Systems</span>
                <span class="subcard-item">🔧 Maintenance</span>
                <span class="subcard-item">📊 Analytics</span>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-actions">
                <button class="eye-icon-btn" onclick="openModal('Purchase'); event.stopPropagation();">
                    <i class="ri-eye-line"></i>
                </button>
            </div>
            <div class="card-icon" style="background: #1e3a5f; color: white;">🛒</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Purchase</div>
                    <div class="card-description">Purchase management and analytics</div>
                </div>
                <span class="card-badge badge-indigo">3 Modules</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">📦 Categories</span>
                <span class="subcard-item">🏢 Suppliers</span>
                <span class="subcard-item">⚙️ MFRG</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('Pricing')">
            <div class="card-icon" style="background: #fef3c7;">💵</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Pricing</div>
                    <div class="card-description">Get Pricing reports and view analytics</div>
                </div>
                <span class="card-badge badge-yellow">79%</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">💰 Price Lists</span>
                <span class="subcard-item">📈 Trends</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('Advertisements')">
            <div class="card-icon" style="background: #e5e7eb;">📢</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Advertisements</div>
                    <div class="card-description">Get Advertisments reports and view analytics</div>
                </div>
                <span class="card-badge badge-gray">9 Metrics</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">📱 Digital Ads</span>
                <span class="subcard-item">📺 Campaigns</span>
                <span class="subcard-item">📊 ROI</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('Content')">
            <div class="card-icon" style="background: #7c2d12; color: white;">📝</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Content</div>
                    <div class="card-description">Get Content reports</div>
                </div>
                <span class="card-badge badge-red">0 Metrics</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">✍️ Articles</span>
                <span class="subcard-item">🎨 Media</span>
                <span class="subcard-item">📅 Schedule</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('Marketing')">
            <div class="card-icon" style="background: #dbeafe;">🎯</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Marketing</div>
                    <div class="card-description">Get Marketing analytics</div>
                </div>
                <span class="card-badge badge-blue">6 Metrics</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">📧 Email</span>
                <span class="subcard-item">🎯 Campaigns</span>
                <span class="subcard-item">📊 Analytics</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('Social Media')">
            <div class="card-icon" style="background: #fef3c7;">📱</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Social Media</div>
                    <div class="card-description">Get Social Media analytics</div>
                </div>
                <span class="card-badge badge-yellow">0 Metrics</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">📘 Facebook</span>
                <span class="subcard-item">📷 Instagram</span>
                <span class="subcard-item">🐦 Twitter</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('Videos')">
            <div class="card-icon" style="background: #fed7aa;">🎬</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Videos</div>
                    <div class="card-description">Get Videos details</div>
                </div>
                <span class="card-badge badge-orange">0 Metrics</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">🎥 Library</span>
                <span class="subcard-item">▶️ Views</span>
                <span class="subcard-item">👍 Engagement</span>
            </div>
        </div>

        <div class="dashboard-card" onclick="openModal('Logistics')">
            <div class="card-icon" style="background: #1e3a5f; color: white;">🚚</div>
            <div class="card-header">
                <div>
                    <div class="card-title">Logistics</div>
                    <div class="card-description">Get Logistics Track Reports</div>
                </div>
                <span class="card-badge badge-indigo">0 Metrics</span>
            </div>
            <div class="subcards-preview">
                <span class="subcard-item">📦 Shipments</span>
                <span class="subcard-item">🚛 Tracking</span>
                <span class="subcard-item">📍 Delivery</span>
            </div>
        </div>
    </div>
    
    <!-- end row -->

    <!-- Menu Modal -->
    <div class="modal" id="menuModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <span class="modal-title-icon" id="modalIcon">📦</span>
                    <span id="modalCategory">Menu</span>
                </div>
                <button class="close-btn" onclick="closeMenuModal()">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="modal-search">
                    <input type="text" id="menuSearch" placeholder="Search menu items..." onkeyup="filterMenuItems()">
                    <i class="ri-search-line"></i>
                </div>
                <div class="menu-grid" id="menuGrid">
                    <!-- Menu items will be dynamically loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Channel Sales Fullscreen Modal -->
    <div class="chart-modal" id="chartModal">
        <div class="chart-modal-content">
            <div class="chart-modal-header">
                <h3 class="chart-modal-title">Sales by Channels — Fullscreen (L30)</h3>
                <button type="button" class="close-btn" onclick="closeChartModal();">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="chart-modal-body">
                <div class="chart-modal-pie-wrap">
                    <div class="chart-modal-canvas-wrap">
                        <canvas id="channelSalesChartModal"></canvas>
                    </div>
                    <ul class="chart-modal-legend-col" id="channelPieLegendList" aria-label="L30 sales by channel"></ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Y Sales fullscreen: pie share of total Y revenue + same legend layout as L30 -->
    <div class="chart-modal" id="ySalesChartModal">
        <div class="chart-modal-content">
            <div class="chart-modal-header">
                <h3 class="chart-modal-title">Y Sales by Channel — Fullscreen View</h3>
                <button type="button" class="close-btn" onclick="closeYSalesChartModal();">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="chart-modal-body">
                <div class="chart-modal-pie-wrap">
                    <div class="chart-modal-canvas-wrap">
                        <canvas id="ySalesChartModalCanvas"></canvas>
                    </div>
                    <ul class="chart-modal-legend-col" id="ySalesPieLegendList" aria-label="Y sales by channel"></ul>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // ============================================
        // DASHBOARD METRICS - Complete Fresh Implementation
        // ============================================
        
        let l30SalesPieChartInstance = null;
        let l30SalesBarChartInstance = null;
        let ySalesPieChartInstance = null;
        let ySalesBarChartInstance = null;

        function pieSliceColors(count) {
            const colors = [];
            for (let i = 0; i < count; i++) {
                const hue = (i * 360 / Math.max(count, 1)) % 360;
                colors.push('hsla(' + hue + ', 62%, 52%, 0.92)');
            }
            return colors;
        }

        function pieSliceBorderColors(count) {
            const colors = [];
            for (let i = 0; i < count; i++) {
                const hue = (i * 360 / Math.max(count, 1)) % 360;
                colors.push('hsla(' + hue + ', 62%, 38%, 1)');
            }
            return colors;
        }

        function setDashboardChartTotals(ids, values) {
            var text;
            if (!values || values.length === 0) {
                text = '—';
            } else {
                var sum = values.reduce(function (a, b) {
                    return a + b;
                }, 0);
                text = '$' + Number(sum).toLocaleString('en-US', { maximumFractionDigits: 0 });
            }
            ids.forEach(function (id) {
                var el = document.getElementById(id);
                if (el) {
                    el.textContent = text;
                }
            });
        }
        
        /**
         * Y Sales: compact pie + bar (same API name as before for callers).
         * Source: GET /channels-master-data → row['Y Sales'].
         */
        function createYSalesBarChart(prefetchedResult) {
            console.log('[Dashboard] Y Sales pie + bar charts...');

            function renderYSalesCharts(result) {
                if (!result.data || result.data.length === 0) {
                    console.warn('[Dashboard] No channel data for Y Sales charts');
                    setDashboardChartTotals(['dashboard-chart-y-total', 'dashboard-chart-y-bar-total'], []);
                    return;
                }

                const rows = [];
                result.data.forEach(function (row) {
                    const name = row['Channel '] || row['Channel'] || row.channel || row.name || row.Name || 'Unknown';
                    const y = parseFloat(row['Y Sales']);
                    const ySales = Number.isFinite(y) ? y : 0;
                    rows.push({ name: String(name).trim(), ySales: ySales });
                });
                rows.sort(function (a, b) {
                    return b.ySales - a.ySales;
                });

                const channels = rows.map(function (r) {
                    return r.name;
                });
                const ySalesValues = rows.map(function (r) {
                    return r.ySales;
                });
                const n = channels.length;

                setDashboardChartTotals(['dashboard-chart-y-total', 'dashboard-chart-y-bar-total'], ySalesValues);

                if (ySalesPieChartInstance) {
                    ySalesPieChartInstance.destroy();
                }
                const pieCtx = document.getElementById('dailySalesChart');
                if (pieCtx) {
                    ySalesPieChartInstance = new Chart(pieCtx, {
                        type: 'pie',
                        data: {
                            labels: channels,
                            datasets: [{
                                label: 'Y Sales ($)',
                                data: ySalesValues,
                                backgroundColor: pieSliceColors(n),
                                borderColor: pieSliceBorderColors(n),
                                borderWidth: 1,
                                hoverOffset: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            layout: {
                                padding: { top: 4, bottom: 4, left: 2, right: 2 }
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.85)',
                                    padding: 6,
                                    titleFont: { size: 9, weight: 'bold' },
                                    bodyFont: { size: 8 },
                                    callbacks: {
                                        label: function (context) {
                                            return channelPieTooltipLabel(context, ySalesValues);
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                if (ySalesBarChartInstance) {
                    ySalesBarChartInstance.destroy();
                }
                const barCtx = document.getElementById('dailySalesBarChart');
                if (barCtx) {
                    ySalesBarChartInstance = new Chart(barCtx, {
                        type: 'bar',
                        data: {
                            labels: channels,
                            datasets: [{
                                label: 'Y Sales',
                                data: ySalesValues,
                                backgroundColor: pieSliceColors(n),
                                borderColor: pieSliceBorderColors(n),
                                borderWidth: 1,
                                borderRadius: 3
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            layout: {
                                padding: { top: 2, bottom: 0, left: 0, right: 2 }
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.85)',
                                    padding: 6,
                                    titleFont: { size: 9, weight: 'bold' },
                                    bodyFont: { size: 8 },
                                    callbacks: {
                                        label: function (context) {
                                            return '$' + context.parsed.y.toLocaleString('en-US', { maximumFractionDigits: 2 });
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    ticks: {
                                        font: { size: 7 },
                                        maxRotation: 52,
                                        minRotation: 38,
                                        autoSkip: false,
                                        color: '#475569'
                                    },
                                    grid: { display: false },
                                    title: { display: false }
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function (value) {
                                            return '$' + Number(value).toLocaleString('en-US', { maximumFractionDigits: 0 });
                                        },
                                        font: { size: 8 },
                                        color: '#475569'
                                    },
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.06)',
                                        drawBorder: false
                                    },
                                    title: { display: false }
                                }
                            }
                        }
                    });
                }

                console.log('[Dashboard] ✅ Y Sales pie + bar created (' + n + ' channels)');
            }

            if (prefetchedResult && prefetchedResult.data !== undefined) {
                renderYSalesCharts(prefetchedResult);
                return;
            }

            fetch('/channels-master-data')
                .then(function (response) {
                    console.log('[Dashboard] channels-master-data status:', response.status);
                    return response.json();
                })
                .then(renderYSalesCharts)
                .catch(function (error) {
                    console.error('[Dashboard] ❌ Error loading Y Sales charts:', error);
                });
        }

        function channelPieTooltipLabel(context, dataValues) {
            const value = context.parsed;
            const total = dataValues.reduce(function (a, b) { return a + b; }, 0);
            const pct = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
            return ' $' + value.toLocaleString('en-US', { maximumFractionDigits: 0 }) + ' (' + pct + '%)';
        }

        function formatPieLegendUsd(n) {
            return '$' + Number(n).toLocaleString('en-US', { maximumFractionDigits: 0 });
        }

        /** Single-column HTML legend with amounts (replaces Chart.js legend to avoid multi-column wrap). */
        function fillChannelPieLegendList(channels, values, backgroundColors, legendListId) {
            const ul = document.getElementById(legendListId || 'channelPieLegendList');
            if (!ul) {
                return;
            }
            ul.innerHTML = '';
            const total = values.reduce(function (a, b) { return a + b; }, 0);
            const frag = document.createDocumentFragment();
            for (let i = 0; i < channels.length; i++) {
                const li = document.createElement('li');
                li.className = 'pie-legend-row';
                const val = values[i];
                const pct = total > 0 ? ((val / total) * 100).toFixed(1) : '0.0';
                const swatch = document.createElement('span');
                swatch.className = 'pie-legend-swatch';
                swatch.style.backgroundColor = backgroundColors[i];
                swatch.setAttribute('aria-hidden', 'true');
                const name = document.createElement('span');
                name.className = 'pie-legend-name';
                name.textContent = channels[i];
                name.title = channels[i];
                const fig = document.createElement('span');
                fig.className = 'pie-legend-val';
                fig.textContent = formatPieLegendUsd(val) + ' (' + pct + '%)';
                li.appendChild(swatch);
                li.appendChild(name);
                li.appendChild(fig);
                frag.appendChild(li);
            }
            ul.appendChild(frag);
        }

        /** L30 Sales: compact pie + bar. Fullscreen modal = pie + legend. */
        function loadChannelSalesChart(prefetchedResult) {
            console.log('[Dashboard] L30 Sales pie + bar charts...');

            function renderL30Charts(result) {
                if (!result.data || result.data.length === 0) {
                    console.warn('[Dashboard] No channel data for L30 charts');
                    setDashboardChartTotals(['dashboard-chart-l30-total', 'dashboard-chart-l30-bar-total'], []);
                    return;
                }

                const rows = [];
                result.data.forEach(function (row) {
                    const name = row['Channel '] || row['Channel'] || row.channel || row.name || row.Name || 'Unknown';
                    const sales = parseFloat(row['L30 Sales'] || row.l30_sales || row.L30Sales || 0);
                    rows.push({ name: String(name).trim(), sales: sales });
                });
                rows.sort(function (a, b) {
                    return b.sales - a.sales;
                });

                const channels = rows.map(function (r) {
                    return r.name;
                });
                const l30Values = rows.map(function (r) {
                    return r.sales;
                });
                const n = channels.length;

                setDashboardChartTotals(['dashboard-chart-l30-total', 'dashboard-chart-l30-bar-total'], l30Values);

                if (l30SalesPieChartInstance) {
                    l30SalesPieChartInstance.destroy();
                }
                const pieCtx = document.getElementById('channelSalesChart');
                if (pieCtx) {
                    l30SalesPieChartInstance = new Chart(pieCtx, {
                        type: 'pie',
                        data: {
                            labels: channels,
                            datasets: [{
                                label: 'L30 Sales ($)',
                                data: l30Values,
                                backgroundColor: pieSliceColors(n),
                                borderColor: pieSliceBorderColors(n),
                                borderWidth: 1,
                                hoverOffset: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            layout: {
                                padding: { top: 4, bottom: 4, left: 2, right: 2 }
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.85)',
                                    padding: 6,
                                    titleFont: { size: 9, weight: 'bold' },
                                    bodyFont: { size: 8 },
                                    callbacks: {
                                        label: function (context) {
                                            return channelPieTooltipLabel(context, l30Values);
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                if (l30SalesBarChartInstance) {
                    l30SalesBarChartInstance.destroy();
                }
                const barCtx = document.getElementById('channelSalesBarChart');
                if (barCtx) {
                    l30SalesBarChartInstance = new Chart(barCtx, {
                        type: 'bar',
                        data: {
                            labels: channels,
                            datasets: [{
                                label: 'L30 Sales',
                                data: l30Values,
                                backgroundColor: pieSliceColors(n),
                                borderColor: pieSliceBorderColors(n),
                                borderWidth: 1,
                                borderRadius: 3
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            layout: {
                                padding: { top: 2, bottom: 0, left: 0, right: 2 }
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.85)',
                                    padding: 6,
                                    titleFont: { size: 9, weight: 'bold' },
                                    bodyFont: { size: 8 },
                                    callbacks: {
                                        label: function (context) {
                                            return '$' + context.parsed.y.toLocaleString('en-US', { maximumFractionDigits: 2 });
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    ticks: {
                                        font: { size: 7 },
                                        maxRotation: 52,
                                        minRotation: 38,
                                        autoSkip: false,
                                        color: '#475569'
                                    },
                                    grid: { display: false },
                                    title: { display: false }
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function (value) {
                                            return '$' + Number(value).toLocaleString('en-US', { maximumFractionDigits: 0 });
                                        },
                                        font: { size: 8 },
                                        color: '#475569'
                                    },
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.06)',
                                        drawBorder: false
                                    },
                                    title: { display: false }
                                }
                            }
                        }
                    });
                }

                console.log('[Dashboard] ✅ L30 Sales pie + bar created (' + n + ' channels)');
            }

            if (prefetchedResult && prefetchedResult.data !== undefined) {
                renderL30Charts(prefetchedResult);
                return;
            }

            fetch('/channels-master-data')
                .then(function (response) {
                    console.log('[Dashboard] Channel data response status:', response.status);
                    return response.json();
                })
                .then(renderL30Charts)
                .catch(function (error) {
                    console.error('[Dashboard] ❌ Error fetching L30 charts:', error);
                });
        }

        window.loadChannelSalesChart = loadChannelSalesChart;

        // ============================================
        // DASHBOARD: same Summary Statistics logic as All Marketplace Master
        // ============================================

        function dashboardParseNumber(value) {
            if (value === null || value === undefined) return 0;
            if (typeof value === 'number' && !isNaN(value)) return value;
            const cleaned = String(value).replace(/[^0-9.-]/g, '');
            const n = parseFloat(cleaned);
            return isNaN(n) ? 0 : n;
        }

        function setDashText(id, text) {
            const el = document.getElementById(id);
            if (el) el.textContent = text;
        }

        function updateDashboardSummaryStats(data) {
            if (!data || !data.length) return;

            let totalChannels = data.length;
            let totalL30Sales = 0;
            let totalL30Orders = 0;
            let totalQty = 0;
            let totalClicks = 0;
            let totalPft = 0;
            let totalCogs = 0;
            let totalAdSpend = 0;
            let totalViews = 0;
            let totalMiss = 0;
            let totalNMap = 0;
            let totalMap = 0;

            data.forEach(function (row) {
                const l30Sales = dashboardParseNumber(row['L30 Sales'] || 0);
                const l30Orders = dashboardParseNumber(row['L30 Orders'] || 0);
                const qty = dashboardParseNumber(row['Qty'] || 0);
                const clicks = dashboardParseNumber(row['clicks'] || 0);
                const gprofitPercent = dashboardParseNumber(row['Gprofit%'] || 0);
                const groi = dashboardParseNumber(row['G Roi'] || 0);
                const npft = dashboardParseNumber(row['N PFT'] || 0);
                const nroi = dashboardParseNumber(row['N ROI'] || 0);
                const cogs = dashboardParseNumber(row['cogs'] || 0);
                const mapCount = dashboardParseNumber(row['Map'] || 0);
                const missCount = dashboardParseNumber(row['Miss'] || 0);
                const nmapCount = dashboardParseNumber(row['NMap'] || 0);
                const adSpend = dashboardParseNumber(row['Total Ad Spend'] || 0);
                const views = dashboardParseNumber(row['Total Views'] || 0);

                totalL30Sales += l30Sales;
                totalL30Orders += l30Orders;
                totalQty += qty;
                totalClicks += clicks;
                totalAdSpend += adSpend;
                totalViews += views;
                totalCogs += cogs;
                totalMap += mapCount;
                totalMiss += missCount;
                totalNMap += nmapCount;

                const profitAmount = (gprofitPercent / 100) * l30Sales;
                totalPft += profitAmount;
            });

            const avgGprofit = totalL30Sales > 0 ? (totalPft / totalL30Sales) * 100 : 0;
            const avgGroi = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;
            const avgAdsPercent = totalL30Sales > 0 ? (totalAdSpend / totalL30Sales) * 100 : 0;
            const avgNpft = avgGprofit - avgAdsPercent;
            const netProfit = totalPft - totalAdSpend;
            const avgNroi = totalCogs > 0 ? (netProfit / totalCogs) * 100 : 0;

            setDashText('total-channels', String(totalChannels));
            setDashText('total-l30-sales', '$' + Math.round(totalL30Sales).toLocaleString('en-US'));
            setDashText('total-l30-orders', Math.round(totalL30Orders).toLocaleString('en-US'));
            setDashText('total-qty', Math.round(totalQty).toLocaleString('en-US'));
            setDashText('total-clicks', Math.round(totalClicks).toLocaleString('en-US'));
            setDashText('avg-gprofit', avgGprofit.toFixed(1) + '%');
            setDashText('total-gross-pft', '$' + Math.round(totalPft).toLocaleString('en-US'));
            setDashText('avg-groi', Math.round(avgGroi) + '%');
            setDashText('total-ad-spend', '$' + Math.round(totalAdSpend).toLocaleString('en-US'));
            setDashText('total-views-badge', Math.round(totalViews).toLocaleString('en-US'));
            const cvrPct = totalViews > 0 ? (totalL30Orders / totalViews) * 100 : null;
            setDashText('cvr-pct-badge', cvrPct !== null ? cvrPct.toFixed(1) + '%' : '-');
            setDashText('total-pft', '$' + Math.round(netProfit).toLocaleString('en-US'));
            setDashText('avg-npft', avgNpft.toFixed(1) + '%');
            setDashText('avg-nroi', avgNroi.toFixed(1) + '%');
            setDashText('total-nmap', Math.round(totalNMap).toLocaleString('en-US'));
            setDashText('total-miss', Math.round(totalMiss).toLocaleString('en-US'));

            let ratingSum = 0, reviewsSum = 0, sellerRatingSum = 0, sellerReviewsSum = 0;
            data.forEach(function (row) {
                const r = dashboardParseNumber(row['Avg Rating'] || 0);
                const rev = dashboardParseNumber(row['Total Reviews'] || 0);
                const sr = dashboardParseNumber(row['Seller Avg Rating'] || 0);
                const srev = dashboardParseNumber(row['Seller Total Reviews'] || 0);
                if (!isNaN(r) && !isNaN(rev) && rev > 0) { ratingSum += r * rev; reviewsSum += rev; }
                if (!isNaN(sr) && !isNaN(srev) && srev > 0) { sellerRatingSum += sr * srev; sellerReviewsSum += srev; }
            });
            const weightedAvgRating = reviewsSum > 0 ? (ratingSum / reviewsSum).toFixed(1) : '0';
            const totalReviews = Math.round(reviewsSum).toLocaleString('en-US');
            const sellerWeightedAvg = sellerReviewsSum > 0 ? (sellerRatingSum / sellerReviewsSum).toFixed(1) : '0';
            const sellerTotalRev = Math.round(sellerReviewsSum).toLocaleString('en-US');
            setDashText('ratings-reviews-badge', weightedAvgRating + ' ★ | ' + totalReviews);
            setDashText('seller-ratings-reviews-badge', sellerWeightedAvg + ' ★ | ' + sellerTotalRev);
        }

        function applyChannelsMasterToDashboard(response) {
            if (!response || response.status !== 200 || !response.data) return;
            const rows = response.data;
            updateDashboardSummaryStats(rows);

            if (response.inventory_value_amazon != null) {
                const val = parseFloat(response.inventory_value_amazon) || 0;
                setDashText('inventory-value-amazon', Math.round(val).toLocaleString('en-US'));
            }
            if (response.inv_at_lp != null) {
                const val = parseFloat(response.inv_at_lp) || 0;
                setDashText('inv-at-lp', Math.round(val).toLocaleString('en-US'));
            }
            if (response.shopify_inv_sum != null) {
                const v = parseFloat(response.shopify_inv_sum) || 0;
                setDashText('dashboard-shopify-inv-sum', Math.round(v).toLocaleString('en-US'));
            }
            if (response.shopify_weighted_avg_lp != null) {
                const v = parseFloat(response.shopify_weighted_avg_lp) || 0;
                setDashText('dashboard-shopify-lp-avg', '$' + v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            }
            const tatEl = document.getElementById('tat-badge');
            if (tatEl && response.inventory_value_amazon != null && rows.length) {
                const invVal = parseFloat(response.inventory_value_amazon) || 0;
                let totalSales = 0;
                rows.forEach(function (row) {
                    totalSales += dashboardParseNumber(row['L30 Sales'] || 0);
                });
                const tat = totalSales > 0 ? invVal / totalSales : 0;
                tatEl.textContent = tat > 0 ? tat.toFixed(2) : '0';
            }
        }

        function loadDashboardFromChannels() {
            console.log('[Dashboard] Loading channels-master-data (summary + charts)...');
            fetch('/channels-master-data')
                .then(function (r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function (response) {
                    if (response.status !== 200 || !response.data) {
                        showErrorState();
                        return;
                    }
                    applyChannelsMasterToDashboard(response);
                    loadChannelSalesChart(response);
                    createYSalesBarChart(response);
                })
                .catch(function (error) {
                    console.error('[Dashboard] ❌ channels-master-data:', error);
                    showErrorState();
                });
        }

        function loadDashboardMetrics() {
            loadDashboardFromChannels();
        }

        function showErrorState() {
            var ids = ['total-channels', 'total-l30-sales', 'total-l30-orders', 'total-qty', 'avg-gprofit', 'total-gross-pft', 'avg-groi', 'total-ad-spend', 'total-views-badge', 'cvr-pct-badge', 'total-pft', 'avg-npft', 'avg-nroi', 'total-clicks', 'total-nmap', 'total-miss', 'inventory-value-amazon', 'inv-at-lp', 'dashboard-shopify-inv-sum', 'dashboard-shopify-lp-avg', 'tat-badge', 'ratings-reviews-badge', 'seller-ratings-reviews-badge', 'dashboard-chart-l30-total', 'dashboard-chart-l30-bar-total', 'dashboard-chart-y-total', 'dashboard-chart-y-bar-total'];
            ids.forEach(function (id) { setDashText(id, '—'); });
        }

        window.reloadDashboardMetrics = function () {
            loadDashboardFromChannels();
        };

        // Fullscreen chart modal functions
        let modalChartInstance = null;
        let ySalesModalChartInstance = null;

        window.closeYSalesChartModal = function () {
            const yModal = document.getElementById('ySalesChartModal');
            if (yModal) {
                yModal.classList.remove('active');
            }
            document.body.style.overflow = '';
            if (ySalesModalChartInstance) {
                ySalesModalChartInstance.destroy();
                ySalesModalChartInstance = null;
            }
            const yLeg = document.getElementById('ySalesPieLegendList');
            if (yLeg) {
                yLeg.innerHTML = '';
            }
        };

        window.openYSalesChartModal = function () {
            window.closeChartModal();
            const modal = document.getElementById('ySalesChartModal');
            if (!modal) {
                return;
            }
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            loadYSalesChartModal();
        };

        function loadYSalesChartModal() {
            fetch('/channels-master-data')
                .then(function (response) {
                    return response.json();
                })
                .then(function (result) {
                    if (!result.data || result.data.length === 0) {
                        console.warn('[Dashboard] No channel data for Y Sales modal');
                        return;
                    }
                    const rows = [];
                    result.data.forEach(function (row) {
                        const channelName = row['Channel '] || row['Channel'] || row.channel || row.name || row.Name || 'Unknown';
                        const y = parseFloat(row['Y Sales']);
                        const ySales = Number.isFinite(y) ? y : 0;
                        rows.push({ name: String(channelName).trim(), ySales: ySales });
                    });
                    rows.sort(function (a, b) {
                        return b.ySales - a.ySales;
                    });
                    const channels = rows.map(function (r) {
                        return r.name;
                    });
                    const ySalesValues = rows.map(function (r) {
                        return r.ySales;
                    });
                    if (ySalesModalChartInstance) {
                        ySalesModalChartInstance.destroy();
                    }
                    const ctx = document.getElementById('ySalesChartModalCanvas');
                    if (!ctx) {
                        return;
                    }
                    const n = channels.length;
                    const bgColors = pieSliceColors(n);
                    ySalesModalChartInstance = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: channels,
                            datasets: [{
                                label: 'Y Sales ($)',
                                data: ySalesValues,
                                backgroundColor: bgColors,
                                borderColor: pieSliceBorderColors(n),
                                borderWidth: 1,
                                hoverOffset: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            layout: {
                                padding: 12
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.85)',
                                    padding: 15,
                                    titleFont: { size: 14, weight: 'bold' },
                                    bodyFont: { size: 13 },
                                    callbacks: {
                                        label: function (context) {
                                            return channelPieTooltipLabel(context, ySalesValues);
                                        }
                                    }
                                }
                            }
                        }
                    });
                    fillChannelPieLegendList(channels, ySalesValues, bgColors, 'ySalesPieLegendList');
                    console.log('[Dashboard] ✅ Y Sales fullscreen chart created');
                })
                .catch(function (error) {
                    console.error('[Dashboard] ❌ Y Sales modal:', error);
                    const yLeg = document.getElementById('ySalesPieLegendList');
                    if (yLeg) {
                        yLeg.innerHTML = '';
                    }
                });
        }

        window.openChartModal = function() {
            window.closeYSalesChartModal();
            const modal = document.getElementById('chartModal');
            if (!modal) {
                return;
            }
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            loadChannelSalesChartModal();
        };

        window.closeChartModal = function() {
            const modal = document.getElementById('chartModal');
            if (modal) {
                modal.classList.remove('active');
            }
            document.body.style.overflow = '';
            
            // Destroy modal chart instance
            if (modalChartInstance) {
                modalChartInstance.destroy();
                modalChartInstance = null;
            }
            const legendUl = document.getElementById('channelPieLegendList');
            if (legendUl) {
                legendUl.innerHTML = '';
            }
        };

        function loadChannelSalesChartModal() {
            console.log('[Dashboard] Loading fullscreen channel chart...');
            
            fetch('/channels-master-data')
                .then(response => response.json())
                .then(result => {
                    if (!result.data || result.data.length === 0) {
                        console.warn('[Dashboard] No channel data available');
                        return;
                    }
                    
                    const channelData = [];
                    
                    result.data.forEach(row => {
                        const channelName = row['Channel '] || row['Channel'] || row.channel || row.name || row.Name || 'Unknown';
                        const sales = parseFloat(row['L30 Sales'] || row.l30_sales || row.L30Sales || 0);
                        channelData.push({ name: channelName, sales: sales });
                    });
                    
                    // Sort by sales (highest to lowest)
                    channelData.sort((a, b) => b.sales - a.sales);
                    
                    const channels = channelData.map(item => item.name);
                    const l30Sales = channelData.map(item => item.sales);
                    
                    // Destroy existing modal chart
                    if (modalChartInstance) {
                        modalChartInstance.destroy();
                    }
                    
                    const ctx = document.getElementById('channelSalesChartModal');
                    if (!ctx) return;
                    
                    const modalN = channels.length;
                    const bgColors = pieSliceColors(modalN);
                    modalChartInstance = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: channels,
                            datasets: [{
                                label: 'L30 Sales ($)',
                                data: l30Sales,
                                backgroundColor: bgColors,
                                borderColor: pieSliceBorderColors(modalN),
                                borderWidth: 1,
                                hoverOffset: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            layout: {
                                padding: 12
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.85)',
                                    padding: 15,
                                    titleFont: { size: 14, weight: 'bold' },
                                    bodyFont: { size: 13 },
                                    callbacks: {
                                        label: function (context) {
                                            return channelPieTooltipLabel(context, l30Sales);
                                        }
                                    }
                                }
                            }
                        }
                    });

                    fillChannelPieLegendList(channels, l30Sales, bgColors);
                    
                    console.log('[Dashboard] ✅ Fullscreen chart created');
                })
                .catch(error => {
                    console.error('[Dashboard] ❌ Error loading fullscreen chart:', error);
                    const legendUl = document.getElementById('channelPieLegendList');
                    if (legendUl) {
                        legendUl.innerHTML = '';
                    }
                });
        }

        function initDashboardPage() {
            if (document.getElementById('dashboard-summary-stats')) {
                loadDashboardFromChannels();
            }
            var pieRefresh = document.getElementById('dashboard-pie-refresh-btn');
            if (pieRefresh) {
                pieRefresh.addEventListener('click', function () {
                    loadChannelSalesChart();
                });
            }
            var pieFs = document.getElementById('dashboard-pie-fullscreen-btn');
            if (pieFs) {
                pieFs.addEventListener('click', function () {
                    window.openChartModal();
                });
            }
            var yRefresh = document.getElementById('dashboard-y-refresh-btn');
            if (yRefresh) {
                yRefresh.addEventListener('click', function () {
                    createYSalesBarChart();
                });
            }
            var yFs = document.getElementById('dashboard-y-fullscreen-btn');
            if (yFs) {
                yFs.addEventListener('click', function () {
                    window.openYSalesChartModal();
                });
            }
            var l30BarRef = document.getElementById('dashboard-l30-bar-refresh-btn');
            if (l30BarRef) {
                l30BarRef.addEventListener('click', function () {
                    loadChannelSalesChart();
                });
            }
            var yBarRef = document.getElementById('dashboard-y-bar-refresh-btn');
            if (yBarRef) {
                yBarRef.addEventListener('click', function () {
                    createYSalesBarChart();
                });
            }
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDashboardPage);
        } else {
            initDashboardPage();
        }

        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeChartModal();
                if (typeof window.closeYSalesChartModal === 'function') {
                    window.closeYSalesChartModal();
                }
                closeMenuModal();
            }
        });

        // ============================================
        // MENU MODAL SYSTEM
        // ============================================

        // Menu data structure with routes
        const menuData = {
            'Purchase': {
                icon: '🛒',
                color: '#1e3a5f',
                items: [
                    { name: 'Categories', icon: '📦', route: '/purchase-masters/categories' },
                    { name: 'Suppliers', icon: '🏢', route: '/purchase-masters/suppliers' },
                    { name: 'MIP', icon: '⚙️', route: '/purchase-masters/mfrg-in-progress' }
                ]
            },
            'Tasks': {
                icon: '✓',
                color: '#0891b2',
                items: [
                    { name: 'My Tasks', icon: '📋', route: '{{ url('tasks') }}' },
                    { name: 'Task Summary', icon: '📊', route: '{{ route('tasks.summary') }}' },
                    { name: 'Team Tasks', icon: '👥', route: '/tasks/team-tasks' },
                    { name: 'Completed Tasks', icon: '✅', route: '/tasks/completed' }
                ]
            },
            'My Team': {
                icon: '👥',
                color: '#059669',
                items: [
                    { name: 'Team Members', icon: '👤', route: '/team/members' },
                    { name: 'Performance', icon: '📊', route: '/team/performance' },
                    { name: 'Goals & Targets', icon: '🎯', route: '/team/goals' }
                ]
            },
            'Inventory': {
                icon: '📦',
                color: '#ea580c',
                items: [
                    { name: 'Stock Levels', icon: '📈', route: '/inventory/stock-levels' },
                    { name: 'Valuation', icon: '💰', route: '/inventory/valuation' }
                ]
            },
            'Operations': {
                icon: '⏰',
                color: '#db2777',
                items: [
                    { name: 'Shipping Analysis', icon: '🚚', route: '/operations/shipping' },
                    { name: 'Reviews Management', icon: '⭐', route: '/operations/reviews' },
                    { name: 'Customer Care', icon: '👥', route: '/operations/customer-care' }
                ]
            },
            'Human Resources': {
                icon: '👨‍💼',
                color: '#9333ea',
                items: [
                    { name: 'Employee Directory', icon: '👥', route: '/hr/employees' },
                    { name: 'Attendance Tracking', icon: '📅', route: '/hr/attendance' },
                    { name: 'Payroll Management', icon: '💼', route: '/hr/payroll' }
                ]
            },
            'Software & IT': {
                icon: '💻',
                color: '#0d9488',
                items: [
                    { name: 'System Management', icon: '🖥️', route: '/it/systems' },
                    { name: 'Maintenance', icon: '🔧', route: '/it/maintenance' },
                    { name: 'Analytics Dashboard', icon: '📊', route: '/it/analytics' }
                ]
            },
            'Pricing': {
                icon: '💵',
                color: '#d97706',
                items: [
                    { name: 'Price Lists', icon: '💰', route: '/pricing/lists' },
                    { name: 'Pricing Trends', icon: '📈', route: '/pricing/trends' }
                ]
            },
            'Advertisements': {
                icon: '📢',
                color: '#4b5563',
                items: [
                    { name: 'Digital Ads', icon: '📱', route: '/ads/digital' },
                    { name: 'Campaigns', icon: '📺', route: '/ads/campaigns' },
                    { name: 'ROI Analysis', icon: '📊', route: '/ads/roi' }
                ]
            },
            'Content': {
                icon: '📝',
                color: '#dc2626',
                items: [
                    { name: 'Articles', icon: '✍️', route: '/content/articles' },
                    { name: 'Media Library', icon: '🎨', route: '/content/media' },
                    { name: 'Content Schedule', icon: '📅', route: '/content/schedule' }
                ]
            },
            'Marketing': {
                icon: '🎯',
                color: '#2563eb',
                items: [
                    { name: 'Email Marketing', icon: '📧', route: '/marketing/email' },
                    { name: 'Campaigns', icon: '🎯', route: '/marketing/campaigns' },
                    { name: 'Analytics', icon: '📊', route: '/marketing/analytics' }
                ]
            },
            'Social Media': {
                icon: '📱',
                color: '#d97706',
                items: [
                    { name: 'Facebook', icon: '📘', route: '/social/facebook' },
                    { name: 'Instagram', icon: '📷', route: '/social/instagram' },
                    { name: 'Twitter', icon: '🐦', route: '/social/twitter' }
                ]
            },
            'Videos': {
                icon: '🎬',
                color: '#ea580c',
                items: [
                    { name: 'Video Library', icon: '🎥', route: '/videos/library' },
                    { name: 'Views Analytics', icon: '▶️', route: '/videos/analytics' },
                    { name: 'Engagement', icon: '👍', route: '/videos/engagement' }
                ]
            },
            'Logistics': {
                icon: '🚚',
                color: '#4f46e5',
                items: [
                    { name: 'Shipments', icon: '📦', route: '/logistics/shipments' },
                    { name: 'Tracking', icon: '🚛', route: '/logistics/tracking' },
                    { name: 'Delivery Status', icon: '📍', route: '/logistics/delivery' }
                ]
            }
        };

        // Open menu modal
        function openModal(category) {
            const modal = document.getElementById('menuModal');
            const modalIcon = document.getElementById('modalIcon');
            const modalCategory = document.getElementById('modalCategory');
            const menuGrid = document.getElementById('menuGrid');
            const menuSearch = document.getElementById('menuSearch');

            // Set modal title and icon
            const categoryData = menuData[category];
            if (categoryData) {
                modalIcon.textContent = categoryData.icon;
                modalCategory.textContent = category;
                
                // Clear search
                menuSearch.value = '';
                
                // Generate menu items
                let html = '';
                if (categoryData.items && categoryData.items.length > 0) {
                    categoryData.items.forEach(item => {
                        html += `
                            <a href="${item.route}" class="menu-item">
                                <div class="menu-item-icon">${item.icon}</div>
                                <div class="menu-item-text">${item.name}</div>
                                <i class="ri-arrow-right-line menu-item-arrow"></i>
                            </a>
                        `;
                    });
                } else {
                    html = `
                        <div class="no-items">
                            <i class="ri-inbox-line"></i>
                            <p>No items available in this category</p>
                        </div>
                    `;
                }
                
                menuGrid.innerHTML = html;
            }
            
            // Show modal
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Close menu modal
        function closeMenuModal() {
            const modal = document.getElementById('menuModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Filter menu items
        function filterMenuItems() {
            const searchInput = document.getElementById('menuSearch');
            const filter = searchInput.value.toLowerCase();
            const menuItems = document.querySelectorAll('.menu-item');
            
            menuItems.forEach(item => {
                const text = item.querySelector('.menu-item-text').textContent.toLowerCase();
                if (text.includes(filter)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('menuModal');
            if (e.target === modal) {
                closeMenuModal();
            }
        });
    </script>
@endsection

