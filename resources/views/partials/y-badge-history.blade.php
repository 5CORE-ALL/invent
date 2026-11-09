{{--
    History dot + graph for "Yesterday" badges (Y Sales etc.) on sales / marketplace pages.
    Backend: App\Http\Controllers\YesterdayBadgeHistoryController (+ App\Support\Badges\YesterdayBadgeRegistry).

    Put it once per page, anywhere in the content section:
      @include('partials.y-badge-history', ['yhPage' => 'walmart_daily_sales', 'yhValue' => $salesYesterday ?? null])

    Optional:
      yhSel    badge selector for the default y_sales badge (default '#y-sales-badge')
      yhFields extra/other badges instead of the default one: [['sel' => '#id', 'metric' => 'y_sales_gpft', 'format' => 'money', 'label' => '…', 'value' => 123]]
                (browser-computed pages call ChBadgeHistory.setLive / setText themselves when the value changes)
    The dot opens the history graph; clicking the badge does too.
--}}
@php
    $yhPage = $yhPage ?? '';
    $yhDef = \App\Support\Badges\YesterdayBadgeRegistry::pages()[$yhPage] ?? null;
    $yhLabel = ($yhDef['label'] ?? $yhPage);
    $yhFields = $yhFields ?? [[
        'sel' => $yhSel ?? '#y-sales-badge',
        'metric' => 'y_sales',
        'format' => 'money',
        'label' => 'Y Sales',
        'value' => $yhValue ?? null,
    ]];
    $yhQuery = '?page=' . rawurlencode($yhPage);
@endphp
@if ($yhDef)
    @include('partials.channel-badge-history', [
        'bhLabel' => $yhLabel,
        'bhChartUrl' => url('/y-badge/chart-data') . $yhQuery,
        'bhPrevUrl' => url('/y-badge/prev-day') . $yhQuery,
        'bhSnapshotUrl' => url('/y-badge/snapshot') . $yhQuery,
    ])
    <script>
        // For badges whose text the page rewrites in JS: sets the text (keeping the history dot) and reports the live value.
        window.ybhSet = window.ybhSet || function (sel, text, metric, value) {
            if (window.ChBadgeHistory) {
                ChBadgeHistory.setText(sel, text);
                if (metric) ChBadgeHistory.setLive(metric, Number(value));
            } else if (window.jQuery) {
                window.jQuery(sel).text(text);
            }
        };
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.ChBadgeHistory) return;
            ChBadgeHistory.init({
                label: @json($yhLabel),
                chartUrl: @json(url('/y-badge/chart-data') . $yhQuery),
                prevUrl: @json(url('/y-badge/prev-day') . $yhQuery),
                snapshotUrl: @json(url('/y-badge/snapshot') . $yhQuery)
            });
            var fields = @json($yhFields);
            ChBadgeHistory.register(fields.map(function (f) {
                return { sel: f.sel, metric: f.metric, format: f.format || 'money', label: f.label || f.metric, invert: !!f.invert, post: true };
            }));
            fields.forEach(function (f) {
                if (f.value !== null && f.value !== undefined && f.value !== '' && isFinite(Number(f.value))) {
                    ChBadgeHistory.setLive(f.metric, Number(f.value));
                }
            });
        });
    </script>
@endif
