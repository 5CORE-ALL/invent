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
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h4 class="card-title mb-0">Archived</h4>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                {{-- Dept filter --}}
                <div class="dropdown" style="width: 120px;">
                    <button class="btn btn-light border dropdown-toggle w-100 text-start d-flex justify-content-between align-items-center"
                        type="button" id="archivedDeptFilterBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <span><span id="archivedDeptFilterLabel">All</span></span>
                    </button>
                    <div class="dropdown-menu p-2" style="max-height: 320px; overflow-y: auto; min-width: 240px;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="archivedDeptFilterAll" checked>
                            <label class="form-check-label fw-semibold" for="archivedDeptFilterAll">All</label>
                        </div>
                        <hr class="my-1">
                        @foreach ($departments as $dept)
                            <div class="form-check">
                                <input class="form-check-input archived-dept-filter" type="checkbox" value="{{ $dept->id }}" id="archivedDeptFilter_{{ $dept->id }}">
                                <label class="form-check-label" for="archivedDeptFilter_{{ $dept->id }}">{{ $dept->name }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
                <a href="{{ route('help-desk-faqs.index') }}" class="btn btn-soft-primary btn-sm">
                    <i class="ri-arrow-left-line"></i> Back to FAQs
                </a>
            </div>
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
                            <tr class="archived-row" data-dept='@json(array_map("strval", $faqDept))'>
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
                        <tr id="archivedNoMatch" style="display:none;">
                            <td colspan="5" class="text-center text-muted py-4">No archived FAQs match the selected department.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var deptFilterAll = document.getElementById('archivedDeptFilterAll');
            var deptFilterBoxes = Array.prototype.slice.call(document.querySelectorAll('.archived-dept-filter'));
            var deptFilterLabel = document.getElementById('archivedDeptFilterLabel');
            var rows = Array.prototype.slice.call(document.querySelectorAll('.archived-row'));
            var noMatch = document.getElementById('archivedNoMatch');

            function selectedDepts() {
                return deptFilterBoxes.filter(function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });
            }

            function rowMatchesDept(row, sel) {
                if (sel.length === 0) return true;
                var depts = [];
                try { depts = JSON.parse(row.getAttribute('data-dept') || '[]'); } catch (e) {}
                depts = (depts || []).map(String);
                // Entries visible to everyone (no dept / "all") always match.
                if (depts.length === 0 || depts.indexOf('all') !== -1) return true;
                for (var i = 0; i < sel.length; i++) {
                    if (depts.indexOf(sel[i]) !== -1) return true;
                }
                return false;
            }

            function applyFilter() {
                var sel = selectedDepts();
                var visible = 0;
                rows.forEach(function (row) {
                    var show = rowMatchesDept(row, sel);
                    row.style.display = show ? '' : 'none';
                    if (show) visible++;
                });
                if (noMatch) noMatch.style.display = (rows.length > 0 && visible === 0) ? '' : 'none';
                if (deptFilterLabel) {
                    deptFilterLabel.textContent = sel.length === 0 ? 'All' : (sel.length + ' department' + (sel.length > 1 ? 's' : ''));
                }
            }

            if (deptFilterAll) {
                deptFilterAll.addEventListener('change', function () {
                    if (deptFilterAll.checked) {
                        deptFilterBoxes.forEach(function (cb) { cb.checked = false; });
                    }
                    applyFilter();
                });
            }

            deptFilterBoxes.forEach(function (cb) {
                cb.addEventListener('change', function () {
                    if (deptFilterAll) {
                        deptFilterAll.checked = selectedDepts().length === 0;
                    }
                    applyFilter();
                });
            });

            applyFilter();
        });
    </script>
@endsection
