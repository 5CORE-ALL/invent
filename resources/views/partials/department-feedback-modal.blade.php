{{--
    One department per day. The popup opens once on that department's weekday
    until the user sends feedback or skips the week.
--}}
@auth
@php
    $dfbPrompt = request()->routeIs('feedback.index')
        ? null
        : \App\Models\DepartmentFeedback::promptFor((int) auth()->id());
@endphp
@if($dfbPrompt)
<style>
    #departmentFeedbackModal .modal-dialog {
        max-width: 480px;
    }
    #departmentFeedbackModal .modal-content {
        border: 0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 22px 50px rgba(15, 23, 42, 0.18);
    }
    #departmentFeedbackModal .dfb-pop-accent {
        height: 4px;
        background: var(--dfb-accent, #4338ca);
    }
    #departmentFeedbackModal .modal-header {
        border: 0;
        padding: 18px 20px 0;
        align-items: flex-start;
    }
    #departmentFeedbackModal .dfb-pop-kicker {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 4px;
    }
    #departmentFeedbackModal .modal-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    #departmentFeedbackModal .modal-title i {
        color: var(--dfb-accent, #4338ca);
        font-size: 1.25rem;
    }
    #departmentFeedbackModal .modal-body {
        padding: 14px 20px 8px;
    }
    #departmentFeedbackModal .dfb-pop-question {
        margin: 0 0 12px;
        color: #334155;
        font-size: 14px;
        line-height: 1.45;
    }
    #departmentFeedbackModal .dfb-pop-options {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 14px;
    }
    #departmentFeedbackModal .dfb-pop-option {
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: #64748b;
        border-radius: 999px;
        padding: 3px 8px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.3;
    }
    #departmentFeedbackModal .dfb-pop-option.is-current {
        background: #fff;
        color: var(--dfb-accent, #4338ca);
        border-color: var(--dfb-accent, #4338ca);
    }
    #departmentFeedbackModal .dfb-pop-option small {
        font-weight: 600;
        color: #94a3b8;
        font-size: 10px;
        margin-left: 4px;
    }
    #departmentFeedbackModal .dfb-pop-option.is-current small {
        color: var(--dfb-accent, #4338ca);
        opacity: 0.75;
    }
    #departmentFeedbackModal .dfb-pop-stars {
        display: flex;
        gap: 8px;
        margin-bottom: 4px;
    }
    #departmentFeedbackModal .dfb-pop-stars label {
        width: 44px;
        height: 40px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        display: grid;
        place-items: center;
        cursor: pointer;
        color: #cbd5e1;
        font-size: 18px;
        background: #fff;
        margin: 0;
    }
    #departmentFeedbackModal .dfb-pop-stars input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    #departmentFeedbackModal .dfb-pop-stars label.is-on {
        color: #d97706;
        border-color: #fcd34d;
        background: #fffbeb;
    }
    #departmentFeedbackModal .dfb-pop-scale {
        display: flex;
        justify-content: space-between;
        color: #94a3b8;
        font-size: 11px;
        font-weight: 600;
        margin-bottom: 12px;
    }
    #departmentFeedbackModal textarea {
        resize: vertical;
        min-height: 88px;
        font-size: 14px;
    }
    #departmentFeedbackModal .dfb-pop-error {
        color: #b91c1c;
        font-size: 12px;
        min-height: 18px;
        margin-top: 6px;
    }
    #departmentFeedbackModal .modal-footer {
        border: 0;
        padding: 4px 20px 18px;
        justify-content: space-between;
    }
    #departmentFeedbackModal .dfb-pop-thanks {
        padding: 28px 8px 12px;
        text-align: center;
        color: #0f172a;
    }
    #departmentFeedbackModal .dfb-pop-thanks i {
        font-size: 32px;
        color: #059669;
    }
</style>

