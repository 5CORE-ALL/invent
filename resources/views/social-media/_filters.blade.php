<form method="get" class="row g-2 align-items-end mb-3">
    <div class="col-6 col-md-2">
        <label class="form-label">From</label>
        <input type="date" name="start" value="{{ $start->toDateString() }}" class="form-control">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label">To</label>
        <input type="date" name="end" value="{{ $end->toDateString() }}" class="form-control">
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label">Range</label>
        <select name="preset" class="form-select">
            <option value="">Custom</option>
            @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $value => $label)
                <option value="{{ $value }}" @selected(($preset ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label">Platform</label>
        <select name="platform" class="form-select">
            <option value="">All platforms</option>
            @foreach ($platforms as $platform)
                <option value="{{ $platform }}" @selected(($filters['platform'] ?? '') === $platform)>{{ ucfirst($platform) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label">Account</label>
        <select name="account_id" class="form-select">
            <option value="">All accounts</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" @selected((int) ($filters['account_id'] ?? 0) === $account->id)>{{ $account->account_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label">Content type</label>
        <select name="content_type" class="form-select">
            <option value="">All types</option>
            @foreach (['post' => 'Post', 'video' => 'Video', 'story' => 'Story'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['content_type'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-3">
        <label class="form-label">Executive</label>
        <select name="user_id" class="form-select">
            <option value="">All executives</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected((int) ($filters['user_id'] ?? 0) === $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
    </div>
    @if (!empty($search))
        <div class="col-12 col-md-3">
            <label class="form-label">Search</label>
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Caption or title">
        </div>
    @endif
    <div class="col-12 col-md-2">
        <button class="btn btn-primary w-100" type="submit">Apply</button>
    </div>
</form>
