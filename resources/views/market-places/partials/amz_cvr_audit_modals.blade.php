{{-- Shared Amz CVR Audit modals — same tables as /amz-cvr-issues (amz_cvr_audit_histories, amz_cvr_issue_types) --}}
<style>
    #amzCvrAuditModal.modal {
        padding: 0 !important;
        z-index: 2100 !important;
    }
    #amzCvrAuditModal .modal-dialog,
    #amzCvrAuditModal .amz-cvr-audit-modal-dialog {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        width: 100vw !important;
        max-width: 100vw !important;
        height: auto !important;
        min-height: 20vh !important;
        max-height: 100vh !important;
        margin: 0 !important;
        padding: 0 !important;
        transform: none !important;
    }
    #amzCvrAuditModal .modal-content {
        width: 100% !important;
        max-width: 100% !important;
        height: auto !important;
        min-height: 20vh !important;
        max-height: 100vh !important;
        border: 0 !important;
        border-radius: 0 !important;
        display: flex !important;
        flex-direction: column !important;
    }
    #amzCvrAuditModal .modal-body {
        flex: 1 1 auto !important;
        height: auto !important;
        max-height: none !important;
        overflow-y: visible !important;
    }
    #amzCvrAuditModal.amz-cvr-audit-tall .modal-dialog {
        height: 100vh !important;
    }
    #amzCvrAuditModal.amz-cvr-audit-tall .modal-content {
        height: 100vh !important;
    }
    #amzCvrAuditModal.amz-cvr-audit-tall .modal-body {
        overflow-y: auto !important;
    }
    .amz-cvr-audit-btn {
        border: 1px solid #2f9e44;
        background: #fff;
        color: #2f9e44;
        border-radius: 6px;
        width: 28px;
        height: 28px;
        padding: 0;
        line-height: 1;
        cursor: pointer;
        font-weight: 700;
    }
    .amz-cvr-audit-btn:hover {
        background: #ebfbee;
    }
    .tabulator .tabulator-header .tabulator-col[tabulator-field="audit"] {
        background: #20c997 !important;
    }
    .tabulator .tabulator-header .tabulator-col[tabulator-field="audit"] .tabulator-col-content,
    .tabulator .tabulator-header .tabulator-col[tabulator-field="audit"] .tabulator-col-title {
        background: #20c997 !important;
        color: #000 !important;
    }
</style>

@include('market-places.partials._amz_cvr_audit_modal_markup')
