<div class="crm-tab-partial" data-tab-partial="follow-ups">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Scheduled</th>
                    <th>Assignee</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($followUps as $f)
                    <tr>
                        <td><a href="{{ route('crm.follow-ups.show', $f) }}">{{ $f->id }}</a></td>
                        <td>{{ Str::limit($f->title, 48) }}</td>
                        <td><span class="badge bg-light text-dark border">{{ $f->follow_up_type }}</span></td>
                        <td>{{ $f->priority }}</td>
                        <td><span class="badge bg-secondary">{{ $f->status }}</span></td>
                        <td class="text-nowrap small">{{ $f->scheduled_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="small">{{ $f->assignedUser->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-muted">No follow-ups for this customer.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($followUps->hasPages())
        <div class="crm-pagination mt-3 d-flex justify-content-center">
            {{ $followUps->onEachSide(1)->links() }}
        </div>
    @endif
</div>
