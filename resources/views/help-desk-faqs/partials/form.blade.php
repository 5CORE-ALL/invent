@php($edit = $edit ?? false)
<div class="row g-3">
    <div class="col-12">
        <label class="form-label">FAQ <span class="text-danger">*</span></label>
        <textarea name="faq" class="form-control" rows="2" placeholder="Enter the question" required></textarea>
    </div>

    <div class="col-12">
        <label class="form-label">Answers</label>
        <textarea name="answers" class="form-control" rows="3" placeholder="Enter the answer"></textarea>
    </div>

    @php($prefix = ($edit ? 'edit' : 'new'))
    <div class="col-12">
        <label class="form-label d-block">DEPT</label>
        <div class="border rounded p-2 dept-checkboxes">
            <div class="form-check mb-1">
                <input class="form-check-input dept-all" type="checkbox" name="dept[]" value="all" id="dept_{{ $prefix }}_all">
                <label class="form-check-label fw-semibold" for="dept_{{ $prefix }}_all">All</label>
            </div>
            <hr class="my-2">
            <div class="row g-1">
                @foreach ($departments as $dept)
                    <div class="col-6 col-md-4">
                        <div class="form-check">
                            <input class="form-check-input dept-one" type="checkbox" name="dept[]" value="{{ $dept->id }}" id="dept_{{ $prefix }}_{{ $dept->id }}">
                            <label class="form-check-label" for="dept_{{ $prefix }}_{{ $dept->id }}">{{ $dept->name }}</label>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <small class="text-muted">Tick one or more departments. Choose "All" to show this FAQ to everyone.</small>
    </div>

    <div class="col-md-6">
        <label class="form-label">Link</label>
        <input type="text" name="link" class="form-control" placeholder="https://...">
    </div>
    <div class="col-md-6">
        <label class="form-label">Link 2</label>
        <input type="text" name="link2" class="form-control" placeholder="https://...">
    </div>
    <div class="col-md-6">
        <label class="form-label">SOP</label>
        <input type="text" name="sop" class="form-control" placeholder="https://...">
    </div>
    <div class="col-md-6">
        <label class="form-label">Video</label>
        <input type="text" name="video" class="form-control" placeholder="https://...">
    </div>
    <div class="col-12">
        <label class="form-label">Action</label>
        <textarea name="action" class="form-control" rows="2" placeholder="Enter the action"></textarea>
    </div>
    <div class="col-12">
        <label class="form-label">CA <span class="text-muted">(Corrective Action)</span></label>
        <textarea name="ca" class="form-control" rows="2" placeholder="Enter the corrective action"></textarea>
    </div>
    <div class="col-12">
        <label class="form-label">+ Action</label>
        <textarea name="plus_action" class="form-control" rows="2" placeholder="Enter the additional action"></textarea>
    </div>
    <div class="col-12">
        <label class="form-label">Messages <span class="text-muted">(max 200 characters)</span></label>
        <textarea name="messages" class="form-control" rows="2" maxlength="200" placeholder="Enter a short message"></textarea>
    </div>
</div>