<div class="modal fade" id="departmentFeedbackModal" tabindex="-1" aria-labelledby="departmentFeedbackTitle" aria-hidden="true"
     style="--dfb-accent: {{ $dfbPrompt['accent'] }};">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="dfb-pop-accent"></div>
            <div class="modal-header">
                <div>
                    <div class="dfb-pop-kicker">{{ $dfbPrompt['day'] }} · once this week</div>
                    <h5 class="modal-title" id="departmentFeedbackTitle">
                        <i class="{{ $dfbPrompt['icon'] }}"></i>
                        {{ $dfbPrompt['label'] }} feedback
                    </h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="departmentFeedbackForm">
                <div class="modal-body">
                    <div class="dfb-pop-options" aria-label="Departments this week">
                        @foreach($dfbPrompt['options'] as $option)
                            <span class="dfb-pop-option {{ $option['current'] ? 'is-current' : '' }}">
                                {{ $option['label'] }}
                                <small>{{ $option['current'] ? 'Today' : $option['day'] }}</small>
                            </span>
                        @endforeach
                    </div>
                    <p class="dfb-pop-question">{{ $dfbPrompt['question'] }}</p>
                    <div class="dfb-pop-stars" id="dfbPopupStars" data-value="0">
                        @for($star = 1; $star <= 5; $star++)
                            <label title="{{ $star }} of 5">
                                <input type="radio" name="dfb_popup_rating" value="{{ $star }}">
                                <i class="ri-star-fill"></i>
                            </label>
                        @endfor
                    </div>
                    <div class="dfb-pop-scale"><span>Needs work</span><span>Excellent</span></div>
                    <label class="visually-hidden" for="dfbPopupComment">Feedback</label>
                    <textarea class="form-control" id="dfbPopupComment" maxlength="2000" placeholder="What should this department keep doing, or change?"></textarea>
                    <div class="dfb-pop-error" id="dfbPopupError" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" id="dfbPopupSkip">Skip this week</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3" id="dfbPopupSend">Send feedback</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var prompt = @json($dfbPrompt);
    var modalEl = document.getElementById('departmentFeedbackModal');
    if (!prompt || !modalEl) return;

    var storageKey = 'dept-feedback-shown:' + @json((int) auth()->id()) + ':' + prompt.date + ':' + prompt.key;
    function alreadyShown() {
        try { return localStorage.getItem(storageKey) === '1'; } catch (e) { return false; }
    }
    function markShown() {
        try { localStorage.setItem(storageKey, '1'); } catch (e) {}
    }
    if (alreadyShown()) return;

    var stars = document.getElementById('dfbPopupStars');
    var labels = stars ? Array.prototype.slice.call(stars.querySelectorAll('label')) : [];
    function paint(value) {
        labels.forEach(function (label, index) {
            label.classList.toggle('is-on', index < value);
        });
    }
    labels.forEach(function (label, index) {
        var input = label.querySelector('input');
        label.addEventListener('mouseenter', function () { paint(index + 1); });
        label.addEventListener('mouseleave', function () {
            var checked = stars.querySelector('input:checked');
            paint(checked ? parseInt(checked.value, 10) : 0);
        });
        if (input) {
            input.addEventListener('change', function () { paint(parseInt(input.value, 10)); });
        }
    });

    var form = document.getElementById('departmentFeedbackForm');
    var errorEl = document.getElementById('dfbPopupError');
    var sendBtn = document.getElementById('dfbPopupSend');
    var skipBtn = document.getElementById('dfbPopupSkip');
    var token = document.querySelector('meta[name="csrf-token"]');
    token = token ? token.getAttribute('content') : '';

    function setBusy(busy) {
        sendBtn.disabled = busy;
        skipBtn.disabled = busy;
    }
    function showError(message) {
        errorEl.textContent = message || '';
    }
    function firstError(payload) {
        if (!payload || !payload.errors) return payload && payload.message ? payload.message : 'Could not save feedback.';
        var keys = Object.keys(payload.errors);
        if (!keys.length) return 'Could not save feedback.';
        var list = payload.errors[keys[0]];
        return list && list[0] ? list[0] : 'Could not save feedback.';
    }
    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token
            },
            body: JSON.stringify(body)
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (payload) {
                if (!res.ok) {
                    var err = new Error(firstError(payload));
                    err.payload = payload;
                    throw err;
                }
                return payload;
            });
        });
    }
    function thank(message) {
        form.innerHTML = '<div class="dfb-pop-thanks"><i class="ri-checkbox-circle-line"></i><p class="mt-2 mb-0 fw-semibold">' + message + '</p></div>';
        setTimeout(function () {
            if (window.bootstrap && bootstrap.Modal) {
                var instance = bootstrap.Modal.getInstance(modalEl);
                if (instance) instance.hide();
            }
        }, 900);
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        showError('');
        var checked = stars.querySelector('input:checked');
        var comment = document.getElementById('dfbPopupComment').value.trim();
        if (!checked) {
            showError('Choose a rating from 1 to 5.');
            return;
        }
        if (comment.length < 4) {
            showError('Add a short note, at least a few words.');
            return;
        }
        setBusy(true);
        post(@json(route('feedback.store')), {
            department: prompt.key,
            rating: parseInt(checked.value, 10),
            comment: comment
        }).then(function (payload) {
            thank(payload.message || 'Feedback saved.');
        }).catch(function (err) {
            setBusy(false);
            showError(err.message || 'Could not save feedback.');
        });
    });

    skipBtn.addEventListener('click', function () {
        showError('');
        setBusy(true);
        post(@json(route('feedback.skip')), { department: prompt.key }).then(function (payload) {
            thank(payload.message || 'Skipped for this week.');
        }).catch(function (err) {
            setBusy(false);
            showError(err.message || 'Could not skip feedback.');
        });
    });

    var tries = 0;
    function openModal() {
        if (!window.bootstrap || !bootstrap.Modal) return;
        markShown();
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
    function tryShow() {
        if (document.querySelector('.modal.show')) {
            if (tries++ < 25) {
                setTimeout(tryShow, 800);
            }
            return;
        }
        openModal();
    }
    setTimeout(tryShow, 1600);
})();
</script>
@endif
@endauth
