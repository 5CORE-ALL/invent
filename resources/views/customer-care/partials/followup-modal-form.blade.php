{{--
  Channels from channel_master (active only) — same list as /all-marketplace-master.
  // Future: Replace with API-based channel fetch
--}}
<form id="followupForm" novalidate>
    <input type="hidden" name="edit_id" id="edit_id" value="">
    <input type="hidden" name="status" id="followup_status" value="Pending">

    <h6 class="text-muted text-uppercase small mb-3">Ticket info</h6>
    <div class="row">
        <input type="hidden" name="ticket_id" id="ticket_id" value="">
        <div class="col-md-6 mb-3 d-none" aria-hidden="true">
            <label class="form-label">Ticket ID</label>
            <input type="text" class="form-control bg-light" id="ticket_id_display" readonly tabindex="-1"
                value="" placeholder="—">
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
    </div>

    <h6 class="text-muted text-uppercase small mb-3">Communication</h6>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="comments" class="form-label">Follow up issue</label>
            <textarea name="comments" id="comments" class="form-control" rows="3"></textarea>
        </div>
        <div class="col-md-6 mb-3">
            <label for="reference_link" class="form-label">Reference link (opens in new tab)</label>
            <input type="url" name="reference_link" id="reference_link" class="form-control" placeholder="https://">
            <div class="invalid-feedback d-block" data-error-for="reference_link"></div>
        </div>
        <div class="col-12 mb-3">
            <label for="followup_image" class="form-label">
                Attach image
                <small class="text-muted">(optional &mdash; JPG / PNG / WEBP / GIF, must be under <strong>1 MB</strong>)</small>
            </label>
            <input type="file" name="image" id="followup_image"
                   class="form-control"
                   accept="image/png,image/jpeg,image/jpg,image/webp,image/gif">
            <div class="invalid-feedback d-block" data-error-for="image"></div>

            <div id="followup_image_preview_wrap" class="mt-2 d-none">
                <div class="d-flex align-items-start gap-3 p-2 border rounded bg-light">
                    <img id="followup_image_preview"
                         src="" alt="Selected image preview"
                         style="max-width:120px;max-height:120px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;">
                    <div class="flex-grow-1 small">
                        <div class="text-muted">
                            <span id="followup_image_preview_label">Selected image</span>
                        </div>
                        <div class="text-muted" id="followup_image_preview_meta"></div>
                        <div class="mt-2 d-flex flex-wrap gap-2">
                            <a href="#" id="followup_image_preview_open" target="_blank" rel="noopener"
                               class="btn btn-sm btn-outline-primary d-none">
                                <i class="mdi mdi-open-in-new"></i> Open full size
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="followup_image_remove_btn">
                                <i class="mdi mdi-delete"></i> Remove image
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            {{-- Set to "1" by Remove image button when editing an existing image --}}
            <input type="hidden" name="remove_image" id="followup_remove_image" value="">
        </div>
    </div>
</form>
