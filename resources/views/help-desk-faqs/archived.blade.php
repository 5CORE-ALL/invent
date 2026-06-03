@extends('layouts.vertical', ['title' => 'Archived FAQs'])

@section('css')
    <style>
        #archivedTable th,
        #archivedTable td {
            vertical-align: middle;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', ['page_title' => 'Archived FAQs', 'sub_title' => 'Restore or permanently delete archived Help Desk entries'])

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Archived</h4>
            <a href="{{ route('help-desk-faqs.index') }}" class="btn btn-soft-primary btn-sm">
                <i class="ri-arrow-left-line"></i> Back to FAQs
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="archivedTable" class="table table-bordered table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width: 120px;">Group</th>
                            <th style="min-width: 240px;">FAQ/FFP/Issues</th>
                            <th style="min-width: 160px;">Dept</th>
                            <th style="min-width: 160px;">Archived At</th>
                            <th class="text-end" style="min-width: 160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($faqs as $faq)
                            @php
                                $faqDept = is_array($faq->dept) ? $faq->dept : [];
                            @endphp
                            <tr>
                                <td>@if (trim((string) $faq->group_name) !== ''){{ $faq->group_name }}@else<span class="text-muted">&mdash;</span>@endif</td>
                                <td>{{ $faq->faq }}</td>
                                <td>
                                    @if (in_array('all', array_map('strval', $faqDept), true))
                                        <span class="badge bg-success">All</span>
                                    @elseif (empty($faqDept))
                                        <span class="text-muted">&mdash;</span>
                                    @else
                                        @foreach ($faqDept as $d)
                                            <span class="badge bg-soft-primary text-primary">{{ $deptNames[$d] ?? $d }}</span>
                                        @endforeach
                                    @endif
                                </td>
                                <td>{{ optional($faq->deleted_at)->format('Y-m-d H:i') }}</td>
                                <td class="text-end">
                                    <form action="{{ route('help-desk-faqs.restore', $faq->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-soft-success"><i class="ri-arrow-go-back-line"></i> Restore</button>
                                    </form>
                                    <form action="{{ route('help-desk-faqs.force-delete', $faq->id) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm('Permanently delete this FAQ? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-soft-danger"><i class="ri-delete-bin-line"></i> Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No archived FAQs.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
