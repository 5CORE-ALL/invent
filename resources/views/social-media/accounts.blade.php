@extends('layouts.vertical', ['title' => 'Social Media Accounts'])

@section('content')
    <h4 class="page-title">Accounts</h4>
    @include('social-media._nav')
    @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="alert alert-warning">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="card"><div class="card-body table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>Platform</th><th>Account</th><th>Executive</th><th>Status</th><th>Token</th>
                    <th>Last sync</th><th>Followers</th><th>Reach</th><th>Sync</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accounts as $account)
                    @php
                        $followers = $latest[$account->id]['followers'] ?? null;
                        $reach = $latest[$account->id]['reach'] ?? null;
                    @endphp
                    <tr>
                        <td>{{ ucfirst($account->platform) }}</td>
                        <td>
                            <div>{{ $account->account_name }}</div>
                            <div class="small text-muted">{{ $account->external_account_id }}</div>
                        </td>
                        <td>{{ $account->user->name ?? '—' }}</td>
                        <td>{{ $account->status }}{{ $account->sync_enabled ? '' : ' (sync off)' }}</td>
                        <td>{{ ucfirst($account->tokenStatus()) }}</td>
                        <td>
                            {{ optional($account->last_synced_at)->diffForHumans() ?? 'Never' }}
                            <div class="small text-muted">{{ $account->last_sync_status }}</div>
                            @if ($account->last_sync_error)<div class="small text-warning">{{ $account->last_sync_error }}</div>@endif
                        </td>
                        <td>@include('social-media._value', ['value' => optional($followers)->metric_value, 'availability' => optional($followers)->availability ?? 'not_available'])</td>
                        <td>@include('social-media._value', ['value' => optional($reach)->metric_value, 'availability' => optional($reach)->availability ?? 'not_available'])</td>
                        <td>
                            @can('social_media.sync')
                                <form method="post" action="{{ route('social-media.accounts.sync', $account) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Sync</button>
                                </form>
                            @endcan
                        </td>
                        <td>
                            @can('social_media.manage')
                                <details>
                                    <summary class="small">Edit</summary>
                                    <form method="post" action="{{ route('social-media.accounts.update', $account) }}" class="mt-2" style="min-width:220px">
                                        @csrf @method('put')
                                        <input type="hidden" name="platform" value="{{ $account->platform }}">
                                        <input type="hidden" name="external_account_id" value="{{ $account->external_account_id }}">
                                        <input type="hidden" name="username" value="{{ $account->username }}">
                                        <input type="hidden" name="user_id" value="{{ $account->user_id }}">
                                        <input type="hidden" name="token_expires_at" value="{{ optional($account->token_expires_at)->format('Y-m-d\TH:i') }}">
                                        <input name="account_name" class="form-control form-control-sm mb-1" value="{{ $account->account_name }}" required>
                                        <input name="profile_url" class="form-control form-control-sm mb-1" value="{{ $account->profile_url }}" placeholder="Profile URL">
                                        <input name="access_token" type="password" class="form-control form-control-sm mb-1" placeholder="New access token" autocomplete="off">
                                        <input name="refresh_token" type="password" class="form-control form-control-sm mb-1" placeholder="New refresh token" autocomplete="off">
                                        <input type="hidden" name="sync_enabled" value="0">
                                        <label class="form-check small"><input class="form-check-input" type="checkbox" name="sync_enabled" value="1" @checked($account->sync_enabled)> Sync enabled</label>
                                        <button class="btn btn-sm btn-primary" type="submit">Save</button>
                                    </form>
                                </details>
                                <form method="post" action="{{ route('social-media.accounts.destroy', $account) }}" class="mt-1" onsubmit="return confirm('Remove this account and its stored history?')">
                                    @csrf @method('delete')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-muted">No accounts connected yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>

    @can('social_media.connect')
    <div class="card"><div class="card-body">
        <h5>Connect an account</h5>
        <p class="text-muted">Paste a token from the platform’s official OAuth flow. The token is encrypted and is not shown again.</p>
        <form method="post" action="{{ route('social-media.accounts.store') }}" class="row g-2">
            @csrf
            <div class="col-md-3">
                <label class="form-label">Platform</label>
                <select name="platform" class="form-select" required>
                    @foreach ($platforms as $platform)
                        <option value="{{ $platform }}">{{ ucfirst($platform) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Account / page ID</label>
                <input name="external_account_id" class="form-control" required value="{{ old('external_account_id') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Name</label>
                <input name="account_name" class="form-control" required value="{{ old('account_name') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Username</label>
                <input name="username" class="form-control" value="{{ old('username') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Profile URL</label>
                <input name="profile_url" type="url" class="form-control" value="{{ old('profile_url') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Access token</label>
                <input name="access_token" type="password" class="form-control" required autocomplete="off">
            </div>
            <div class="col-md-4">
                <label class="form-label">Refresh token</label>
                <input name="refresh_token" type="password" class="form-control" autocomplete="off">
            </div>
            <div class="col-md-3">
                <label class="form-label">Token expiry</label>
                <input name="token_expires_at" type="datetime-local" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Executive</label>
                <select name="user_id" class="form-select">
                    <option value="">Unassigned</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <input type="hidden" name="sync_enabled" value="0">
                <label class="form-check"><input class="form-check-input" type="checkbox" name="sync_enabled" value="1" checked> Sync</label>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary" type="submit">Connect</button>
            </div>
        </form>
    </div></div>
    @endcan

    <div class="card"><div class="card-body">
        <h5>Metric availability</h5>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Metric</th>@foreach ($platforms as $platform)<th>{{ ucfirst($platform) }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach (array_keys($catalog['facebook'] ?? []) as $metric)
                        <tr>
                            <td>{{ str_replace('_', ' ', $metric) }}</td>
                            @foreach ($platforms as $platform)
                                <td>{{ ($catalog[$platform][$metric] ?? 'not_available') === 'available' ? 'Available' : 'Not available' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div></div>
@endsection
