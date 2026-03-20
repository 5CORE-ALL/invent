@extends('layouts.vertical', ['title' => 'Shopify Inventory CSV Import', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .upload-area {
            border: 2px dashed #007bff;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-area:hover {
            background-color: #f8f9fa;
            border-color: #0056b3;
        }
        .upload-area.dragging {
            background-color: #e7f3ff;
            border-color: #0056b3;
        }
        .batch-status-badge {
            font-size: 0.85rem;
            padding: 0.35rem 0.65rem;
        }
        .progress-container {
            margin-top: 10px;
        }
        .card {
            box-shadow: 0 0 35px 0 rgba(154, 161, 171, 0.15);
            border: none;
            margin-bottom: 24px;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #e7e7e7;
            padding: 1rem 1.5rem;
        }
        .page-title-box {
            padding-bottom: 1.5rem;
        }
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #313a46;
        }
    </style>
@endsection

@section('content')
    <!-- Start Page Title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('any', 'index') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="#">Inventory Management</a></li>
                        <li class="breadcrumb-item active">Shopify CSV Import</li>
                    </ol>
                </div>
                <h4 class="page-title">
                    <i class="ri-file-upload-line me-2"></i>Shopify Inventory CSV Import
                </h4>
            </div>
        </div>
    </div>
    <!-- End Page Title -->

    <!-- Upload Section -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="header-title mb-0"><i class="ri-upload-cloud-line me-1"></i> Upload Inventory CSV</h4>
                </div>
                <div class="card-body">
                    <form id="uploadForm" enctype="multipart/form-data">
                        @csrf
                        <div class="upload-area" id="uploadArea">
                            <i class="ri-upload-cloud-2-line" style="font-size: 3rem; color: #0d6efd;"></i>
                            <h5 class="mt-3">Drag & Drop CSV File Here</h5>
                            <p class="text-muted">or click to browse</p>
                            <input type="file" id="csvFile" name="csv_file" accept=".csv,.txt" style="display: none;">
                        </div>
                        
                        <div id="fileInfo" class="mt-3" style="display: none;">
                            <div class="alert alert-info">
                                <i class="ri-file-text-line"></i> <span id="fileName"></span>
                                <button type="button" class="btn btn-sm btn-link float-end" onclick="clearFile()">
                                    <i class="ri-close-line"></i> Remove
                                </button>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary btn-lg w-100" id="uploadBtn" disabled>
                                <i class="ri-upload-2-line me-1"></i> Upload & Process
                            </button>
                        </div>
                    </form>

                    <div class="mt-4">
                        <h6><i class="ri-information-line me-1"></i> CSV Format Requirements:</h6>
                        <ul class="small text-muted">
                            <li>Required columns: <code>SKU</code> and <code>Available</code> (or <code>On Hand</code>)</li>
                            <li>SKU must exist in inventory master</li>
                            <li>Quantity must be a valid number</li>
                            <li>Max file size: 50MB</li>
                            <li>Supports up to 100,000+ SKUs</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Import History -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="header-title mb-0"><i class="ri-history-line me-1"></i> Import History</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-centered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Filename</th>
                                    <th>Status</th>
                                    <th>Progress</th>
                                    <th>Total</th>
                                    <th>Success</th>
                                    <th>Failed</th>
                                    <th>Created By</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="batchesTable">
                                @forelse($batches as $batch)
                                <tr data-batch-id="{{ $batch->id }}">
                                    <td>{{ $batch->id }}</td>
                                    <td><i class="ri-file-text-line me-1"></i>{{ $batch->filename }}</td>
                                    <td>
                                        @if($batch->status === 'completed')
                                            <span class="badge bg-success batch-status-badge">
                                                <i class="ri-check-line"></i> Completed
                                            </span>
                                        @elseif($batch->status === 'processing')
                                            <span class="badge bg-primary batch-status-badge">
                                                <i class="ri-loader-4-line"></i> Processing
                                            </span>
                                        @elseif($batch->status === 'failed')
                                            <span class="badge bg-danger batch-status-badge">
                                                <i class="ri-error-warning-line"></i> Failed
                                            </span>
                                        @else
                                            <span class="badge bg-secondary batch-status-badge">
                                                <i class="ri-time-line"></i> Pending
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($batch->total_rows > 0)
                                            <div class="progress" style="height: 20px; min-width: 100px;">
                                                @php
                                                    $percentage = round(($batch->processed_rows / $batch->total_rows) * 100, 1);
                                                @endphp
                                                <div class="progress-bar @if($batch->status === 'completed') bg-success @elseif($batch->status === 'failed') bg-danger @endif" 
                                                     role="progressbar" 
                                                     style="width: {{ $percentage }}%">
                                                    {{ $percentage }}%
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>{{ number_format($batch->total_rows) }}</td>
                                    <td><span class="badge bg-soft-success text-success">{{ number_format($batch->successful_rows) }}</span></td>
                                    <td>
                                        @if($batch->failed_rows > 0)
                                            <span class="badge bg-soft-danger text-danger">{{ number_format($batch->failed_rows) }}</span>
                                        @else
                                            <span class="badge bg-soft-secondary text-secondary">0</span>
                                        @endif
                                    </td>
                                    <td>{{ $batch->creator->name ?? 'N/A' }}</td>
                                    <td>{{ $batch->created_at->format('M d, Y H:i') }}</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            @if($batch->status === 'processing')
                                                <button class="btn btn-info" onclick="refreshBatch({{ $batch->id }})" title="Refresh">
                                                    <i class="ri-refresh-line"></i>
                                                </button>
                                            @endif
                                            
                                            @if($batch->failed_rows > 0)
                                                <button class="btn btn-warning" onclick="downloadErrors({{ $batch->id }})" title="Download Errors">
                                                    <i class="ri-download-line"></i>
                                                </button>
                                            @endif
                                            
                                            @if($batch->status !== 'processing')
                                                <button class="btn btn-primary" onclick="retryBatch({{ $batch->id }})" title="Retry">
                                                    <i class="ri-restart-line"></i>
                                                </button>
                                                <button class="btn btn-danger" onclick="deleteBatch({{ $batch->id }})" title="Delete">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">
                                        <i class="ri-inbox-line" style="font-size: 2rem;"></i>
                                        <p class="mb-0 mt-2">No import history found</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($batches->hasPages())
                    <div class="mt-3">
                        {{ $batches->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('csvFile');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const uploadBtn = document.getElementById('uploadBtn');
        const uploadForm = document.getElementById('uploadForm');

        // Click to upload
        uploadArea.addEventListener('click', () => fileInput.click());

        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragging');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragging');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragging');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect();
            }
        });

        fileInput.addEventListener('change', handleFileSelect);

        function handleFileSelect() {
            const file = fileInput.files[0];
            if (file) {
                fileName.textContent = file.name + ' (' + formatBytes(file.size) + ')';
                fileInfo.style.display = 'block';
                uploadBtn.disabled = false;
            }
        }

        function clearFile() {
            fileInput.value = '';
            fileInfo.style.display = 'none';
            uploadBtn.disabled = true;
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        // Upload form submission
        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(uploadForm);
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<i class="ri-loader-4-line"></i> Uploading...';

            try {
                const response = await fetch('{{ route("inventory.import.shopify-csv") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    alert('CSV uploaded successfully! Processing has started.');
                    clearFile();
                    location.reload();
                } else {
                    alert('Upload failed: ' + (data.message || 'Unknown error'));
                    uploadBtn.disabled = false;
                    uploadBtn.innerHTML = '<i class="ri-upload-2-line me-1"></i> Upload & Process';
                }
            } catch (error) {
                alert('Upload failed: ' + error.message);
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<i class="ri-upload-2-line me-1"></i> Upload & Process';
            }
        });

        // Refresh batch status
        async function refreshBatch(batchId) {
            try {
                const response = await fetch(`/inventory/import/batch/${batchId}/status`);
                const data = await response.json();
                
                if (data.success) {
                    location.reload();
                }
            } catch (error) {
                console.error('Error refreshing batch:', error);
            }
        }

        // Download error report
        function downloadErrors(batchId) {
            window.location.href = `/inventory/import/batch/${batchId}/download-errors`;
        }

        // Retry batch
        async function retryBatch(batchId) {
            if (!confirm('Are you sure you want to retry this batch?')) {
                return;
            }

            try {
                const response = await fetch(`/inventory/import/batch/${batchId}/retry`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json'
                    }
                });

                const data = await response.json();
                
                if (data.success) {
                    alert('Batch retry started!');
                    location.reload();
                } else {
                    alert('Retry failed: ' + data.message);
                }
            } catch (error) {
                alert('Retry failed: ' + error.message);
            }
        }

        // Delete batch
        async function deleteBatch(batchId) {
            if (!confirm('Are you sure you want to delete this batch? This cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch(`/inventory/import/batch/${batchId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json'
                    }
                });

                const data = await response.json();
                
                if (data.success) {
                    alert('Batch deleted successfully!');
                    location.reload();
                } else {
                    alert('Delete failed: ' + data.message);
                }
            } catch (error) {
                alert('Delete failed: ' + error.message);
            }
        }

        // Auto-refresh processing batches every 5 seconds
        setInterval(() => {
            const processingBatches = document.querySelectorAll('[data-batch-id]');
            processingBatches.forEach(row => {
                const statusBadge = row.querySelector('.batch-status-badge');
                if (statusBadge && statusBadge.textContent.includes('Processing')) {
                    const batchId = row.getAttribute('data-batch-id');
                    refreshBatch(batchId);
                }
            });
        }, 5000);
    </script>
@endsection
