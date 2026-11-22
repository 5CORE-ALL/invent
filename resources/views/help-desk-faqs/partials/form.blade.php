@php($edit = $edit ?? false)
@php($prefix = ($edit ? 'edit' : 'new'))
{{-- Next-gen FAQ form: two columns on desktop so every input fits on one screen. --}}
<div class="row g-3 fe-form">
    {{-- ───────────── Left column: content ───────────── --}}
    <div class="col-lg-7 d-flex flex-column gap-3">
        <section class="fe-section">
            <div class="fe-section-title"><i class="ri-question-answer-line"></i> Question &amp; Answer</div>
            <div class="row g-2">
                <div class="col-12">
                    <label class="fe-label">Group</label>
                    <input type="text" name="group_name" class="form-control form-control-sm" placeholder="Enter the group">
                </div>
                <div class="col-12">
                    <label class="fe-label">FAQ <span class="text-danger">*</span></label>
                    <textarea name="faq" class="form-control form-control-sm" rows="2" placeholder="Enter the question" required></textarea>
                </div>
                <div class="col-12">
                    <label class="fe-label">Answers</label>
                    <textarea name="answers" class="form-control form-control-sm" rows="3" placeholder="Enter the answer"></textarea>
                </div>
            </div>
        </section>

        <section class="fe-section">
            <div class="fe-section-title"><i class="ri-flashlight-line"></i> Actions</div>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="fe-label">Action</label>
                    <textarea name="action" class="form-control form-control-sm" rows="2" placeholder="Enter the action"></textarea>
                </div>
                <div class="col-md-4">
                    <label class="fe-label">CA <span class="text-muted text-lowercase fw-normal">(corrective)</span></label>
                    <textarea name="ca" class="form-control form-control-sm" rows="2" placeholder="Enter the corrective action"></textarea>
                </div>
                <div class="col-md-4">
                    <label class="fe-label">+ Action</label>
                    <textarea name="plus_action" class="form-control form-control-sm" rows="2" placeholder="Enter the additional action"></textarea>
                </div>
                <div class="col-12">
                    <label class="fe-label">Messages</label>
                    <textarea name="messages" class="form-control form-control-sm" rows="3" placeholder="Enter a message (no character limit)"></textarea>
                </div>
            </div>
        </section>
    </div>

    {{-- ───────────── Right column: meta ───────────── --}}
    <div class="col-lg-5 d-flex flex-column gap-3">
        <section class="fe-section">
            <div class="fe-section-title"><i class="ri-building-line"></i> Department</div>
            <div class="dept-checkboxes">
                <label class="fe-chip fe-chip-all">
                    <input class="dept-all" type="checkbox" name="dept[]" value="all" id="dept_{{ $prefix }}_all">
                    <span>All</span>
                </label>
                <div class="fe-chip-grid">
                    @foreach ($departments as $dept)
                        <label class="fe-chip" for="dept_{{ $prefix }}_{{ $dept->id }}">
                            <input class="dept-one" type="checkbox" name="dept[]" value="{{ $dept->id }}" id="dept_{{ $prefix }}_{{ $dept->id }}">
                            <span>{{ $dept->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <small class="text-muted d-block mt-1" style="font-size:0.7rem;">Pick one or more. "All" shows this FAQ to everyone.</small>
        </section>

        <section class="fe-section">
            <div class="fe-section-title"><i class="ri-price-tag-3-line"></i> Details</div>
            <div class="row g-2">
                <div class="col-6">
                    <label class="fe-label">Type/Variant</label>
                    <textarea name="type_variant" class="form-control form-control-sm" rows="2" placeholder="Type / variant"></textarea>
                </div>
                <div class="col-6">
                    <label class="fe-label">What</label>
                    <textarea name="what" class="form-control form-control-sm" rows="2" placeholder="What"></textarea>
                </div>
            </div>
        </section>

        <section class="fe-section">
            <div class="fe-section-title"><i class="ri-links-line"></i> Resources</div>
            <div class="row g-2">
                <div class="col-6">
                    <label class="fe-label">Link</label>
                    <input type="text" name="link" class="form-control form-control-sm" placeholder="https://...">
                </div>
                <div class="col-6">
                    <label class="fe-label">Link 2</label>
                    <input type="text" name="link2" class="form-control form-control-sm" placeholder="https://...">
                </div>
                <div class="col-6">
                    <label class="fe-label">SOP</label>
                    <input type="text" name="sop" class="form-control form-control-sm" placeholder="https://...">
                </div>
                <div class="col-6">
                    <label class="fe-label">Video</label>
                    <input type="text" name="video" class="form-control form-control-sm" placeholder="https://...">
                </div>
            </div>
        </section>
    </div>
</div>
