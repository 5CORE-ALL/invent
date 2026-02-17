@extends('layouts.vertical', ['title' => $title ?? 'Marketplace Sync', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ $title ?? ucfirst($marketplace) . ' - ' . ucfirst($page) }}</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-0">
                        <strong>{{ ucfirst($marketplace) }}</strong> &raquo; {{ ucfirst($page) }}.
                        @if($page === 'products' && $marketplace === 'reverb')
                            <a href="{{ route('reverb.pricing') }}">Go to Reverb Pricing</a>
                        @else
                            This section will be implemented for {{ ucfirst($marketplace) }}.
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
