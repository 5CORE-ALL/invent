@extends('layouts.vertical', ['title' => 'Upload Tasks', 'sidenav' => 'condensed'])

@section('css')
    <style>
        .task-upload-card {
            border: 1px dashed #d1d5db;
            background: #f9fafb;
            border-radius: 8px;
            padding: 18px 20px;
        }

        .task-upload-card .form-control {
            max-width: 420px;
        }

        .task-upload-instructions {
            font-size: 12px;
            color: #6b7280;
            margin-top: 8px;
        }

        .task-upload-instructions code {
            background: #eef2ff;
            color: #3730a3;
            padding: 1px 6px;
            border-radius: 4px;
        }

        .task-upload-dropzone {
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            background: #fff;
            padding: 28px 16px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.15s ease, background 0.15s ease;
        }

        .task-upload-dropzone:hover,
        .task-upload-dropzone.is-dragover {
            border-color: #56ab2f;
            background: #f4fbf0;
        }

        .task-upload-results table th,
        .task-upload-results table td {
            font-size: 13px;
            vertical-align: middle;
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-12">
                <div class="page-title-box d-flex align-items-center justify-content-between py-1">
                    <h4 class="page-title mb-0">Upload Tasks</h4>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('tasks.index') }}">Task Manager</a></li>
                            <li class="breadcrumb-item active">Upload Tasks</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card task-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <div>
                                <h5 class="mb-1">Upload a task sheet</h5>
                                <div class="text-muted" style="font-size: 13px;">
                                    Download the Excel template. Click Assignee and pick the person from the dropdown — do not type the name. Email is not needed.
                                </div>
                            </div>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-success dropdown-toggle" id="csv-actions-btn" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="CSV: upload, template, export">
                                    <i class="mdi mdi-file-delimited"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#" id="task-upload-trigger">
                                            <i class="mdi mdi-upload me-2"></i>Upload CSV
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('tasks.downloadTemplate') }}">
                                            <i class="mdi mdi-download me-2"></i>Download Template
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="#" id="task-upload-export">
                                            <i class="mdi mdi-export me-2"></i>Export Selected<span class="export-count"></span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="task-upload-card">
                            <form id="task-upload-form" enctype="multipart/form-data">
                                @csrf
                                <div class="row g-3 align-items-start">
                                    <div class="col-lg-6">
                                        <div class="task-upload-dropzone" id="task-upload-dropzone">
                                            <i class="mdi mdi-file-excel text-success" style="font-size: 2.4rem;"></i>
                                            <div class="fw-semibold mt-2">Drop your CSV or Excel file here</div>
                                            <div class="text-muted small">or click to browse</div>
                                            <div class="small text-success fw-semibold mt-2" id="task-upload-filename"></div>
                                        </div>
                                        <input type="file" name="csv_file" id="task-upload-file" class="d-none" accept=".csv,.txt,.xlsx,.xls,.xlsm,.ods" required>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="d-flex flex-wrap gap-2 mb-3">
                                            <button type="submit" class="btn btn-success" id="task-upload-submit">
                                                <i class="mdi mdi-upload me-1"></i> Upload & Import
                                            </button>
                                            <a href="{{ route('tasks.downloadTemplate') }}" class="btn btn-outline-primary">
                                                <i class="mdi mdi-download me-1"></i> Download Template
                                            </a>
                                            <a href="{{ route('tasks.downloadTemplate', ['format' => 'csv']) }}" class="btn btn-outline-secondary">
                                                CSV Template
                                            </a>
                                            <a href="{{ route('tasks.index') }}" class="btn btn-light">
                                                Back to Task Manager
                                            </a>
                                        </div>
                                        <div class="task-upload-instructions">
                                            Use the <strong>Excel</strong> template (dropdowns do not work in CSV).
                                            Click <code>Assignee</code> and select a name from the list.
                                            Extra people go in <code>Assignee 2</code> and <code>Assignee 3</code>.
                                            Required: <code>Task</code>, <code>Assignee</code>.
                                            Optional: Assignor, Group, Priority, Status, Description, ETC Minutes, Start Date, and links.
                                            Max file size 10 MB.
                                        </div>
                                    </div>
                                </div>
                            </form>

                            <div id="task-upload-progress" class="mt-3" style="display: none;">
                                <div class="progress mb-2">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 100%"></div>
                                </div>
                                <p class="text-center text-muted mb-0">
                                    <i class="mdi mdi-loading mdi-spin me-2"></i>Uploading and creating tasks...
                                </p>
                            </div>
                            <div id="task-upload-result" class="mt-3"></div>
                        </div>

                        <div id="task-upload-results-wrap" class="task-upload-results mt-4" style="display: none;">
                            <h6 class="mb-2">Created tasks</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0" id="task-upload-results-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 36px;"><input type="checkbox" id="task-upload-select-all"></th>
                                            <th>ID</th>
                                            <th>Task</th>
                                            <th>Assignor</th>
                                            <th>Assignee</th>
                                            <th>Group</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        (function() {
            var fileInput = document.getElementById('task-upload-file');
            var dropzone = document.getElementById('task-upload-dropzone');
            var filenameEl = document.getElementById('task-upload-filename');
            var form = document.getElementById('task-upload-form');
            var importedRows = [];

            function setFile(file) {
                if (!file) return;
                var dt = new DataTransfer();
                dt.items.add(file);
                fileInput.files = dt.files;
                filenameEl.textContent = file.name;
            }

            function selectedImportedRows() {
                return Array.from(document.querySelectorAll('.task-upload-row-check:checked')).map(function(cb) {
                    return importedRows[parseInt(cb.value, 10)];
                }).filter(Boolean);
            }

            function syncExportCount() {
                var count = document.querySelectorAll('.task-upload-row-check:checked').length;
                document.querySelectorAll('.export-count').forEach(function(el) {
                    el.textContent = count > 0 ? ' (' + count + ')' : '';
                });
            }

            function renderResults(tasks) {
                importedRows = Array.isArray(tasks) ? tasks : [];
                var wrap = document.getElementById('task-upload-results-wrap');
                var tbody = wrap.querySelector('tbody');
                tbody.innerHTML = '';
                if (importedRows.length === 0) {
                    wrap.style.display = 'none';
                    return;
                }
                importedRows.forEach(function(task, index) {
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td><input type="checkbox" class="task-upload-row-check" value="' + index + '"></td>' +
                        '<td>' + (task.id || '') + '</td>' +
                        '<td>' + (task.title || '') + '</td>' +
                        '<td>' + (task.assignor || '') + '</td>' +
                        '<td>' + (task.assign_to || '') + '</td>' +
                        '<td>' + (task.group || '') + '</td>' +
                        '<td>' + (task.priority || '') + '</td>' +
                        '<td>' + (task.status || '') + '</td>';
                    tbody.appendChild(tr);
                });
                wrap.style.display = '';
                document.getElementById('task-upload-select-all').checked = false;
                syncExportCount();
            }

            function escapeCsvCell(value) {
                var text = String(value == null ? '' : value);
                if (/[",\n\r]/.test(text)) {
                    return '"' + text.replace(/"/g, '""') + '"';
                }
                return text;
            }

            dropzone.addEventListener('click', function() {
                fileInput.click();
            });
            document.getElementById('task-upload-trigger').addEventListener('click', function(e) {
                e.preventDefault();
                fileInput.click();
            });
            fileInput.addEventListener('change', function() {
                if (fileInput.files[0]) {
                    filenameEl.textContent = fileInput.files[0].name;
                }
            });
            ['dragenter', 'dragover'].forEach(function(eventName) {
                dropzone.addEventListener(eventName, function(e) {
                    e.preventDefault();
                    dropzone.classList.add('is-dragover');
                });
            });
            ['dragleave', 'drop'].forEach(function(eventName) {
                dropzone.addEventListener(eventName, function(e) {
                    e.preventDefault();
                    dropzone.classList.remove('is-dragover');
                });
            });
            dropzone.addEventListener('drop', function(e) {
                if (e.dataTransfer.files[0]) {
                    setFile(e.dataTransfer.files[0]);
                }
            });

            document.getElementById('task-upload-select-all').addEventListener('change', function() {
                document.querySelectorAll('.task-upload-row-check').forEach(function(cb) {
                    cb.checked = document.getElementById('task-upload-select-all').checked;
                });
                syncExportCount();
            });
            document.getElementById('task-upload-results-table').addEventListener('change', function(e) {
                if (e.target.classList.contains('task-upload-row-check')) {
                    syncExportCount();
                }
            });

            document.getElementById('task-upload-export').addEventListener('click', function(e) {
                e.preventDefault();
                var rows = selectedImportedRows();
                if (rows.length === 0) {
                    document.getElementById('task-upload-result').innerHTML =
                        '<div class="alert alert-danger mb-0">Please select at least one imported task to export.</div>';
                    return;
                }
                var headers = ['Task ID', 'Task', 'Assignor', 'Assignee', 'Group', 'Priority', 'Status'];
                var lines = [headers.map(escapeCsvCell).join(',')];
                rows.forEach(function(row) {
                    lines.push([
                        row.id || '',
                        row.title || '',
                        row.assignor || '',
                        row.assign_to || '',
                        row.group || '',
                        row.priority || '',
                        row.status || ''
                    ].map(escapeCsvCell).join(','));
                });
                var blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
                var url = URL.createObjectURL(blob);
                var link = document.createElement('a');
                link.href = url;
                link.download = 'tasks-uploaded-' + new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-') + '.csv';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            });

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                if (!fileInput.files.length) {
                    document.getElementById('task-upload-result').innerHTML =
                        '<div class="alert alert-danger mb-0">Please select a CSV or Excel file.</div>';
                    return;
                }

                var formData = new FormData();
                formData.append('csv_file', fileInput.files[0]);
                formData.append('_token', '{{ csrf_token() }}');

                document.getElementById('task-upload-progress').style.display = '';
                document.getElementById('task-upload-submit').disabled = true;
                document.getElementById('task-upload-result').innerHTML = '';

                fetch('{{ route('tasks.importCsv') }}', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                })
                .then(function(response) { return response.json().then(function(data) { return { ok: response.ok, data: data }; }); })
                .then(function(result) {
                    var data = result.data || {};
                    var errors = Array.isArray(data.errors) ? data.errors : [];
                    var warnings = Array.isArray(data.warnings) ? data.warnings : [];
                    var alertClass = data.imported > 0 ? 'alert-success' : 'alert-danger';
                    var html = '<div class="alert ' + alertClass + '"><div class="fw-semibold mb-1">' +
                        (data.message || (data.imported > 0 ? 'Import complete' : 'Import failed')) +
                        '</div>';
                    if (errors.length) {
                        html += '<ul class="mb-0">' + errors.map(function(item) { return '<li>' + item + '</li>'; }).join('') + '</ul>';
                    }
                    if (warnings.length) {
                        html += '<div class="small mt-2">' + warnings.join('<br>') + '</div>';
                    }
                    html += '</div>';
                    document.getElementById('task-upload-result').innerHTML = html;
                    renderResults(data.tasks || []);
                })
                .catch(function() {
                    document.getElementById('task-upload-result').innerHTML =
                        '<div class="alert alert-danger mb-0">Upload failed. Please check the sheet format and try again.</div>';
                })
                .finally(function() {
                    document.getElementById('task-upload-progress').style.display = 'none';
                    document.getElementById('task-upload-submit').disabled = false;
                });
            });
        })();
    </script>
@endsection
