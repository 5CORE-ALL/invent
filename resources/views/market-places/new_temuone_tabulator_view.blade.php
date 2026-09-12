@extends('layouts.vertical', ['title' => 'New Temu One', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        /* Vertical column headers (same as /bestbuy-pricing) */
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
        }

        .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        .tabulator-paginator label {
            margin-right: 5px;
        }

        .lmp-eye-btn {
            line-height: 1;
            vertical-align: middle;
        }
        #lmpModal tr.lmp-lowest-row,
        #lmpModal tr.lmp-lowest-row > td {
            background-color: #d1ecf1 !important;
        }
        #lmpModal tr.lmp-ignored-row {
            opacity: 0.55;
        }

        .toast-container {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 1080;
        }

        .temu-sprice-cap-lbl {
            color: #fd7e14;
            font-weight: 800;
            font-size: 10px;
            line-height: 1;
            margin-left: 3px;
            cursor: help;
        }
        @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'css', 'ebaySprcDilChannel' => 'temu'])
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'New Temu One',
        'sub_title' => 'New Temu One',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-2">
                <div id="summary-stats" class="d-flex align-items-center flex-wrap gap-1">
                    <select id="inventory-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">All INV</option>
                        <option value="zero">INV = 0</option>
                        <option value="more" selected>INV &gt; 0</option>
                    </select>

                    <select id="dil-filter" class="form-select form-select-sm"
                        style="width: 90px; display: inline-block;">
                        <option value="all">DIL%</option>
                        <option value="red">Red (&lt;25%)</option>
                        <option value="green">Green (25-50%)</option>
                        <option value="pink">Pink (50%+)</option>
                    </select>
                    @include('partials.ebay-sprc-dil', [
                        'ebaySprcDilPart' => 'buttons',
                        'ebaySprcDilChannel' => 'temu',
                        'ebaySprcDilZeroSoldUsesMinGroi' => false,
                    ])
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="new-temuone-table-wrapper" style="height: calc(100vh - 160px); display: flex; flex-direction: column;">
                    <div class="px-2 py-1 bg-light border-bottom d-flex flex-wrap gap-2 align-items-center">
                        <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="max-width: 220px;">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU..." style="max-width: 220px;">
                    </div>
                    <div id="new-temuone-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="newTemuoneEditLinksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <small class="text-muted">SKU: <span id="newTemuoneEditLinksSku" class="fw-bold"></span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Seller Link (S)</label>
                        <input type="url" class="form-control" id="newTemuoneSellerLinkInput" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Buyer Link (B)</label>
                        <input type="url" class="form-control" id="newTemuoneBuyerLinkInput" placeholder="https://...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="newTemuoneSaveLinksBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="lmpModal" tabindex="-1" aria-labelledby="lmpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="lmpModalLabel"><i class="fas fa-link me-2"></i>LMP for <span id="lmpModalSku"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="border rounded p-3 mb-3 bg-light">
                        <h6 class="mb-3"><i class="fas fa-plus text-success me-1"></i> Add New LMP</h6>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label small mb-0">Price <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="lmpNewPrice" placeholder="e.g. 29.99">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0">Delivery</label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="lmpNewDelivery" placeholder="0.00">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small mb-0">Product Link</label>
                                <input type="text" class="form-control form-control-sm" id="lmpNewLink" placeholder="https://...">
                            </div>
                            <div class="col-md-3 d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-primary" id="lmpAddRowBtn"><i class="fas fa-plus me-1"></i> Add LMP</button>
                            </div>
                        </div>
                    </div>
                    <h6 class="mb-2">LMP List</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" id="lmpListTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Price</th>
                                    <th style="width: 90px;">Delivery</th>
                                    <th style="width: 90px;">Price+D</th>
                                    <th>Link</th>
                                    <th class="text-center" style="width: 70px;">Ignore</th>
                                    <th style="width: 80px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="lmpEntriesContainer"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    @include('partials.ebay-sprc-dil', [
        'ebaySprcDilPart' => 'modals',
        'ebaySprcDilChannel' => 'temu',
        'ebaySprcDilZeroSoldUsesMinGroi' => false,
    ])
@endsection

