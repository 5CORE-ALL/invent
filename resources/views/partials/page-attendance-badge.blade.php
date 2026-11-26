{{--
    Page attendance badge (toolbar button that opens the attendance modal).
    Usage: @include('partials.page-attendance-badge', ['paKey' => 'order-fulfillment.pending'])
    Pair it with @include('partials.page-attendance-modal', ['paKey' => ...]) lower on the same page.
--}}
<button type="button" class="pa-badge" id="pa-badge" title="Page attendance: who is responsible for this page, who checked in, and missed visits">
    <span class="pa-badge-dot" aria-hidden="true"></span>
    <i class="fas fa-user-check" aria-hidden="true"></i>
    Page attendance <b id="pa-badge-count">…</b>
    <span class="pa-badge-me" id="pa-badge-me" style="display:none;">your check-in</span>
</button>
