@extends('layouts.vertical', ['title' => $title ?? 'Attendance Monitor'])

@section('css')
<style>
    .att-card { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; background: #fff; }
    .att-stat { border-radius: 10px; padding: .85rem 1rem; background: #f8f9fa; }
    .att-stat .val { font-size: 1.35rem; font-weight: 700; }
    .risk-high { color: #dc3545; }
    .risk-medium { color: #fd7e14; }
    .risk-low { color: #198754; }
    .live-dot { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; display: inline-block; animation: pulse 2s infinite; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-12">
            <div class="att-card p-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <h4 class="mb-1"><i class="ri-radar-line me-2 text-primary"></i>Attendance Monitor</h4>
                        <p class="text-muted mb-0 small">Insightful-style WFH monitoring with AI misuse detection</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="get" class="d-flex gap-2">
                            <input type="date" name="date" class="form-control form-control-sm" value="{{ $date }}">
                            <button class="btn btn-sm btn-primary">Go</button>
                        </form>
                        <a href="{{ route('attendance.index') }}" class="btn btn-sm btn-outline-secondary">My Attendance</a>
                        @if($can_admin)
                        <a href="{{ route('attendance.policies') }}" class="btn btn-sm btn-outline-secondary">Policies</a>
                        @endif
                    </div>
                </div>
                <div class="row g-2 mt-3">
                    <div class="col-6 col-md-3"><div class="att-stat"><div class="text-muted small">Team Size</div><div class="val">{{ $stats['total_employees'] }}</div></div></div>
                    <div class="col-6 col-md-3"><div class="att-stat"><div class="text-muted small">Present Today</div><div class="val">{{ $stats['present_today'] }}</div></div></div>
                    <div class="col-6 col-md-3"><div class="att-stat"><div class="text-muted small">Active Now</div><div class="val">{{ $stats['active_now'] }}</div></div></div>
                    <div class="col-6 col-md-3"><div class="att-stat"><div class="text-muted small">Open AI Flags</div><div class="val text-danger">{{ $stats['open_flags'] }}</div></div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="att-card p-3">
                <h6 class="mb-3">Team — {{ \Carbon\Carbon::parse($date)->format('D, M j, Y') }}</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Designation</th>
                                <th>Status</th>
                                <th>Hours</th>
                                <th>Active%</th>
                                <th>Score</th>
                                <th>Live</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($employees as $emp)
                            @php
                                $sum = $summaries->get($emp->id);
                                $live = $active_sessions->get($emp->id);
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-medium">{{ $emp->name }}</div>
                                    <div class="small text-muted">{{ $emp->email }}</div>
                                </td>
                                <td class="small">{{ $emp->designation ?? '—' }}</td>
                                <td>
                                    @if($sum)
                                        <span class="badge bg-{{ $sum->status === 'present' ? 'success' : ($sum->status === 'late' ? 'warning' : 'secondary') }}">
                                            {{ ucfirst($sum->status) }}
                                        </span>
                                    @else
                                        <span class="badge bg-light text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $sum ? number_format($sum->workHours(), 1).'h' : '—' }}</td>
                                <td>{{ $sum ? $sum->activePercent().'%' : '—' }}</td>
                                <td>
                                    @if($sum?->productivity_score !== null)
                                        <span class="{{ $sum->productivity_score < 50 ? 'risk-high' : ($sum->productivity_score < 70 ? 'risk-medium' : 'risk-low') }}">
                                            {{ $sum->productivity_score }}
                                        </span>
                                    @else — @endif
                                </td>
                                <td>
                                    @if($live)
                                        @php
                                            $liveState = $live->last_activity_state ?? ($live->status === 'paused' ? 'break' : 'working');
                                        @endphp
                                        <span class="badge bg-{{ $liveState === 'idle' ? 'warning' : ($liveState === 'break' ? 'secondary' : 'success') }}">
                                            @if($liveState === 'idle')
                                                <span class="live-dot" style="background:#f97316"></span> Idle
                                            @elseif($liveState === 'break')
                                                Break
                                            @else
                                                <span class="live-dot"></span> Working
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('attendance.employee', $emp) }}?date={{ $date }}" class="btn btn-xs btn-outline-primary btn-sm py-0">View</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="att-card p-3">
                <h6 class="mb-3"><i class="ri-robot-2-line me-1"></i> AI Flags (7 days)</h6>
                @forelse($open_flags as $flag)
                    <div class="border-bottom pb-2 mb-2">
                        <div class="d-flex justify-content-between">
                            <strong class="small">{{ $flag->user?->name }}</strong>
                            <span class="badge bg-{{ $flag->severity === 'high' ? 'danger' : ($flag->severity === 'medium' ? 'warning' : 'secondary') }}">
                                {{ $flag->severity }}
                            </span>
                        </div>
                        <div class="small">{{ $flag->title }}</div>
                        <div class="text-muted" style="font-size:.75rem">{{ $flag->flag_date?->format('M j') }} · {{ $flag->typeLabel() }}</div>
                        <a href="{{ route('attendance.employee', $flag->user_id) }}" class="small">Review →</a>
                    </div>
                @empty
                    <p class="text-muted small mb-0">No open flags in the last 7 days.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
