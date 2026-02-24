@extends('layouts.vertical', ['title' => $title ?? 'TopDawg - Settings', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<style>
    .settings-section { border: 1px solid #e9ecef; border-radius: 8px; margin-bottom: 1.5rem; overflow: hidden; }
    .settings-section-header { background: #f8f9fa; padding: 12px 16px; font-weight: 600; }
    .settings-section-body { padding: 16px; }
    .form-check-input:checked { background-color: #405189; border-color: #405189; }
    .sync-toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
    .sync-toggle-row:last-child { border-bottom: none; }
</style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <p class="text-muted mb-3">TopDawg integration settings. No Shopify sync — data is fetched from TopDawg API only.</p>

            <form id="topdawg-settings-form">
                @csrf

                <div class="settings-section">
                    <div class="settings-section-header">General</div>
                    <div class="settings-section-body">
                        <div class="sync-toggle-row">
                            <label class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="general[sync_enabled]" value="1" {{ ($settings['general']['sync_enabled'] ?? true) ? 'checked' : '' }}>
                                <span class="form-check-label">Enable sync (fetch products and orders from TopDawg API)</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <button type="submit" class="btn btn-primary" id="save-settings-btn">Save Settings</button>
                    <span class="ms-2 text-muted small" id="save-status"></span>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('topdawg-settings-form').addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('save-settings-btn');
            var status = document.getElementById('save-status');
            btn.disabled = true;
            status.textContent = 'Saving...';
            var formData = new FormData(this);
            var data = {};
            formData.forEach(function(value, key) {
                if (key.includes('[')) {
                    var match = key.match(/^(\w+)\[(\w+)\]/);
                    if (match) {
                        var o = data[match[1]] = data[match[1]] || {};
                        o[match[2]] = value;
                    }
                } else {
                    data[key] = value;
                }
            });
            fetch('{{ route('marketplace.settings.save', 'topdawg') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': formData.get('_token'),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                status.textContent = res.success ? 'Saved.' : (res.message || 'Error');
                btn.disabled = false;
                if (res.success) setTimeout(function() { status.textContent = ''; }, 2000);
            })
            .catch(function() {
                status.textContent = 'Request failed.';
                btn.disabled = false;
            });
        });
    </script>
@endsection
