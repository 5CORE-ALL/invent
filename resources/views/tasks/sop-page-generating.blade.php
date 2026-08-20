@extends('layouts.vertical', ['title' => 'Building SOP page'])

@section('content')
<div class="container-fluid py-5">
    <div class="text-center" style="max-width:480px;margin:80px auto;">
        <div class="spinner-border text-primary mb-3" role="status"></div>
        <h5 class="mb-2">Writing the SOP page</h5>
        <p class="text-muted mb-0">Using the SOP link and AI to write the procedure and matching visuals for <strong>{{ $task->title }}</strong>.</p>
        <p class="text-danger small mt-3 d-none" id="sop-gen-error"></p>
    </div>
</div>
@endsection

@section('script')
<script>
    (async function () {
        var errEl = document.getElementById('sop-gen-error');
        try {
            var res = await fetch(@json($ensureUrl), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            var data = await res.json().catch(function () { return {}; });
            if (!res.ok) {
                errEl.classList.remove('d-none');
                errEl.textContent = data.message || 'Could not build the SOP page.';
                return;
            }
            window.location.replace(@json($showUrl));
        } catch (e) {
            errEl.classList.remove('d-none');
            errEl.textContent = 'Could not build the SOP page.';
        }
    })();
</script>
@endsection
