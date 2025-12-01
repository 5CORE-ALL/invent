<style>
    .tabulator .tabulator-header {
        background: linear-gradient(90deg, #D8F3F3 0%, #D8F3F3 100%);
        border-bottom: 1px solid #403f3f;
        box-shadow: 0 4px 16px rgba(37, 99, 235, 0.10);
    }

    .tabulator .tabulator-header .tabulator-col {
        text-align: center;
        background: #D8F3F3;
        border-right: 1px solid #262626;
        padding: 16px 15px;
        font-weight: 700;
        color: #1e293b;
        font-size: 1.08rem;
        letter-spacing: 0.02em;
        transition: background 0.2s;
        white-space: nowrap;
        overflow: visible;
    }

    .tabulator .tabulator-header .tabulator-col:hover {
        background: #D8F3F3;
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
        border-right: 1px solid #262626;
        border-bottom: 1px solid #262626;
        font-size: 1rem;
        color: #22223b;
        vertical-align: middle;
        transition: background 0.18s, color 0.18s;
    }

    .tabulator .tabulator-cell:focus {
        outline: 1px solid #262626;
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
        border-top: 1px solid #262626;
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

    .green-bg {
        color: #05bd30 !important;
    }

    .pink-bg {
        color: #ff01d0 !important;
    }

    .red-bg {
        color: #ff2727 !important;
    }
</style>
