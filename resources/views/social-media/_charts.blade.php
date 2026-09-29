<div class="row">
    <div class="col-lg-6"><div class="card"><div class="card-body"><div id="chart-reach" style="height:280px"></div></div></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-body"><div id="chart-engagement" style="height:280px"></div></div></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-body"><div id="chart-followers" style="height:280px"></div></div></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-body"><div id="chart-posts" style="height:280px"></div></div></div></div>
    <div class="col-12"><div class="card"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Platform performance</h5>
            <select id="platform-metric" class="form-select w-auto">
                <option value="reach">Reach</option>
                <option value="engagement">Engagement</option>
                <option value="followers">Followers</option>
                <option value="posts">Content volume</option>
            </select>
        </div>
        <div id="chart-platforms" style="height:300px"></div>
    </div></div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const charts = @json($report['charts'] ?? []);
    const platforms = @json($report['platforms'] ?? []);
    function line(id, title, points, name) {
        const rows = points || [];
        Highcharts.chart(id, {
            title: { text: rows.length ? title : title + ' — no historical data' },
            credits: { enabled: false },
            xAxis: { categories: rows.map(function (point) { return point.date; }) },
            yAxis: { title: { text: name } },
            series: [{ name: name, data: rows.map(function (point) { return point.value; }) }],
            lang: { noData: 'No historical data for this period.' }
        });
    }
    line('chart-reach', 'Reach progress', charts.reach, 'Reach');
    line('chart-engagement', 'Engagement progress', charts.engagement, 'Engagement');
    line('chart-followers', 'Follower count', charts.followers, 'Followers');
    line('chart-posts', 'Content publishing', charts.posts, 'Posts');
    function drawPlatforms(metric) {
        const rows = platforms.filter(function (row) { return row[metric] !== null && row[metric] !== undefined; });
        Highcharts.chart('chart-platforms', {
            chart: { type: 'column' },
            title: { text: rows.length ? 'Platforms by ' + metric : 'No stored history for ' + metric },
            credits: { enabled: false },
            xAxis: { categories: rows.map(function (row) { return row.platform; }) },
            yAxis: { title: { text: metric } },
            series: [{ name: metric, data: rows.map(function (row) { return Number(row[metric]); }) }]
        });
    }
    const picker = document.getElementById('platform-metric');
    drawPlatforms(picker.value);
    picker.addEventListener('change', function () { drawPlatforms(picker.value); });
});
</script>
