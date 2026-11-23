            const pulseRoot = document.getElementById('lqs-pulse');
            if (pulseRoot && pulseRoot.dataset.url && window.Chart) {
                let pulseChart = null;
                let pulseDays = 30;
                const pulseStill = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                const pulseStroke = function (context) {
                    const area = context.chart.chartArea;
                    if (!area) return '#c4b5fd';
                    const g = context.chart.ctx.createLinearGradient(area.left, 0, area.right, 0);
                    g.addColorStop(0, '#f472b6');
                    g.addColorStop(0.55, '#a78bfa');
                    g.addColorStop(1, '#22d3ee');
                    return g;
                };
                const pulseCount = function (el, to) {
                    if (!el) return;
                    const target = Number(to) || 0;
                    if (pulseStill) { el.textContent = target.toLocaleString(); return; }
                    const from = Number(String(el.textContent).replace(/[^0-9.-]/g, '')) || 0;
                    const start = performance.now();
                    (function step(now) {
                        const p = Math.min(1, (now - start) / 800);
                        el.textContent = Math.round(from + (target - from) * (1 - Math.pow(1 - p, 3))).toLocaleString();
                        if (p < 1) requestAnimationFrame(step);
                    })(start);
                };
                const pulseAlong = function (pts, t) {
                    if (!pts.length) return null;
                    if (pts.length === 1) return pts[0];
                    const lengths = [];
                    let total = 0;
                    for (let i = 1; i < pts.length; i++) {
                        const len = Math.hypot(pts[i].x - pts[i - 1].x, pts[i].y - pts[i - 1].y);
                        lengths.push(len);
                        total += len;
                    }
                    if (total <= 0) return pts[0];
                    let dist = ((t % 1) + 1) % 1 * total;
                    for (let i = 1; i < pts.length; i++) {
                        const len = lengths[i - 1];
                        if (dist <= len || i === pts.length - 1) {
                            const p = len === 0 ? 0 : Math.min(1, dist / len);
                            return { x: pts[i - 1].x + (pts[i].x - pts[i - 1].x) * p, y: pts[i - 1].y + (pts[i].y - pts[i - 1].y) * p };
                        }
                        dist -= len;
                    }
                    return pts[pts.length - 1];
                };
                const drawPulse = function (rows) {
                    const labels = rows.map(function (r) { return r.label; });
                    const data = rows.map(function (r) { return Number(r.actions) || 0; });
                    pulseCount(document.getElementById('lqs-pulse-today'), data.length ? data[data.length - 1] : 0);
                    pulseCount(document.getElementById('lqs-pulse-avg'), data.length ? Math.round(data.reduce(function (a, b) { return a + b; }, 0) / data.length) : 0);
                    const canvas = document.getElementById('lqsPulseChart');
                    if (!canvas) return;
                    if (pulseChart) pulseChart.destroy();
                    const drawMs = pulseStill ? 0 : 1600;
                    const stepMs = data.length > 1 ? drawMs / (data.length - 1) : drawMs;
                    pulseChart = new Chart(canvas, {
                        type: 'line',
                        plugins: [{
                            id: 'lqsPulseDot',
                            afterDatasetsDraw: function (chart) {
                                const meta = chart.getDatasetMeta(0);
                                const values = chart.data.datasets[0].data;
                                const ctx = chart.ctx;
                                ctx.save();
                                ctx.font = '700 10px sans-serif';
                                ctx.fillStyle = '#e2e8f0';
                                ctx.textAlign = 'center';
                                ctx.textBaseline = 'bottom';
                                let lastX = -999;
                                meta.data.forEach(function (pt, i) {
                                    const n = Number(values[i]) || 0;
                                    if (n <= 0 || pt.skip) return;
                                    if (pt.x - lastX < 26 && i !== values.length - 1) return;
                                    lastX = pt.x;
                                    ctx.fillText(n.toLocaleString(), pt.x, pt.y - 6);
                                });
                                if (!pulseStill && chart.$pulseReady && meta.data.length > 1) {
                                    const travel = (performance.now() % 3400) / 3400;
                                    for (let trail = 4; trail >= 0; trail--) {
                                        const pos = pulseAlong(meta.data, travel - trail * 0.018);
                                        if (!pos) continue;
                                        const radius = trail === 0 ? 11 : 7 - trail;
                                        const glow = ctx.createRadialGradient(pos.x, pos.y, 0, pos.x, pos.y, radius);
                                        glow.addColorStop(0, trail === 0 ? 'rgba(255,255,255,.95)' : 'rgba(34,211,238,.55)');
                                        glow.addColorStop(0.45, 'rgba(167,139,250,.45)');
                                        glow.addColorStop(1, 'rgba(34,211,238,0)');
                                        ctx.fillStyle = glow;
                                        ctx.beginPath();
                                        ctx.arc(pos.x, pos.y, radius, 0, Math.PI * 2);
                                        ctx.fill();
                                    }
                                }
                                ctx.restore();
                            }
                        }],
                        data: {
                            labels: labels,
                            datasets: [{
                                data: data,
                                borderColor: pulseStroke,
                                backgroundColor: function (context) {
                                    const area = context.chart.chartArea;
                                    if (!area) return 'rgba(167,139,250,.2)';
                                    const g = context.chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
                                    g.addColorStop(0, 'rgba(244,114,182,.45)');
                                    g.addColorStop(0.55, 'rgba(167,139,250,.22)');
                                    g.addColorStop(1, 'rgba(34,211,238,0)');
                                    return g;
                                },
                                fill: true,
                                tension: 0.15,
                                borderWidth: 2.6,
                                pointRadius: function (ctx) { return data[ctx.dataIndex] > 0 ? 3 : 0; },
                                pointBackgroundColor: '#fff',
                                pointBorderColor: '#22d3ee',
                                pointHoverRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: pulseStill ? false : {
                                duration: stepMs,
                                onComplete: function (anim) {
                                    const chart = anim && anim.chart ? anim.chart : this;
                                    if (chart) chart.$pulseReady = true;
                                }
                            },
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: '#0b1220',
                                    borderColor: 'rgba(167,139,250,.55)',
                                    borderWidth: 1,
                                    displayColors: false,
                                    callbacks: {
                                        title: function (items) { return rows[items[0].dataIndex].date; },
                                        label: function (item) {
                                            const n = item.parsed.y;
                                            return n.toLocaleString() + ' action' + (n === 1 ? '' : 's');
                                        },
                                        afterBody: function (items) {
                                            const row = rows[items[0].dataIndex] || {};
                                            const lines = (row.details || []).map(function (d) {
                                                return d.sku + ' · ' + d.sku_actions + ' on this SKU · ' + d.user + (d.action ? ' · ' + d.action : '');
                                            });
                                            if (row.more) lines.push('+' + row.more + ' more');
                                            return lines;
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: { grid: { display: false }, border: { display: false }, ticks: { color: '#94a3b8', maxRotation: 0, autoSkip: true, maxTicksLimit: 8, font: { size: 10, weight: '600' } } },
                                y: { beginAtZero: true, border: { display: false }, grid: { color: 'rgba(148,163,184,.14)' }, ticks: { color: '#94a3b8', precision: 0, font: { size: 10 } } }
                            }
                        }
                    });
                };
                window.lqsReloadPulse = function () {
                    document.querySelectorAll('#lqs-pulse .lqs-pulse-ranges button').forEach(function (btn) {
                        btn.classList.toggle('on', Number(btn.dataset.days) === pulseDays);
                    });
                    fetch(pulseRoot.dataset.url + '?days=' + pulseDays, { headers: { Accept: 'application/json' } })
                        .then(function (res) { return res.json(); })
                        .then(function (json) { drawPulse(Array.isArray(json.days) ? json.days : []); })
                        .catch(function () {});
                };
                document.querySelectorAll('#lqs-pulse .lqs-pulse-ranges button').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        pulseDays = Number(btn.dataset.days) || 30;
                        window.lqsReloadPulse();
                    });
                });
                window.lqsReloadPulse();
                if (!pulseStill) {
                    (function loop() {
                        requestAnimationFrame(loop);
                        if (document.hidden || !pulseChart || !pulseChart.$pulseReady) return;
                        const box = pulseChart.canvas.getBoundingClientRect();
                        if (box.bottom < 0 || box.top > window.innerHeight) return;
                        pulseChart.draw();
                    })();
                }
            }
