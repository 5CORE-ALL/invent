@extends('layouts.vertical', ['title' => 'Listing Audit Master'])

@section('css')
    <style>
        .stats-card {
            background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .stats-card h4 {
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 0.5rem;
        }

        .stats-card .badge {
            font-size: 1.5rem;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .table-container {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%);
            border: none;
            padding: 1rem;
            font-weight: 600;
            color: #1E293B;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background-color: #F8FAFC;
            transform: translateY(-2px);
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #E2E8F0;
        }

        .channel-link {
            color: #4F46E5;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .channel-link:hover {
            color: #4338CA;
        }

        .sessions-badge {
            background: #EEF2FF;
            color: #4F46E5;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
        }

        .sl-no {
            width: 70px;
            color: #64748B;
            font-weight: 500;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .stats-card {
                margin-bottom: 1rem;
            }

            .table-container {
                border-radius: 12px;
                padding: 1rem;
            }

            .table thead th {
                padding: 0.75rem;
            }

            .table td {
                padding: 0.75rem;
            }
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid py-4">
        <!-- Stats Card -->
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="m-0">Sessions Overview</h4>
                <span class="badge">{{ number_format($sessions_l30) }}</span>
            </div>
            <p class="text-white-50 mb-0">Total sessions under 100</p>
        </div>

        <!-- Table Container -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="sl-no">Sl No.</th>
                            <th>Channel</th>
                            <th class="text-end">Sessions < 100</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data as $index => $row)
                            <tr>
                                <td class="sl-no">{{ $index + 1 }}</td>
                                <td>
                                    <a href="{{ $row['channel_url'] }}" target="_blank" class="channel-link">
                                        {{ $row['Channel'] }}
                                    </a>
                                </td>
                                <td class="text-end">
                                    <span class="sessions-badge">
                                        {{ number_format($row['sessions_l30']) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        // Add animation when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
@endsection