@section('script-bottom')
<script>
    let table = null;
    let newTemuoneEditLinksRow = null;

    function chPromoRound2(n) {
        return Math.round((Number(n) || 0) * 100) / 100;
    }
    function chPromoTemuInvertSgroiAtSprice(sprice, lp, ship) {
        const s = Number(sprice);
        const cost = Number(lp);
        const shipN = Number(ship);
        if (!(s > 0) || !(cost > 0) || !isFinite(s) || !isFinite(cost)) return null;
        const shipUse = isFinite(shipN) && shipN > 0 ? shipN : 0;
        const mult = 1.1364;
        const candidates = [(s - 2.99) / mult, s / mult];
        let best = 0;
        let bestErr = Infinity;
        candidates.forEach(function(base) {
            if (!(base > 0)) return;
            let full = base * mult;
            if (full <= 26.99) full += 2.99;
            const err = Math.abs(full - s);
            if (err < bestErr - 1e-6) {
                bestErr = err;
                best = base;
            } else if (Math.abs(err - bestErr) <= 1e-6 && base > best) {
                best = base;
            }
        });
        if (!(best > 0)) return null;
        const sR = best <= 26.99 ? best + 2.99 : best;
        return ((sR * 0.95 - shipUse - cost) / cost) * 100;
    }
    /** Same Temu SGROI back-solve as /temu2-decrease so Sprc Dil uses temu_data_view math. */
    function chPromoSpriceFromTargetRoi(d, roiPct) {
        const lp = parseFloat(d && (d.LP_productmaster != null ? d.LP_productmaster : d.lp)) || 0;
        if (!(lp > 0)) return 0;
        const ship = parseFloat(d && (d.temu_ship != null ? d.temu_ship : d.Ship_productmaster)) || 0;
        const roi = isFinite(Number(roiPct)) ? Number(roiPct) : 0;
        const targetSR = (lp * (1 + roi / 100) + ship) / 0.95;
        if (!(targetSR > 0) || !isFinite(targetSR)) return 0;
        const base = targetSR > 26.99 ? targetSR : Math.max(0.01, targetSR - 2.99);
        let seed = base * 1.1364;
        if (seed <= 26.99) seed += 2.99;
        seed = chPromoRound2(seed);
        const seedSgroi = chPromoTemuInvertSgroiAtSprice(seed, lp, ship);
        if (seedSgroi != null && Math.abs(seedSgroi - roi) <= 1.5) return seed;
        let lo = Math.max(0.01, seed * 0.35);
        let hi = Math.max(seed * 2.8, seed + 20);
        for (let expand = 0; expand < 10; expand++) {
            const gLo = chPromoTemuInvertSgroiAtSprice(lo, lp, ship);
            const gHi = chPromoTemuInvertSgroiAtSprice(hi, lp, ship);
            if (gLo == null || gHi == null) break;
            if (gLo <= roi && roi <= gHi) break;
            if (roi < gLo) { hi = lo; lo = Math.max(0.01, lo * 0.5); }
            else { lo = hi; hi = hi * 1.8; }
        }
        let best = seed;
        let bestErr = Infinity;
        for (let i = 0; i < 40; i++) {
            const mid = (lo + hi) / 2;
            const g = chPromoTemuInvertSgroiAtSprice(mid, lp, ship);
            if (g == null) break;
            const err = Math.abs(g - roi);
            if (err < bestErr) { bestErr = err; best = mid; }
            if (g < roi) lo = mid;
            else hi = mid;
        }
        return (isFinite(best) && best > 0) ? chPromoRound2(best) : 0;
    }
    window.chPromoSpriceFromTargetRoi = chPromoSpriceFromTargetRoi;

    @include('partials.ebay-sprc-dil', [
        'ebaySprcDilPart' => 'script',
        'ebaySprcDilChannel' => 'temu',
        'ebaySprcDilZeroSoldUsesMinGroi' => false,
    ])

    function temuParseMoney(v) {
        const n = parseFloat(v);
        return (isFinite(n) && n > 0) ? n : 0;
    }

    function temuAmzRefPrice(row) {
        return temuParseMoney(row && (row.a_price != null ? row.a_price
            : (row['A Price'] != null ? row['A Price'] : row.amazon_price)));
    }

    function temuEbayRefPrice(row) {
        const e = temuParseMoney(row && (row.e_price != null ? row.e_price : row.ebay_price));
        const e2 = temuParseMoney(row && (row.e2_price != null ? row.e2_price : row.ebay2_price));
        if (e > 0 && e2 > 0) return Math.min(e, e2);
        return e > 0 ? e : e2;
    }

    function temuLmpRefPrice(row) {
        return temuParseMoney(row && (row.lmp_raw != null ? row.lmp_raw : row.lmp));
    }

    /** Discounted Price = Sprc Dil from the /temu1-data Dil table (Temu 2: Dil slab including 0 Sold). */
    function temuDiscountedPrice(row) {
        if (!row) return 0;
        if (typeof ebaySprcDilForRow === 'function') {
            const sprcDil = Number(ebaySprcDilForRow(row));
            if (sprcDil > 0) return +sprcDil.toFixed(2);
        }
        if (typeof chPromoTemuZeroSoldSprice === 'function') {
            const zeroSold = Number(chPromoTemuZeroSoldSprice(row));
            if (zeroSold > 0) return +zeroSold.toFixed(2);
        }
        if (typeof chPromoTemuSpriceFromStdPrmtCpn === 'function') {
            const combo = Number(chPromoTemuSpriceFromStdPrmtCpn(row));
            if (combo > 0) return +combo.toFixed(2);
        }
        if (typeof chPromoSpriceFromStdTPromo === 'function') {
            const calc = chPromoSpriceFromStdTPromo(row, { skip_lmp_cap: true });
            if (calc > 0) return +Number(calc).toFixed(2);
        }
        const fallback = parseFloat(row.sprc_dil);
        return fallback > 0 ? +fallback.toFixed(2) : 0;
    }

    /** S PRC = Discounted Price, then the lowest of eBay / Amazon / LMP when cheaper. Same as /temu2-decrease. */
    function temuSpriceCapResult(row, rawSprice, extra) {
        extra = extra || {};
        const liveDiscounted = temuDiscountedPrice(row);
        const passed = parseFloat(rawSprice);
        let discounted = 0;
        if (extra.use_passed_as_discounted && passed > 0) {
            discounted = +passed.toFixed(2);
        } else if (liveDiscounted > 0) {
            discounted = +liveDiscounted.toFixed(2);
        }
        let sprice = discounted > 0 ? discounted : 0;
        if (!(sprice > 0)) return { sprice: 0, labels: [], lmpAlert: false, amz: 0, ebay: 0, lmp: 0 };

        const ebay = +temuEbayRefPrice(row).toFixed(2);
        const amz = +temuAmzRefPrice(row).toFixed(2);
        const lmp = extra.skip_lmp_cap ? 0 : +temuLmpRefPrice(row).toFixed(2);
        const zeroSoldOwns = typeof chPromoTemuZeroSoldOwnsSprice === 'function'
            && chPromoTemuZeroSoldOwnsSprice(row);

        if (!zeroSoldOwns) {
            if (ebay > 0 && sprice > ebay) sprice = +ebay.toFixed(2);
            if (amz > 0 && sprice > amz) sprice = +amz.toFixed(2);
            if (lmp > 0 && sprice > lmp) sprice = +lmp.toFixed(2);
        }

        const labels = [];
        if (ebay > 0 && +sprice.toFixed(2) === ebay && discounted > ebay) labels.push('EB');
        if (amz > 0 && +sprice.toFixed(2) === amz && discounted > amz) labels.push('Amz');
        const lmpAlert = lmp > 0 && +sprice.toFixed(2) === lmp && discounted > lmp;
        return { sprice: +sprice.toFixed(2), labels: labels, lmpAlert: lmpAlert, amz: amz, ebay: ebay, lmp: lmp };
    }

    function temuSpriceCellModel(row) {
        if (!row) return { value: 0, labels: [], lmpAlert: false, lmp: 0, amz: 0, ebay: 0 };
        const cap = temuSpriceCapResult(row);
        const value = (cap && cap.sprice > 0) ? +Number(cap.sprice).toFixed(2) : 0;
        if (value > 0) {
            row.SPRICE = value;
            row.sprice = value;
        }
        return {
            value: value,
            labels: (cap && cap.labels) || [],
            lmpAlert: !!(cap && cap.lmpAlert),
            lmp: (cap && cap.lmp) || 0,
            amz: (cap && cap.amz) || 0,
            ebay: (cap && cap.ebay) || 0,
        };
    }

    function temuDisplayedSprice(row) {
        const cap = temuSpriceCapResult(row);
        return (cap && cap.sprice > 0) ? +Number(cap.sprice).toFixed(2) : 0;
    }
    window.temuDisplayedSprice = temuDisplayedSprice;
    window.temuSpriceCapResult = temuSpriceCapResult;
    window.temuDiscountedPrice = temuDiscountedPrice;

    function showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;

        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    function temuLmpRecovery(price) {
        const p = parseFloat(price);
        if (!(p > 0)) return null;
        if (p <= 27) return +((p * 0.85) + 2.99).toFixed(2);
        return +(p * 0.85).toFixed(2);
    }

    function temuLmpEntryEffective(entry) {
        if (!entry || entry.ignored) return null;
        const base = parseFloat(entry.price);
        if (!(base > 0)) return null;
        const dRaw = parseFloat(entry.delivery);
        let delivery = (isFinite(dRaw) && dRaw > 0) ? dRaw : 0;
        if (delivery <= 0 && base < 27) delivery = 2.99;
        return { raw: +(base + delivery).toFixed(2) };
    }

    let lmpModalSku = '';
    let lmpSaveTimer = null;
    let lmpSaveInFlight = false;
    let lmpSaveQueued = false;

    function openLmpModal(sku, entries) {
        lmpModalSku = sku || '';
        document.getElementById('lmpModalSku').textContent = lmpModalSku;
        $('#lmpNewPrice').val('');
        $('#lmpNewDelivery').val('');
        $('#lmpNewLink').val('');
        const tbody = $('#lmpEntriesContainer');
        tbody.empty();
        (Array.isArray(entries) ? entries : []).forEach(function(entry) {
            appendLmpTableRow(
                tbody,
                entry.price !== undefined && entry.price !== null ? entry.price : '',
                entry.delivery !== undefined && entry.delivery !== null ? entry.delivery : '',
                entry.link || '',
                !!entry.ignored,
                entry.source_sku || ''
            );
        });
        updateLmpLowestHighlight();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('lmpModal')).show();
    }

    function getLmpRowEffectivePrice(tr) {
        const num = parseFloat($(tr).find('.lmp-price').val());
        if (isNaN(num)) return null;
        let delivery = parseFloat($(tr).find('.lmp-delivery').val());
        if (isNaN(delivery) || delivery < 0) delivery = 0;
        if (delivery <= 0 && num < 27) delivery = 2.99;
        return num + delivery;
    }

    function appendLmpTableRow(tbody, price, delivery, link, ignored, sourceSku) {
        const tr = $('<tr class="lmp-entry-row">' +
            '<td class="lmp-num text-center align-middle"></td>' +
            '<td class="align-middle"><input type="number" step="0.01" min="0" class="form-control form-control-sm lmp-price border-0 bg-transparent" style="max-width:100px" placeholder="Price"> <span class="lmp-lowest-badge"></span></td>' +
            '<td class="align-middle"><input type="number" step="0.01" min="0" class="form-control form-control-sm lmp-delivery border-0 bg-transparent" style="max-width:90px" placeholder="0.00"></td>' +
            '<td class="align-middle text-center"><span class="lmp-price-d text-muted">—</span></td>' +
            '<td class="align-middle"><input type="text" class="form-control form-control-sm lmp-link d-inline-block me-1" style="max-width:200px" placeholder="https://..."> <a href="#" class="btn btn-sm btn-outline-primary lmp-open-link" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i></a></td>' +
            '<td class="align-middle text-center"><input type="checkbox" class="form-check-input lmp-ignore-cb" title="Ignore for L1"></td>' +
            '<td class="align-middle"><button type="button" class="btn btn-sm btn-outline-danger lmp-remove-row"><i class="fas fa-trash-alt"></i></button></td></tr>');
        tr.find('.lmp-price').val(price !== '' && price != null ? price : '');
        tr.find('.lmp-delivery').val(delivery !== '' && delivery != null ? delivery : '');
        tr.find('.lmp-link').val(link || '');
        tr.data('source-sku', sourceSku || lmpModalSku || '');
        tr.data('ignored', ignored ? 1 : 0);
        if (ignored) {
            tr.addClass('lmp-ignored-row');
            tr.find('.lmp-ignore-cb').prop('checked', true);
        }
        tbody.append(tr);
        updateLmpPriceD(tr);
        renumberLmpRows();
    }

    function updateLmpPriceD(tr) {
        const total = getLmpRowEffectivePrice(tr);
        const $el = $(tr).find('.lmp-price-d');
        if (total === null) $el.text('—').addClass('text-muted');
        else $el.text('$' + Number(total).toFixed(2)).removeClass('text-muted');
    }

    function renumberLmpRows() {
        $('#lmpEntriesContainer .lmp-entry-row').each(function(i) {
            $(this).find('.lmp-num').text(i + 1);
        });
    }

    function updateLmpLowestHighlight() {
        let minVal = null;
        let minTr = null;
        $('#lmpEntriesContainer .lmp-entry-row').each(function() {
            const tr = $(this);
            tr.removeClass('lmp-lowest-row');
            tr.find('.lmp-lowest-badge').empty();
            if (tr.hasClass('lmp-ignored-row') || tr.data('ignored') == 1) return;
            const total = getLmpRowEffectivePrice(tr);
            if (total === null) return;
            if (minVal === null || total < minVal) {
                minVal = total;
                minTr = tr;
            }
        });
        if (minTr) {
            minTr.addClass('lmp-lowest-row');
            minTr.find('.lmp-lowest-badge').html(' <span class="badge bg-info">LOWEST</span>');
        }
    }

    function collectLmpModalEntries() {
        const entries = [];
        $('#lmpEntriesContainer .lmp-entry-row').each(function() {
            const $tr = $(this);
            const price = $tr.find('.lmp-price').val();
            const delivery = $tr.find('.lmp-delivery').val();
            const link = $tr.find('.lmp-link').val();
            if (!price && !link && !delivery) return;
            const deliveryNum = delivery !== '' && delivery != null ? parseFloat(delivery) : 0;
            entries.push({
                price: price ? parseFloat(price) : null,
                delivery: (!isNaN(deliveryNum) && deliveryNum > 0) ? deliveryNum : 0,
                link: link ? String(link).trim() : null,
                ignored: $tr.hasClass('lmp-ignored-row') || $tr.data('ignored') == 1,
                source_sku: $tr.data('source-sku') || lmpModalSku
            });
        });
        return entries;
    }

    function syncLmpModalToTable(entries) {
        if (!table || !lmpModalSku) return;
        let best = null;
        (entries || []).forEach(function(e) {
            const meta = temuLmpEntryEffective(e);
            if (!meta) return;
            if (!best || meta.raw < best.raw) best = meta;
        });
        const raw = best ? best.raw : null;
        const recovery = raw != null ? temuLmpRecovery(raw) : null;
        let lowestLink = null;
        (entries || []).forEach(function(e) {
            const meta = temuLmpEntryEffective(e);
            if (meta && raw != null && +Number(meta.raw).toFixed(2) === +Number(raw).toFixed(2) && !lowestLink) {
                lowestLink = e.link || null;
            }
        });
        table.getRows().forEach(function(row) {
            const d = row.getData() || {};
            const sku = String(d.sku || d['(Child) sku'] || '');
            if (sku !== String(lmpModalSku)) return;
            const next = {
                lmp_entries: entries,
                lmp_raw: raw,
                lmp: recovery,
                lmp_link: lowestLink
            };
            const cap = typeof temuSpriceCapResult === 'function'
                ? temuSpriceCapResult(Object.assign({}, d, next))
                : null;
            if (cap && cap.sprice > 0) {
                next.sprice = cap.sprice;
                next.SPRICE = cap.sprice;
                next.sprice_labels = cap.labels;
                next.sprice_lmp_alert = cap.lmpAlert;
            }
            row.update(next);
            row.reformat();
        });
    }

    function saveLmpEntriesNow() {
        if (!lmpModalSku) return;
        if (lmpSaveInFlight) {
            lmpSaveQueued = true;
            return;
        }
        const entries = collectLmpModalEntries();
        lmpSaveInFlight = true;
        $.ajax({
            url: '{{ route("temu.lmp.save") }}',
            method: 'POST',
            contentType: 'application/json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            data: JSON.stringify({
                sku: lmpModalSku,
                lmp_entries: entries
            }),
            success: function(response) {
                if (response && response.success) {
                    syncLmpModalToTable(entries);
                } else {
                    showToast((response && (response.message || response.error)) || 'Failed to save LMP', 'error');
                }
            },
            error: function(xhr) {
                const msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Failed to save LMP';
                showToast(msg, 'error');
            },
            complete: function() {
                lmpSaveInFlight = false;
                if (lmpSaveQueued) {
                    lmpSaveQueued = false;
                    saveLmpEntriesNow();
                }
            }
        });
    }

    function scheduleLmpAutosave() {
        clearTimeout(lmpSaveTimer);
        lmpSaveTimer = setTimeout(saveLmpEntriesNow, 400);
    }

    function openNewTemuoneEditLinksModal(row) {
        newTemuoneEditLinksRow = row;
        const d = row.getData();
        const sku = d['(Child) sku'] || d.sku || '';
        document.getElementById('newTemuoneEditLinksSku').textContent = sku;
        document.getElementById('newTemuoneSellerLinkInput').value = d['S Link'] || '';
        document.getElementById('newTemuoneBuyerLinkInput').value = d['B Link'] || '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('newTemuoneEditLinksModal')).show();
    }

    function applyFilters() {
        if (!table) return;

        const inventoryFilter = $('#inventory-filter').val();
        const dilFilter = $('#dil-filter').val();
        const skuSearch = $('#sku-search').val() || '';
        const parentSearch = $('#parent-search').val() || '';

        table.clearFilter();

        if (inventoryFilter === 'zero') {
            table.addFilter('INV', '=', 0);
        } else if (inventoryFilter === 'more') {
            table.addFilter('INV', '>', 0);
        }

        if (skuSearch) {
            table.addFilter('(Child) sku', 'like', skuSearch);
        }
        if (parentSearch) {
            table.addFilter('Parent', 'like', parentSearch);
        }

        if (dilFilter !== 'all') {
            table.addFilter(function(data) {
                const inv = parseFloat(data['INV']) || 0;
                const l30 = parseFloat(data['L30']) || 0;
                const dil = inv === 0 ? 0 : (l30 / inv) * 100;

                if (dilFilter === 'red') return dil < 25;
                if (dilFilter === 'green') return dil >= 25 && dil < 50;
                if (dilFilter === 'pink') return dil >= 50;
                return true;
            });
        }
    }

    $(document).ready(function() {
        table = new Tabulator('#new-temuone-table', {
            ajaxURL: '{{ route("newtemuone.data.json") }}',
            ajaxSorting: false,
            layout: 'fitData',
            layoutColumnsOnNewData: true,
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: 'rows',
            langs: {
                default: {
                    pagination: {
                        page_size: 'SKU Count'
                    }
                }
            },
            initialSort: [{
                column: 'temu_l30',
                dir: 'desc'
            }],
            columns: [
                {
                    title: 'SKU',
                    field: '(Child) sku',
                    headerFilter: 'input',
                    headerFilterPlaceholder: 'Search SKU...',
                    cssClass: 'text-primary fw-bold',
                    tooltip: true,
                    frozen: true,
                    width: 250,
                    formatter: function(cell) {
                        const sku = cell.getValue() || '';
                        return `<span>${sku}</span><i class="fa fa-copy text-secondary copy-sku-btn"
                                   style="cursor: pointer; margin-left: 8px; font-size: 14px;"
                                   data-sku="${sku}"
                                   title="Copy SKU"></i>`;
                    }
                },
                {
                    title: 'Links',
                    field: 'links_column',
                    frozen: true,
                    width: 55,
                    hozAlign: 'center',
                    visible: true,
                    headerSort: false,
                    tooltip: 'Double-click to add / edit links',
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const buyerLink = rowData['B Link'] || '';
                        const sellerLink = rowData['S Link'] || '';

                        let html = '<div style="display:flex;flex-direction:column;gap:1px;line-height:1.1;">';
                        if (sellerLink) {
                            html += '<a href="' + String(sellerLink).replace(/"/g, '&quot;') + '" target="_blank" rel="noopener noreferrer" class="text-info" style="font-size:11px;text-decoration:none;" onclick="event.stopPropagation();"><i class="fa fa-link"></i> S</a>';
                        }
                        if (buyerLink) {
                            html += '<a href="' + String(buyerLink).replace(/"/g, '&quot;') + '" target="_blank" rel="noopener noreferrer" class="text-success" style="font-size:11px;text-decoration:none;" onclick="event.stopPropagation();"><i class="fa fa-link"></i> B</a>';
                        }
                        if (!sellerLink && !buyerLink) {
                            html += '<span class="text-muted" style="font-size:12px;">-</span>';
                        }
                        html += '</div>';
                        return html;
                    },
                    cellDblClick: function(e, cell) {
                        openNewTemuoneEditLinksModal(cell.getRow());
                    }
                },
                {
                    title: 'INV',
                    field: 'INV',
                    hozAlign: 'center',
                    width: 50,
                    sorter: 'number'
                },
                {
                    title: 'OV L30',
                    field: 'L30',
                    hozAlign: 'center',
                    width: 50,
                    sorter: 'number'
                },
                {
                    title: 'L30',
                    field: 'temu_l30',
                    hozAlign: 'center',
                    width: 50,
                    sorter: 'number',
                    headerTooltip: 'Temu L30 qty from temu_orders — same sales table and Pacific L30 window as /temu-tabulator Qty Purchased'
                },
                {
                    title: 'Views',
                    field: 'views',
                    hozAlign: 'center',
                    width: 60,
                    sorter: 'number',
                    headerTooltip: 'Same as /temu1-data Views: SUM(temu_view_data.product_clicks) by Goods ID; Ads API fallback when the sheet has no row'
                },
                {
                    title: 'CVR',
                    field: 'cvr_percent',
                    hozAlign: 'center',
                    width: 55,
                    sorter: 'number',
                    headerTooltip: 'CVR = (Temu L30 / Views) × 100 — same as /temu1-data',
                    formatter: function(cell) {
                        const val = parseFloat(cell.getValue()) || 0;
                        const color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
                        const label = (val > 3.5 ? String(Math.round(val)) : val.toFixed(1)) + '%';
                        return '<span style="color: ' + color + '; font-weight: 600;">' + label + '</span>';
                    }
                },
                {
                    title: 'Dil',
                    field: 'Dil%',
                    hozAlign: 'center',
                    sorter: 'number',
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const INV = parseFloat(rowData.INV) || 0;
                        const OVL30 = parseFloat(rowData['L30']) || 0;

                        if (INV === 0) return '<span style="color: #6c757d;">0%</span>';

                        const dil = (OVL30 / INV) * 100;
                        let color = '';

                        if (dil < 25) color = '#dc3545';
                        else if (dil >= 25 && dil < 50) color = '#28a745';
                        else color = '#e83e8c';

                        return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: 'Base Price',
                    field: 'base_price',
                    hozAlign: 'center',
                    width: 70,
                    sorter: 'number',
                    headerTooltip: 'temu_metrics.base_price',
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue());
                        if (value === null || value === undefined || isNaN(value) || value === 0) {
                            return '<span style="color: #6c757d;">—</span>';
                        }
                        return '<span style="font-weight: 600;">$' + value.toFixed(2) + '</span>';
                    }
                },
                {
                    title: 'R Price',
                    field: 'r_price',
                    hozAlign: 'center',
                    width: 70,
                    sorter: 'number',
                    headerTooltip: 'Normal Temu price (base + $2.99 when base ≤ $26.99)',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const base = parseFloat(row.base_price) || 0;
                        const rPrice = parseFloat(cell.getValue()) || 0;
                        if (!(rPrice > 0)) {
                            return '<span style="color: #6c757d;">—</span>';
                        }
                        const tip = base <= 26.99
                            ? ('R Price = Base + $2.99 → $' + base.toFixed(2) + ' + $2.99 = $' + rPrice.toFixed(2))
                            : ('R Price = Base (no +$2.99, base > $26.99) → $' + base.toFixed(2));
                        return '<span style="font-weight: 600;" title="' + tip + '">$' + rPrice.toFixed(2) + '</span>';
                    }
                },
                {
                    title: 'T Price',
                    field: 't_price',
                    hozAlign: 'center',
                    width: 70,
                    sorter: 'number',
                    headerTooltip: 'Temu Price = (Base × 1.1364); +$2.99 if that result ≤ $26.99',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const base = parseFloat(row.base_price) || 0;
                        const tPrice = parseFloat(cell.getValue()) || 0;
                        if (!(tPrice > 0) || !(base > 0)) {
                            return '<span style="color: #6c757d;">—</span>';
                        }
                        const afterMult = +(base * 1.1364).toFixed(2);
                        const tip = afterMult <= 26.99
                            ? ('T Price = (Base × 1.1364) + $2.99 → $' + base.toFixed(2) + ' × 1.1364 = $' + afterMult.toFixed(2) + ' + $2.99 = $' + tPrice.toFixed(2))
                            : ('T Price = (Base × 1.1364) → $' + base.toFixed(2) + ' × 1.1364 = $' + tPrice.toFixed(2) + ' (no +$2.99, result > $26.99)');
                        return '<span style="font-weight: 600;" title="' + tip + '">$' + tPrice.toFixed(2) + '</span>';
                    }
                },
                {
                    title: 'Std Price',
                    field: 'STANDARD_PRICE',
                    hozAlign: 'center',
                    width: 70,
                    sorter: 'number',
                    headerTooltip: 'Standard Price — same amazon_data_view.STANDARD_PRICE source as /temu2-decrease',
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue());
                        if (value === null || value === undefined || isNaN(value) || value <= 0) {
                            return '<span style="color: #6c757d;">—</span>';
                        }
                        return '<span style="font-weight: 600;">$' + value.toFixed(2) + '</span>';
                    }
                },
                {
                    title: 'LMP',
                    field: 'lmp_raw',
                    hozAlign: 'center',
                    width: 88,
                    sorter: 'number',
                    headerTooltip: 'Lowest LMP from the modal: Price + Delivery (Del $2.99 when Price < $27). Same as Temu 2.',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const raw = parseFloat(cell.getValue());
                        const displayVal = raw > 0 ? raw : null;
                        const display = displayVal != null
                            ? (displayVal % 1 === 0 ? displayVal.toLocaleString() : displayVal.toFixed(2))
                            : '-';
                        const sku = String(row.sku || row['(Child) sku'] || '').replace(/"/g, '&quot;');
                        const count = Array.isArray(row.lmp_entries) ? row.lmp_entries.length : 0;
                        const title = count > 0
                            ? ('Lowest LMP $' + (displayVal != null ? Number(displayVal).toFixed(2) : '-') + ' (' + count + ' entries) - click to edit')
                            : 'Click to add LMP';
                        return '<span class="lmp-display" title="' + title.replace(/"/g, '&quot;') + '">'
                            + (display !== '-' ? display : '<span style="color: #999;">-</span>')
                            + '</span> <button type="button" class="btn btn-sm btn-link p-0 lmp-eye-btn" data-sku="' + sku + '" title="' + title.replace(/"/g, '&quot;') + '"><i class="fas fa-info-circle text-info"></i></button>';
                    },
                    cellClick: function(e, cell) {
                        if (e.target.closest('.lmp-eye-btn')) {
                            e.stopPropagation();
                            const row = cell.getRow().getData();
                            openLmpModal(row.sku || row['(Child) sku'], row.lmp_entries || []);
                        }
                    }
                },
                {
                    title: 'S PRC',
                    field: 'sprice',
                    hozAlign: 'center',
                    width: 88,
                    sorter: 'number',
                    headerTooltip: 'Same formula as /temu2-decrease: Sprc Dil from the /temu1-data Dil table (including Temu L30 = 0), then the lowest of eBay, Amazon, and LMP. Saved on temu_data_view.',
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const model = typeof temuSpriceCellModel === 'function'
                            ? temuSpriceCellModel(rowData)
                            : { value: parseFloat(cell.getValue()) || 0, labels: [], lmpAlert: false, lmp: 0, amz: 0, ebay: 0 };
                        const value = model.value;
                        const live = parseFloat(rowData.temu_price || rowData.t_price) || 0;
                        const lmp = model.lmp || temuParseMoney(rowData.lmp_raw);
                        if (!(value > 0)) return '<span style="color: #6c757d;">—</span>';
                        const formatted = '$' + value.toFixed(2);
                        const overLmp = model.lmpAlert || (lmp > 0 && value >= lmp);
                        const priceHtml = overLmp
                            ? '<span style="color:#dc3545;font-weight:600;">' + formatted + '</span>'
                            : formatted;
                        const redTri = overLmp
                            ? '<i class="fas fa-exclamation-triangle" style="color:#dc3545;font-size:10px;margin-left:3px;" title="S PRC capped at LMP $'
                                + Number(lmp || 0).toFixed(2) + '"></i>'
                            : '';
                        const blueTri = (live > 0 && Math.round(value * 100) !== Math.round(live * 100))
                            ? '<i class="fas fa-exclamation-triangle" style="color:#0d6efd;font-size:10px;margin-left:3px;" title="S PRC $'
                                + value.toFixed(2) + ' ≠ T Price $' + live.toFixed(2) + '"></i>'
                            : '';
                        let capHtml = '';
                        (model.labels || []).forEach(function(lbl) {
                            const ref = lbl === 'Amz' ? model.amz : model.ebay;
                            const name = lbl === 'Amz' ? 'Amazon' : 'eBay';
                            capHtml += '<span class="temu-sprice-cap-lbl" title="S PRC capped to ' + name + ' $'
                                + Number(ref).toFixed(2) + '">' + lbl + '</span>';
                        });
                        return '<span style="white-space:nowrap;display:inline-flex;align-items:center;gap:2px;">'
                            + priceHtml + capHtml + redTri + blueTri + '</span>';
                    }
                }
            ]
        });

        table.on('dataLoaded', function() {
            applyFilters();
        });

        $('#sku-search, #parent-search').on('keyup', applyFilters);
        $('#inventory-filter, #dil-filter').on('change', applyFilters);

        $(document).on('click', '.copy-sku-btn', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            navigator.clipboard.writeText(sku).then(() => {
                showToast(`Copied: ${sku}`, 'success');
            });
        });

        $(document).on('click', '#newTemuoneSaveLinksBtn', function() {
            if (!newTemuoneEditLinksRow) return;
            const d = newTemuoneEditLinksRow.getData();
            const sku = d['(Child) sku'] || d.sku || '';
            const sellerLink = document.getElementById('newTemuoneSellerLinkInput').value.trim();
            const buyerLink = document.getElementById('newTemuoneBuyerLinkInput').value.trim();
            const $btn = $(this);
            $btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: '{{ route("newtemuone.save.links") }}',
                method: 'POST',
                data: {
                    sku: sku,
                    buyer_link: buyerLink,
                    seller_link: sellerLink
                },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(res) {
                    if (res && res.success) {
                        newTemuoneEditLinksRow.update({
                            'S Link': res.seller_link || '',
                            'B Link': res.buyer_link || ''
                        }).then(function() {
                            newTemuoneEditLinksRow.reformat();
                        }).catch(function() {
                            newTemuoneEditLinksRow.reformat();
                        });
                        showToast('Links saved', 'success');
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('newTemuoneEditLinksModal')).hide();
                    } else {
                        showToast((res && res.message) || 'Error saving links', 'error');
                    }
                },
                error: function(xhr) {
                    let msg = 'Error saving links';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    showToast(msg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Save');
                }
            });
        });

        $(document).on('click', '#lmpAddRowBtn', function() {
            const price = $('#lmpNewPrice').val();
            if (!price) {
                showToast('Price is required', 'error');
                return;
            }
            appendLmpTableRow(
                $('#lmpEntriesContainer'),
                price,
                $('#lmpNewDelivery').val(),
                $('#lmpNewLink').val(),
                false,
                lmpModalSku
            );
            $('#lmpNewPrice').val('');
            $('#lmpNewDelivery').val('');
            $('#lmpNewLink').val('');
            updateLmpLowestHighlight();
            scheduleLmpAutosave();
        });

        $(document).on('input change', '#lmpEntriesContainer .lmp-price, #lmpEntriesContainer .lmp-delivery, #lmpEntriesContainer .lmp-link', function() {
            updateLmpPriceD($(this).closest('tr'));
            updateLmpLowestHighlight();
            scheduleLmpAutosave();
        });

        $(document).on('change', '#lmpEntriesContainer .lmp-ignore-cb', function() {
            const $tr = $(this).closest('tr');
            if (this.checked) {
                $tr.addClass('lmp-ignored-row').data('ignored', 1);
            } else {
                $tr.removeClass('lmp-ignored-row').data('ignored', 0);
            }
            updateLmpLowestHighlight();
            scheduleLmpAutosave();
        });

        $(document).on('click', '#lmpEntriesContainer .lmp-remove-row', function() {
            $(this).closest('tr').remove();
            renumberLmpRows();
            updateLmpLowestHighlight();
            scheduleLmpAutosave();
        });

        $(document).on('click', '#lmpEntriesContainer .lmp-open-link', function(e) {
            const href = $(this).closest('tr').find('.lmp-link').val();
            if (!href) {
                e.preventDefault();
                return;
            }
            $(this).attr('href', href);
        });
    });
</script>
@endsection
