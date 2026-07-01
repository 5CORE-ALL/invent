@extends('layouts.vertical', ['title' => $title ?? 'Attendance Policies'])

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="{{ route('attendance.monitor') }}" class="small text-muted">← Monitor</a>
            <h4 class="mb-0">Attendance Policies</h4>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#policyModal">Add Policy</button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Name</th><th>Designation</th><th>Hours</th><th>WFH</th><th>Monitoring</th><th>Default</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($policies as $p)
                    <tr>
                        <td>{{ $p->name }}</td>
                        <td>{{ $p->designation?->name ?? 'All' }}</td>
                        <td class="small">{{ \Carbon\Carbon::parse($p->expected_start)->format('H:i') }}–{{ \Carbon\Carbon::parse($p->expected_end)->format('H:i') }} ({{ $p->min_daily_hours }}h)</td>
                        <td>{{ $p->wfh_allowed ? 'Yes' : 'No' }}</td>
                        <td>{{ $p->monitoring_enabled ? 'On' : 'Off' }}</td>
                        <td>{{ $p->is_default ? '✓' : '' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="policyModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="policyForm">
            @csrf
            <div class="modal-header"><h5 class="modal-title">New Policy</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-2">
                <div class="col-12"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
                <div class="col-12"><label class="form-label">Designation (optional)</label>
                    <select name="designation_id" class="form-select"><option value="">All employees</option>
                    @foreach($designations as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6"><label class="form-label">Start</label><input type="time" name="expected_start" class="form-control" value="09:30" required></div>
                <div class="col-6"><label class="form-label">End</label><input type="time" name="expected_end" class="form-control" value="18:30" required></div>
                <div class="col-4"><label class="form-label">Min hours</label><input type="number" step="0.5" name="min_daily_hours" class="form-control" value="8"></div>
                <div class="col-4"><label class="form-label">Grace (min)</label><input type="number" name="grace_minutes" class="form-control" value="15"></div>
                <div class="col-4"><label class="form-label">Max idle/hr</label><input type="number" name="max_idle_minutes_per_hour" class="form-control" value="15"></div>
                <div class="col-6"><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="wfh_allowed" value="1" checked id="wfh"><label class="form-check-label" for="wfh">WFH allowed</label></div></div>
                <div class="col-6"><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="monitoring_enabled" value="1" checked id="mon"><label class="form-check-label" for="mon">Monitoring on</label></div></div>
                <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_default" value="1" id="def"><label class="form-check-label" for="def">Set as default policy</label></div></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>
@endsection

@section('script')
<script>
document.getElementById('policyForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const body = Object.fromEntries(fd.entries());
    ['wfh_allowed','monitoring_enabled','is_default'].forEach(k => { body[k] = fd.has(k); });
    if (!body.designation_id) delete body.designation_id;
    const r = await fetch('{{ route('attendance.policies.store') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: JSON.stringify(body)
    });
    if ((await r.json()).ok) location.reload();
});
</script>
@endsection
