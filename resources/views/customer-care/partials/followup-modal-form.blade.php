{{--
  Channels from channel_master (active only) — same list as /all-marketplace-master.
  // Future: Replace with API-based channel fetch
--}}
<form id="followupForm" novalidate>
    <input type="hidden" name="edit_id" id="edit_id" value="">

    <h6 class="text-muted text-uppercase small mb-3">Ticket info</h6>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Ticket ID</label>
            <input type="text" class="form-control bg-light" id="ticket_id_display" readonly tabindex="-1"
                value="" placeholder="—">
            <input type="hidden" name="ticket_id" id="ticket_id" value="">
            <small class="text-muted" id="ticket_id_hint">Generated automatically when you save (e.g. TKT-000001).</small>
        </div>
        <x-followup.input-field label="Order ID" name="order_id" />
        <div class="col-md-6 mb-3">
            <label for="sku" class="form-label">SKU</label>
            <input type="text" name="sku" id="sku" class="form-control" list="followup_sku_datalist"
                maxlength="128" placeholder="Type to search product master" autocomplete="off">
            <datalist id="followup_sku_datalist"></datalist>
            <small class="text-muted d-block mt-1">Suggestions load from <strong>Product Master</strong> as you type.</small>
            <div class="invalid-feedback" data-error-for="sku"></div>
        </div>
        <div class="col-md-6 mb-3">
            <label for="channel_master_id" class="form-label">Channel</label>
            <select name="channel_master_id" id="channel_master_id" class="form-select">
                <option value="">— Select —</option>
                @foreach ($channels as $channel)
                    <option value="{{ $channel->id }}">{{ $channel->name }}</option>
                @endforeach
            </select>
            <div class="invalid-feedback d-block" data-error-for="channel_master_id"></div>
        </div>
        <x-followup.input-field label="Customer name" name="customer_name" />
        <x-followup.input-field label="Email" name="email" type="email" />
        <x-followup.input-field label="Phone" name="phone" type="tel" />
    </div>

    <h6 class="text-muted text-uppercase small mb-3 mt-2">Issue info</h6>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label for="issue_type" class="form-label">Issue type<span class="text-danger">*</span></label>
            <select name="issue_type" id="issue_type" class="form-select" required>
                <option value="Payment">Payment</option>
                <option value="Delivery">Delivery</option>
                <option value="Return">Return</option>
                <option value="Refund">Refund</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div class="col-md-4 mb-3">
            <label for="followup_status" class="form-label">Status<span class="text-danger">*</span></label>
            <select name="status" id="followup_status" class="form-select" required>
                <option value="Pending">Pending</option>
                <option value="In Progress">In Progress</option>
                <option value="Resolved">Resolved</option>
                <option value="Escalated">Escalated</option>
            </select>
        </div>
        <div class="col-md-4 mb-3">
            <label for="priority" class="form-label">Priority<span class="text-danger">*</span></label>
            <select name="priority" id="priority" class="form-select" required>
                <option value="Low">Low</option>
                <option value="Medium">Medium</option>
                <option value="High">High</option>
                <option value="Urgent">Urgent</option>
            </select>
        </div>
    </div>

    <h6 class="text-muted text-uppercase small mb-3">Follow-up tracking</h6>
    <div class="row">
        <x-followup.input-field label="Follow-up date" name="followup_date" type="date" :required="true" />
        <x-followup.input-field label="Follow-up time" name="followup_time" type="time" />
        <div class="col-md-6 mb-3">
            <label for="next_followup_at" class="form-label">Next follow-up (date &amp; time)</label>
            <input type="datetime-local" name="next_followup_at" id="next_followup_at" class="form-control">
        </div>
        <div class="col-md-6 mb-3">
            <label for="assigned_executive" class="form-label">Assigned executive</label>
            <div class="input-group">
                <input type="text" name="assigned_executive" id="assigned_executive" class="form-control"
                    list="assigned_executive_datalist" autocomplete="off"
                    placeholder="Choose, type, or add with +">
                <button type="button" class="btn btn-outline-primary px-3" id="btnAddExecutiveToList"
                    title="Add this name to the executive list" aria-label="Add executive to list">
                    <i class="mdi mdi-plus"></i>
                </button>
            </div>
            <datalist id="assigned_executive_datalist">
                @foreach ($defaultExecutives ?? [] as $ex)
                    <option value="{{ $ex }}"></option>
                @endforeach
            </datalist>
            <small class="text-muted d-block mt-1">Type a new name, then tap <i class="mdi mdi-plus"
                    aria-hidden="true"></i> to keep it in the list and filters.</small>
            <div class="invalid-feedback" data-error-for="assigned_executive"></div>
        </div>
    </div>

    <h6 class="text-muted text-uppercase small mb-3">Communication</h6>
    <div class="row">
        <div class="col-12 mb-3">
            <label for="comments" class="form-label">Notes</label>
            <textarea name="comments" id="comments" class="form-control" rows="2"></textarea>
        </div>
        <div class="col-12 mb-3">
            <label for="internal_remarks" class="form-label">Internal remarks</label>
            <textarea name="internal_remarks" id="internal_remarks" class="form-control" rows="2"></textarea>
        </div>
        <div class="col-12 mb-3">
            <label for="reference_link" class="form-label">Reference link (opens in new tab)</label>
            <input type="url" name="reference_link" id="reference_link" class="form-control" placeholder="https://">
            <div class="invalid-feedback d-block" data-error-for="reference_link"></div>
        </div>
    </div>
</form>
