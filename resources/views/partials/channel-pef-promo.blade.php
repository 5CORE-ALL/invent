{{--
  Channel-independent Dil vs PRMT / CVR vs CPN + PRMT% / CPN% / Appr / DSC% / Push Prc / sprice ?
  Data stored on each channel's *_data_view.value with Amazon-format keys
  (PEF_PRMT_PCT, PEF_CPN_PCT, PEF_DSC_PCT, PEF_APPR, PUSH_PRC_STATUS, PUSH_PRC_VALUE, PUSH_PRC_PUSHED_AT).
  eBay1 → ebay_data_view  |  eBay2 → ebay_two_data_views  |  eBay3 → ebay3_data_view
  Include: css | buttons | modals | script | all  + channelPromoChannel key.
--}}
@php
    $channelPromoPart = $channelPromoPart ?? 'all';
    $channelPromoChannel = $channelPromoChannel ?? 'ebay1';
    $channelPromoHideCvrCpn = !empty($channelPromoHideCvrCpn);
    $channelPromoShowZeroSoldRules = !empty($channelPromoShowZeroSoldRules);
    $channelPromoShowGtSoldRules = !empty($channelPromoShowGtSoldRules);
    $channelPromoShowZeroSoldDilRule = in_array($channelPromoChannel, ['ebay2op', 'aliexpress'], true);
    $channelPromoZeroSoldDilColorSlabs = in_array($channelPromoChannel, ['aliexpress'], true);
    $channelPromoShowCvrUpDn = in_array($channelPromoChannel, ['ebay1', 'temu', 'temu2'], true);
    $channelPromoPageReloadPushEnabled = \App\Http\Controllers\MarketPlace\ChannelPromoPricingController::isPageReloadPushEnabled($channelPromoChannel);
@endphp

@if($channelPromoPart === 'css' || $channelPromoPart === 'all')
        /* Dil vs PRMT / CVR vs CPN — channel promo (ch-promo-*) */
        .ch-pef-promo-cell {
            font-size: inherit;
            font-weight: 600;
            color: #64748b;
        }
        .ch-pef-promo-cell.has-val { color: #0f172a; }
        .tabulator-row .tabulator-cell[tabulator-field="prmt_pct"],
        .tabulator-row .tabulator-cell[tabulator-field="b2b_discount"],
        .tabulator-row .tabulator-cell[tabulator-field="zero_sold_prmt"],
        .tabulator-row .tabulator-cell[tabulator-field="gt_sold_pct"],
        .tabulator-row .tabulator-cell[tabulator-field="sale_event"],
        .tabulator-row .tabulator-cell[tabulator-field="push_prmt"],
        .tabulator-row .tabulator-cell[tabulator-field="cpn_pct"],
        .tabulator-row .tabulator-cell[tabulator-field="push_cpn"],
        .tabulator-row .tabulator-cell[tabulator-field="push_std_prc"],
        .tabulator-row .tabulator-cell[tabulator-field="dsc"],
        .tabulator-row .tabulator-cell[tabulator-field="appr"] {
            padding: 2px 4px !important;
        }
        @keyframes ch-promo-spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .ebay-push-std-prc-btn .fa-spinner,
        .ch-promo-push-prc-btn .fa-spinner,
        .ch-promo-sale-event-btn .fa-spinner,
        .ch-promo-push-prmt-btn .fa-spinner,
        .ch-promo-push-cpn-col-btn .fa-spinner,
        .ch-promo-push-cpn-queue-btn .fa-spinner {
            display: inline-block !important;
            animation: ch-promo-spin 0.75s linear infinite !important;
        }
        .ch-promo-col-del {
            border: none;
            background: none;
            cursor: pointer;
            color: #dc3545;
            padding: 0;
            line-height: 1;
            margin-left: 3px;
            font-size: 11px;
            vertical-align: middle;
        }
        .ch-promo-col-del:hover,
        .ch-promo-col-del:focus {
            color: #a71d2a;
        }
        .ch-promo-header-with-del {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            line-height: 1.15;
        }
        #ch-promo-dil-prmt-table .ch-promo-b2b-disc-input {
            background: #198754;
            border-color: #198754;
            color: #fff;
            font-weight: 700;
            text-align: right;
            pointer-events: none;
        }
        #ch-promo-dil-prmt-table .ch-promo-dil-prmt-input,
        #ch-promo-zero-sold-prmt-table .ch-promo-dil-prmt-input,
        #ch-promo-gt-sold-prc-table .ch-promo-gt-sold-pct-input,
        #ch-promo-cvr-cpn-table .ch-promo-cvr-cpn-input,
        #ch-promo-zero-sold-dil-table .ch-promo-zero-sold-dil-roi-input,
        #ch-promo-prmt-menu-btn,
        #ch-promo-dil-vs-prmt-btn {
            background: #198754;
            border-color: #198754;
            color: #fff;
        }
        #ch-promo-prmt-menu-btn:hover,
        #ch-promo-prmt-menu-btn:focus,
        #ch-promo-prmt-menu-btn.show,
        #ch-promo-dil-vs-prmt-btn:hover,
        #ch-promo-dil-vs-prmt-btn:focus,
        #ch-promo-zero-sold-menu-btn:hover,
        #ch-promo-zero-sold-menu-btn:focus,
        #ch-promo-zero-sold-menu-btn.show {
            background: #157347;
            border-color: #146c43;
            color: #fff;
        }
        #ch-promo-zero-sold-menu-btn {
            background: #198754;
            border-color: #198754;
            color: #fff;
        }
        #ch-promo-gt-sold-rule-btn {
            background: #4f46e5;
            border-color: #4f46e5;
            color: #fff;
        }
        #ch-promo-gt-sold-rule-btn:hover,
        #ch-promo-gt-sold-rule-btn:focus {
            background: #4338ca;
            border-color: #4338ca;
            color: #fff;
        }
        #ch-promo-gt-sold-prc-table .ch-promo-gt-sold-dir-select {
            min-width: 108px;
        }
        #ch-promo-cpn-menu-btn,
        #ch-promo-cvr-vs-cpn-btn {
            background: #20c997;
            border-color: #20c997;
            color: #fff;
        }
        #ch-promo-cpn-menu-btn:hover,
        #ch-promo-cpn-menu-btn:focus,
        #ch-promo-cpn-menu-btn.show,
        #ch-promo-cvr-vs-cpn-btn:hover,
        #ch-promo-cvr-vs-cpn-btn:focus {
            background: #1aa179;
            border-color: #1aa179;
            color: #fff;
        }
        #ch-promo-sprice-recalc-btn {
            background: #0d6efd;
            border-color: #0d6efd;
            color: #fff;
        }
        #ch-promo-sprice-recalc-btn:hover,
        #ch-promo-sprice-recalc-btn:focus {
            background: #0b5ed7;
            border-color: #0a58ca;
            color: #fff;
        }
        #ch-promo-sprice-recalc-btn:disabled,
        #ch-promo-sprice-vs-tpromo-btn:disabled {
            opacity: 0.65;
        }
        @if(!empty($channelPromoShowCvrUpDn))
        @include('partials.cvr-up-dn', ['cvrUpDnPart' => 'css', 'cvrUpDnChannel' => $channelPromoChannel])
        @endif
        .ch-promo-reload-push-switch {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex: 0 0 auto;
            white-space: nowrap;
            padding: 4px 10px 4px 12px;
            border: 1px solid #86efac;
            border-radius: 999px;
            background: #f0fdf4;
            font-size: 12px;
            font-weight: 700;
            color: #15803d;
            line-height: 1.2;
            cursor: pointer;
            user-select: none;
            margin: 0;
        }
        .ch-promo-reload-push-switch.is-off {
            border-color: #cbd5e1;
            background: #f8fafc;
            color: #64748b;
        }
        .ch-promo-reload-push-switch .ch-promo-reload-push-text {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .ch-promo-reload-push-switch .ch-promo-reload-push-state {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #16a34a;
        }
        .ch-promo-reload-push-switch.is-off .ch-promo-reload-push-state {
            color: #94a3b8;
        }
        .ch-promo-reload-push-switch > input[type="checkbox"] {
            appearance: none;
            -webkit-appearance: none;
            position: relative !important;
            float: none !important;
            left: auto !important;
            margin: 0 !important;
            flex: 0 0 36px;
            width: 36px;
            height: 20px;
            border: 0;
            border-radius: 999px;
            background: #86efac;
            box-shadow: inset 0 0 0 1px #4ade80;
            cursor: pointer;
        }
        .ch-promo-reload-push-switch.is-off > input[type="checkbox"] {
            background: #cbd5e1;
            box-shadow: inset 0 0 0 1px #94a3b8;
        }
        .ch-promo-reload-push-switch > input[type="checkbox"]::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .25);
            transition: left .15s ease;
        }
        .ch-promo-reload-push-switch > input[type="checkbox"]:checked::after {
            left: 18px;
        }
        #ch-promo-sprice-vs-tpromo-btn {
            background: #0d6efd;
            border-color: #0d6efd;
            color: #fff;
        }
        #ch-promo-sprice-vs-tpromo-btn:hover,
        #ch-promo-sprice-vs-tpromo-btn:focus {
            background: #0b5ed7;
            border-color: #0a58ca;
            color: #fff;
        }
        #ch-promo-sprice-vs-tpromo-del-btn {
            background: #dc3545;
            border-color: #dc3545;
            color: #fff;
        }
        #ch-promo-sprice-vs-tpromo-del-btn:hover,
        #ch-promo-sprice-vs-tpromo-del-btn:focus {
            background: #bb2d3b;
            border-color: #b02a37;
            color: #fff;
        }
        #ch-promo-sprice-vs-tpromo-del-btn:disabled {
            opacity: 0.65;
        }
        #ch-promo-zero-sold-vs-dil-btn {
            background: #0f766e;
            border-color: #0f766e;
            color: #fff;
        }
        #ch-promo-zero-sold-vs-dil-btn:hover,
        #ch-promo-zero-sold-vs-dil-btn:focus {
            background: #0d9488;
            border-color: #0f766e;
            color: #fff;
        }
        #ch-promo-zero-sold-vs-dil-btn:disabled {
            opacity: 0.65;
        }
        #ch-promo-push-prc-progress {
            display: none;
            width: 100%;
            min-width: 220px;
            max-width: 420px;
            margin: 4px 0 0;
            padding: 6px 10px;
            border: 1px solid #ffe08a;
            border-radius: 8px;
            background: #fffbeb;
        }
        #ch-promo-push-prc-progress.active {
            display: block;
            position: fixed;
            right: 16px;
            bottom: 16px;
            z-index: 10850;
            min-width: 300px;
            max-width: 440px;
            margin: 0;
            padding: 12px 14px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.18);
        }
        #ch-promo-push-prc-progress.done {
            border-color: #86efac;
            background: #f0fdf4;
        }
        #ch-promo-push-prc-progress .ch-promo-push-prc-progress-head {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
            font-size: 13px;
            font-weight: 700;
            color: #b45309;
        }
        #ch-promo-push-prc-progress.done .ch-promo-push-prc-progress-head {
            color: #15803d;
        }
        #ch-promo-push-prc-progress-spin {
            display: none;
            color: #f59e0b;
        }
        #ch-promo-push-prc-progress.active:not(.done) #ch-promo-push-prc-progress-spin {
            display: inline-block;
        }
        #ch-promo-push-prc-progress .ch-promo-push-prc-progress-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 6px;
            font-size: 12px;
            line-height: 1.2;
        }
        #ch-promo-push-prc-progress-pct {
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            color: #b45309;
            margin-left: auto;
            min-width: 2.5em;
            text-align: right;
        }
        #ch-promo-push-prc-progress.done #ch-promo-push-prc-progress-pct {
            color: #15803d;
        }
        #ch-promo-push-prc-progress-msg {
            color: #64748b;
            flex: 1;
            text-align: left;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #ch-promo-push-prc-progress .ch-promo-push-prc-bar {
            height: 10px;
            border-radius: 999px;
            background: #fde68a;
            overflow: hidden;
        }
        #ch-promo-push-prc-progress.done .ch-promo-push-prc-bar {
            background: #bbf7d0;
        }
        #ch-promo-push-prc-progress .ch-promo-push-prc-bar > span {
            display: block;
            height: 100%;
            width: 0%;
            min-width: 0;
            background: #f59e0b;
            transition: width 0.25s ease, background 0.25s ease;
            border-radius: 999px;
        }
        #ch-promo-push-prc-progress.active:not(.done) .ch-promo-push-prc-bar > span {
            min-width: 8%;
            animation: chPromoPushBarPulse 1.2s ease-in-out infinite;
        }
        @keyframes chPromoPushBarPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        #ch-promo-push-prc-progress.done .ch-promo-push-prc-bar > span {
            background: #22c55e;
            animation: none;
            min-width: 0;
        }
        #ch-promo-push-prc-progress.has-fail.done .ch-promo-push-prc-bar > span {
            background: linear-gradient(90deg, #22c55e 70%, #f59e0b 100%);
        }
        #chPromoCvrVsCpnModal .modal-content {
            background: #f3e8ff;
            border-color: #e9d5ff;
        }
        #chPromoCvrVsCpnModal .modal-header,
        #chPromoCvrVsCpnModal .modal-footer {
            background: #f3e8ff;
            border-color: #e9d5ff;
        }
        #chPromoCvrVsCpnModal .modal-body {
            background: #f3e8ff;
        }
        #chPromoCvrVsCpnModal .table {
            background: #fff;
        }
        #chPromoCvrVsCpnModal .table thead.table-light th {
            background: #ede9fe;
        }
        /* Analytics tables: show full cell values; scroll horizontally instead of "…" */
        .tabulator .tabulator-tableholder {
            overflow-x: auto !important;
        }
        .tabulator .tabulator-row .tabulator-cell {
            white-space: nowrap !important;
            text-overflow: clip !important;
        }
        @include('partials.analytics-column-visibility', ['colVisPart' => 'css'])
        @push('page-title-after')
            <label class="ch-promo-reload-push-switch{{ $channelPromoPageReloadPushEnabled ? '' : ' is-off' }}"
                id="ch-promo-reload-push-wrap"
                title="When ON, this page auto-pushes price on reload. When OFF, reload does not push. Daily cron still pushes either way.">
                <span class="ch-promo-reload-push-text">
                    Push on reload
                    <span class="ch-promo-reload-push-state" id="ch-promo-reload-push-label">{{ $channelPromoPageReloadPushEnabled ? 'On' : 'Off' }}</span>
                </span>
                <input type="checkbox" role="switch" id="ch-promo-reload-push-switch"
                    {{ $channelPromoPageReloadPushEnabled ? 'checked' : '' }}>
            </label>
        @endpush
@endif

@if($channelPromoPart === 'buttons' || $channelPromoPart === 'all')
                    @include('partials.sprice-lmp-cap-script')
                    @unless(in_array($channelPromoChannel, ['macys', 'macy']))
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm" id="ch-promo-dil-vs-prmt-btn"
                            title="{{ $channelPromoChannel === 'shopify_b2b' ? 'B2B discount: map Dil% slabs (0–0 → 12 … 22%+ → 0). Auto-fills B2B disc; Apply writes PRMT%.' : 'Map Dil% slabs to PRMT%. Changing any number fills the PRMT% column. Does not create a sale event.' }}">
                            <i class="fas fa-sliders-h"></i> {{ $channelPromoChannel === 'shopify_b2b' ? 'B2B discount' : ($channelPromoChannel === 'aliexpress' ? 'PRMT%' : 'Prmt%') }}
                        </button>
                        @if(in_array($channelPromoChannel, ['ebay1', 'ebay2', 'ebay2op', 'ebay3'], true))
                        <button type="button" class="btn btn-sm dropdown-toggle dropdown-toggle-split" id="ch-promo-prmt-menu-btn"
                            data-bs-toggle="dropdown" aria-expanded="false" title="More Prmt% actions">
                            <span class="visually-hidden">More</span>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="ch-promo-prmt-menu-btn">
                            <li>
                                <a class="dropdown-item text-danger" href="#" id="ch-promo-end-sales-btn">
                                    <i class="fas fa-ban me-1"></i> End all sales
                                </a>
                            </li>
                        </ul>
                        @endif
                    </div>
                    @endunless
                    @if($channelPromoShowZeroSoldRules)
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm dropdown-toggle" id="ch-promo-zero-sold-menu-btn"
                            data-bs-toggle="dropdown" aria-expanded="false"
                            title="{{ in_array($channelPromoChannel, ['macys', 'macy']) ? '0 Sold: apply Amazon Price to S PRC' : '0 Sold Dil color rules + apply/push Prmt%' }}">
                            <i class="fas fa-sliders-h"></i> 0 Sold
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="ch-promo-zero-sold-menu-btn">
                            <li>
                                <a class="dropdown-item" href="#" id="ch-promo-zero-sold-rules-btn">
                                    @if(in_array($channelPromoChannel, ['macys', 'macy']))
                                    <i class="fas fa-sliders-h me-1 text-success"></i> Apply Amazon Price…
                                    @else
                                    <i class="fas fa-sliders-h me-1 text-success"></i> Dil Color vs PRMT…
                                    @endif
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" id="ch-promo-push-zero-sold-btn">
                                    @if(in_array($channelPromoChannel, ['macys', 'macy']))
                                    <i class="fas fa-upload me-1 text-success"></i> Push Price
                                    @else
                                    <i class="fas fa-upload me-1 text-success"></i> Push Prmt%
                                    @endif
                                </a>
                            </li>
                        </ul>
                    </div>
                    @endif
                    @if($channelPromoShowGtSoldRules)
                    <button type="button" class="btn btn-sm" id="ch-promo-gt-sold-rule-btn"
                        title=">0 Sold Dil color rules: add or subtract % from Std Prc → S PRC. Save, Apply, or Push from the modal.">
                        <i class="fas fa-sliders-h"></i> &gt;0 Sold Rule
                    </button>
                    @endif
                    @unless($channelPromoHideCvrCpn)
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm" id="ch-promo-cvr-vs-cpn-btn"
                            title="Map CVR% slabs to CPN%. Apply writes CPN% only (no coupon push).">
                            CVR%
                        </button>
                        @if(in_array($channelPromoChannel, ['ebay1', 'ebay2', 'ebay2op', 'ebay3'], true))
                        <button type="button" class="btn btn-sm dropdown-toggle dropdown-toggle-split" id="ch-promo-cpn-menu-btn"
                            data-bs-toggle="dropdown" aria-expanded="false" title="More CVR% actions">
                            <span class="visually-hidden">More</span>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="ch-promo-cpn-menu-btn">
                            <li>
                                <a class="dropdown-item text-danger" href="#" id="ch-promo-end-coupons-btn">
                                    <i class="fas fa-ban me-1"></i> End all coupons
                                </a>
                            </li>
                        </ul>
                        @endif
                    </div>
                    @endunless
                    @if(!empty($channelPromoShowCvrUpDn))
                    @include('partials.cvr-up-dn', ['cvrUpDnPart' => 'buttons', 'cvrUpDnChannel' => $channelPromoChannel])
                    @endif
                    @unless(in_array($channelPromoChannel, ['macys', 'macy']))
                    @if(in_array($channelPromoChannel, ['ebay1', 'ebay2', 'ebay2op', 'ebay3']))
                    @unless(in_array($channelPromoChannel, ['ebay1', 'ebay2', 'ebay3'], true))
                    <div class="btn-group" role="group">
                    <button type="button" class="btn btn-sm" id="ch-promo-sprice-vs-tpromo-btn"
                        title="Autofill S PRC = Std × (1 − T Promo/100). T Promo = PRMT% + CPN% + CVR Up/Dn. Selected SKUs if checked; otherwise all visible. Skips INV = 0. No marketplace push. S PRC ≥ LMP is capped at LMP and keeps a red triangle after push.">
                        Sprice vs T promo
                    </button>
                    <button type="button" class="btn btn-sm" id="ch-promo-sprice-vs-tpromo-del-btn"
                        title="Clear S PRC filled by Sprice vs T promo. Selected SKUs if checked; otherwise all visible. No marketplace push.">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    </div>
                    @endunless
                    @if($channelPromoShowZeroSoldDilRule)
                    <button type="button" class="btn btn-sm" id="ch-promo-zero-sold-vs-dil-btn"
                        title="0 Sold vs Dil: map Dil% slabs to Target ROI%, then set S PRC so GROI equals that target. Only SKUs with E L30 = 0. Selected if checked; otherwise all visible. No marketplace push.">
                        0 sold Vs Dil Rule
                    </button>
                    @endif
                    @endif
                    @endunless
                    @if($channelPromoShowZeroSoldDilRule && $channelPromoChannel === 'aliexpress')
                    <button type="button" class="btn btn-sm" id="ch-promo-zero-sold-vs-dil-btn"
                        title="0 Sold vs ROI: Dil color → Target ROI%. Autopopulate writes PRMT% and cvr% (S PRC = Std − PRMT% − cvr%) when no saved promo exists (AL30 = 0, INV &gt; 0). Pause stops mid-run. No marketplace push.">
                        0 sold vs ROI
                    </button>
                    @endif
                    <div id="ch-promo-push-prc-progress" aria-live="polite" title="Push progress">
                        <div class="ch-promo-push-prc-progress-head">
                            <i class="fas fa-spinner fa-spin" id="ch-promo-push-prc-progress-spin"></i>
                            <span id="ch-promo-push-prc-progress-title">Pushing</span>
                            <span id="ch-promo-push-prc-progress-pct">0%</span>
                        </div>
                        <div class="ch-promo-push-prc-progress-meta">
                            <span id="ch-promo-push-prc-progress-msg">Ready</span>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" id="ch-promo-push-prc-cancel-btn"
                                style="display:none;font-size:11px;line-height:1.2;" title="Cancel remaining Push Prc">
                                Cancel
                            </button>
                        </div>
                        <div class="ch-promo-push-prc-bar"><span id="ch-promo-push-prc-progress-bar"></span></div>
                    </div>
@endif

@if($channelPromoPart === 'modals' || $channelPromoPart === 'all')

    @unless($channelPromoHideCvrCpn)
    <div class="modal fade" id="chPromoCvrVsCpnModal" tabindex="-1" aria-labelledby="chPromoCvrVsCpnModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="chPromoCvrVsCpnModalLabel">
                        CVR%
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2" id="ch-promo-cvr-cpn-help">
                        @if($channelPromoChannel === 'ebay2op')
                            Map CVR% slabs to <strong>CPN %</strong> (no 0% slab).
                            Change a slab to autofill rows below by <strong>−1</strong> each (min 0).
                            <strong>Save Rule</strong> stores the slabs.
                            <strong>Apply</strong> writes CPN% only on SKUs with <strong>eBay sale (E L30) &gt; 0</strong>
                            (database only — no eBay coupon).
                        @elseif(in_array($channelPromoChannel, ['ebay2', 'ebay3'], true))
                            Map CVR% slabs to <strong>CPN %</strong>.
                            Change a slab to autofill rows below by <strong>−1</strong> each (min 0).
                            <strong>Save Rule</strong> stores the slabs.
                            <strong>Apply</strong> writes CPN% only on SKUs with <strong>eBay sale (E L30) &gt; 0</strong>
                            (database only — no eBay coupon).
                        @else
                            Map CVR% slabs to <strong>CPN %</strong>.
                            Change a slab to autofill rows below by <strong>−1</strong> each (min 0).
                            <strong>Save Rule</strong> stores the slabs.
                            Apply then writes CPN% to the database only (no eBay coupon).
                        @endif
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="ch-promo-cvr-cpn-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:55%;">CVR%</th>
                                    <th style="width:45%;" class="text-end">CPN %</th>
                                </tr>
                            </thead>
                            <tbody id="ch-promo-cvr-cpn-tbody"></tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2" id="ch-promo-cvr-cpn-status"></div>
                </div>
                <div class="modal-footer py-2 flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-primary" id="ch-promo-cvr-cpn-save-btn"
                        title="Save CVR→CPN slab values only (does not write SKU CPN%).">
                        <i class="fas fa-save me-1"></i>Save Rule
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="ch-promo-cvr-cpn-apply-btn"
                        title="{{ in_array($channelPromoChannel, ['ebay2', 'ebay2op', 'ebay3'], true) ? 'Save CVR→CPN rules, then write CPN% only on SKUs with E L30 > 0 (database only — no eBay coupon)' : 'Save CVR→CPN rules and CPN% to the database only — does not create eBay coupons' }}">
                        Apply
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endunless

    @if(!empty($channelPromoShowCvrUpDn))
    @include('partials.cvr-up-dn', ['cvrUpDnPart' => 'modals', 'cvrUpDnChannel' => $channelPromoChannel])
    @endif

    <div class="modal fade" id="chPromoDilVsPrmtModal" tabindex="-1" aria-labelledby="chPromoDilVsPrmtModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="chPromoDilVsPrmtModalLabel">
                        <i class="fas fa-sliders-h me-1"></i> {{ $channelPromoChannel === 'shopify_b2b' ? 'B2B discount' : 'Dil vs PRMT' }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2" id="ch-promo-dil-prmt-help">
                        @if(in_array($channelPromoChannel, ['tiktok', 'tiktok2'], true))
                        Same Dil vs PRMT map as Amazon: <strong>0–10% → 10</strong> down to
                        <strong>&gt; 100% → 0</strong>. Dil% = <strong>OV L30 ÷ INV</strong>.
                        Changing any slab fills <strong>PRMT %</strong>.
                        <strong>Save and Apply</strong> writes PRMT% (selected, or all visible).
                        If <strong>INV = 0</strong>, PRMT% is <strong>0</strong>.
                        @else
                        Map Dil% slabs to PRMT%. Changing <strong>any</strong> slab number immediately
                        fills the <strong>PRMT %</strong> column. <strong>Save and Apply</strong> saves rules
                        and writes PRMT% for this channel. Does <strong>not</strong> create an eBay sale event.
                        If <strong>INV = 0</strong>, PRMT% is forced to <strong>0</strong>.
                        @endif
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="ch-promo-dil-prmt-table">
                            <thead class="table-light">
                                <tr>
                                    @if($channelPromoChannel === 'shopify_b2b')
                                    <th style="width:40%;">Dil%</th>
                                    <th style="width:30%;" class="text-end">B2B disc</th>
                                    <th style="width:30%;" class="text-end">PRMT %</th>
                                    @else
                                    <th style="width:55%;">Dil%</th>
                                    <th style="width:45%;" class="text-end">PRMT %</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody id="ch-promo-dil-prmt-tbody"></tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2" id="ch-promo-dil-prmt-status"></div>
                </div>
                <div class="modal-footer py-2 flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-primary" id="ch-promo-dil-prmt-apply-btn"
                        @if(in_array($channelPromoChannel, ['tiktok', 'tiktok2'], true))
                        title="Save Amazon Dil→PRMT rules, then write PRMT% — selected rows if checked, otherwise all visible. INV = 0 → PRMT% = 0."
                        @else
                        title="Save Dil→PRMT rules, then write PRMT% — selected rows if checked, otherwise all visible. Only SKUs with sold &gt; 0 (eBay E L30, AliExpress AL30). 0-sold SKUs are skipped. Does not create a sale event."
                        @endif>
                        <i class="fas fa-save me-1"></i>Save and Apply
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if($channelPromoShowZeroSoldDilRule)
    <div class="modal fade" id="chPromoZeroSoldVsDilModal" tabindex="-1" aria-labelledby="chPromoZeroSoldVsDilModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="chPromoZeroSoldVsDilModalLabel">
                        @if($channelPromoZeroSoldDilColorSlabs)
                        <i class="fas fa-sliders-h me-1"></i> 0 sold vs ROI
                        @else
                        <i class="fas fa-sliders-h me-1"></i> 0 sold Vs Dil Rule
                        @endif
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    @if($channelPromoZeroSoldDilColorSlabs)
                    <p class="small text-muted mb-2" id="ch-promo-zero-sold-dil-help">
                        Map <strong>Dil color</strong> to <strong>Target ROI%</strong>
                        (<strong style="color:#a00211;">Red &lt;25%</strong>,
                        <strong style="color:#28a745;">Green 25–50%</strong>,
                        <strong style="color:#e83e8c;">Pink 50%+</strong>).
                        Dil = <strong>OV L30 ÷ INV</strong>.
                        <strong>This rule applies only when AL30 = 0</strong>
                        (sold SKUs use <strong>Dil vs PRMT</strong> instead).
                        <strong>Save Rule</strong> and <strong>Autopopulate</strong> write
                        <strong>PRMT%</strong>, <strong>cvr%</strong>, and <strong>S PRC</strong>
                        on selected or visible SKUs with
                        <strong>AL30 = 0</strong>, <strong>INV &gt; 0</strong>, <strong>LP &gt; 0</strong>
                        so <strong>SROI = Target ROI%</strong> on <strong>Std Prc</strong>
                        (not live <strong>Price</strong>):
                        <code>S PRC = Std × (1 − (PRMT% + cvr%)/100)</code>.
                        Target ROI% picks the discount off Std
                        (<code>need S PRC = (LP × (1 + ROI%/100) + Ship) / margin</code>).
                        Discount off Std is split: <strong>PRMT%</strong> from Dil vs PRMT (if it fits),
                        remainder on <strong>cvr%</strong>.
                        Use <strong>Pause</strong> to stop mid-run. No marketplace push.
                    </p>
                    @else
                    <p class="small text-muted mb-2" id="ch-promo-zero-sold-dil-help">
                        Map Dil% slabs (<strong>0–10%</strong> … <strong>&gt; 100%</strong>) to
                        <strong>Target ROI%</strong>. Dil is <strong>OV L30 ÷ INV</strong> (same Dil column).
                        <strong>Apply</strong> writes <strong>S PRC</strong> only on selected or visible SKUs with
                        <strong>eBay sale (E L30) = 0</strong>, <strong>INV &gt; 0</strong>, and <strong>LP &gt; 0</strong>
                        so <strong>GROI = Target ROI%</strong>
                        (<code>S PRC = (LP × (1 + ROI%/100) + Ship) / margin</code>).
                        No marketplace push.
                    </p>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="ch-promo-zero-sold-dil-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:55%;">{{ $channelPromoZeroSoldDilColorSlabs ? 'Dil Color' : 'Dil%' }}</th>
                                    <th style="width:45%;" class="text-end">Target ROI%</th>
                                </tr>
                            </thead>
                            <tbody id="ch-promo-zero-sold-dil-tbody"></tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2" id="ch-promo-zero-sold-dil-status"></div>
                    <div class="small fw-semibold mt-1" id="ch-promo-zero-sold-dil-progress" style="display:none;"></div>
                </div>
                <div class="modal-footer py-2 flex-wrap gap-1">
                    @if($channelPromoZeroSoldDilColorSlabs)
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="ch-promo-zero-sold-dil-save-btn"
                        title="Save Dil color → Target ROI% rules, then set PRMT%, cvr%, S PRC, and SROI on AL30 = 0 SKUs only">
                        <i class="fas fa-save me-1"></i> Save Rule
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="ch-promo-zero-sold-dil-apply-btn"
                        title="Save rules, then autopopulate S PRC on 0 Sold (AL30 = 0) SKUs from Dil→Target ROI%">
                        <i class="fas fa-magic me-1"></i> Autopopulate
                    </button>
                    <button type="button" class="btn btn-sm btn-warning" id="ch-promo-zero-sold-dil-pause-btn"
                        title="Pause Autopopulate" disabled>
                        <i class="fas fa-pause me-1"></i> Pause
                    </button>
                    @else
                    <button type="button" class="btn btn-sm btn-primary" id="ch-promo-zero-sold-dil-apply-btn"
                        title="Save Dil→Target GROI% rules, then set S PRC so GROI = Target on 0 Sold (E L30 = 0) SKUs">
                        Apply
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($channelPromoShowZeroSoldRules)
    <div class="modal fade" id="chPromoZeroSoldPrmtModal" tabindex="-1" aria-labelledby="chPromoZeroSoldPrmtModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="chPromoZeroSoldPrmtModalLabel">
                        @if(in_array($channelPromoChannel, ['macys', 'macy']))
                        <i class="fas fa-sliders-h me-1"></i> 0 Sold Rule
                        @else
                        <i class="fas fa-sliders-h me-1"></i> Dil Color vs PRMT
                        @endif
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    @if(in_array($channelPromoChannel, ['macys', 'macy']))
                    <p class="small text-muted mb-2">
                        For <strong>0 Sold</strong> only (<strong>MC L30 = 0</strong>).
                        <strong>Apply</strong> copies <strong>Amazon Price (A Price)</strong> onto
                        <strong>S PRC</strong> for each selected or visible 0 Sold SKU.
                        Skips <strong>INV = 0</strong> and missing A Price.
                        <strong>Push</strong> applies A Price, then sends S PRC to Macy.
                    </p>
                    <div class="small text-muted mt-2" id="ch-promo-zero-sold-prmt-status">
                        Apply Amazon Price to S PRC.
                    </div>
                    @else
                    <p class="small text-muted mb-2">
                        Rules for <strong>0 Sold</strong> only (<strong>RV L30 = 0</strong>), by Dil color:
                        <strong style="color:#a00211;">Red &lt;25%</strong>,
                        <strong style="color:#28a745;">Green 25–50%</strong>,
                        <strong style="color:#e83e8c;">Pink 50%+</strong>.
                        <strong>Apply</strong> writes <strong>PRMT %</strong> on each selected or visible 0 Sold SKU.
                        If <strong>INV = 0</strong>, PRMT% is <strong>0</strong>.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="ch-promo-zero-sold-prmt-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:55%;">Dil Color</th>
                                    <th style="width:45%;" class="text-end">PRMT %</th>
                                </tr>
                            </thead>
                            <tbody id="ch-promo-zero-sold-prmt-tbody"></tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2" id="ch-promo-zero-sold-prmt-status"></div>
                    @endif
                </div>
                <div class="modal-footer py-2 flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-primary" id="ch-promo-zero-sold-prmt-apply-btn"
                        title="{{ in_array($channelPromoChannel, ['macys', 'macy']) ? 'Apply Amazon Price to S PRC for 0 Sold rows' : 'Save 0 Sold Dil-color rules, then apply PRMT% to 0 Sold rows' }}">
                        Apply
                    </button>
                    @if(in_array($channelPromoChannel, ['macys', 'macy']))
                    <button type="button" class="btn btn-sm btn-warning" id="ch-promo-zero-sold-amz-push-btn"
                        title="Apply Amazon Price to S PRC, then push to Macy">
                        <i class="fas fa-upload me-1"></i> Push
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($channelPromoShowGtSoldRules)
    <div class="modal fade" id="chPromoGtSoldPrcModal" tabindex="-1" aria-labelledby="chPromoGtSoldPrcModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="chPromoGtSoldPrcModalLabel">
                        <i class="fas fa-sliders-h me-1"></i> &gt;0 Sold Rule
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2">
                        Rules for <strong>&gt;0 Sold</strong> only, by Dil color:
                        <strong style="color:#a00211;">Red Dil &lt;25% → Amazon Price (A Price)</strong>,
                        <strong style="color:#28a745;">Green Dil 25–50%</strong>,
                        <strong style="color:#e83e8c;">Pink Dil 50%+</strong>.
                        Green / Pink use <strong>%</strong> added to or subtracted from <strong>Std Prc</strong>
                        (<strong>S PRC = Std × (1 ± %/100)</strong>).
                        <strong>Save Rule</strong> stores Green / Pink.
                        <strong>Apply</strong> writes S PRC (no marketplace push).
                        <strong>Push</strong> sends S PRC to the channel.
                        If <strong>INV = 0</strong>, the row is skipped.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="ch-promo-gt-sold-prc-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:38%;">Dil Color</th>
                                    <th style="width:28%;">From Std Prc</th>
                                    <th style="width:20%;" class="text-end">% of Std</th>
                                    <th style="width:14%;" class="text-end">Rule</th>
                                </tr>
                            </thead>
                            <tbody id="ch-promo-gt-sold-prc-tbody"></tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2" id="ch-promo-gt-sold-prc-status"></div>
                </div>
                <div class="modal-footer py-2 flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="ch-promo-gt-sold-prc-save-btn"
                        title="Save Dil-color % and Increase/Decrease rules only">
                        Save Rule
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="ch-promo-gt-sold-prc-apply-btn"
                        title="Save rules and set S PRC = Std ± % for selected or visible >0 Sold rows">
                        Apply
                    </button>
                    <button type="button" class="btn btn-sm btn-warning" id="ch-promo-gt-sold-prc-push-btn"
                        title="Apply S PRC from Std ± %, then push price to the marketplace">
                        <i class="fas fa-upload me-1"></i> Push
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endif

@if($channelPromoPart === 'script' || $channelPromoPart === 'all')

        // ==================== Channel PEF Promo (channel_promo_pricing) ====================
        @include('partials.tabulator-column-autofit')
        @include('partials.analytics-column-visibility', ['colVisPart' => 'script'])

        const CHANNEL_PROMO_CHANNEL = @json($channelPromoChannel ?? 'ebay1');
        let chPromoPageReloadPushEnabled = @json($channelPromoPageReloadPushEnabled ?? true);
        const CHANNEL_PROMO_HIDE_CVR_CPN = @json($channelPromoHideCvrCpn);
        const CHANNEL_PROMO_SHOW_ZERO_SOLD_RULES = @json($channelPromoShowZeroSoldRules);
        const CHANNEL_PROMO_SHOW_GT_SOLD_RULES = @json($channelPromoShowGtSoldRules);
        const CHANNEL_PROMO_SHOW_ZERO_SOLD_DIL_RULE = @json($channelPromoShowZeroSoldDilRule);
        const CHANNEL_PROMO_ZERO_SOLD_DIL_COLOR_SLABS = @json($channelPromoZeroSoldDilColorSlabs);
        const CHANNEL_PROMO_CFG = {
            ebay1: {
                label: 'eBay',
                saveSpriceUrl: '/ebay-one/save-sprice',
                pushPriceUrl: '/push-ebay-price-tabulator',
                priceField: 'eBay Price',
                cvrField: 'SCVR',
                dilField: 'E Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                saveSpriceMode: 'sku',
            },
            ebay2: {
                label: 'eBay2',
                saveSpriceUrl: '/save-ebay2-sprice',
                pushPriceUrl: '/push-ebay2-price',
                priceField: 'eBay Price',
                cvrField: 'SCVR',
                dilField: 'E Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                saveSpriceMode: 'sku',
            },
            ebay2op: {
                label: 'eBay2 OP',
                saveSpriceUrl: '/save-ebay2-sprice',
                pushPriceUrl: '/push-ebay2-price',
                priceField: 'eBay Price',
                cvrField: 'SCVR',
                dilField: 'E Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                saveSpriceMode: 'sku',
            },
            ebay3: {
                label: 'eBay3',
                saveSpriceUrl: '/ebay3/save-sprice',
                pushPriceUrl: '/push-ebay3-price-tabulator',
                priceField: 'eBay Price',
                cvrField: 'SCVR',
                dilField: 'E Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                saveSpriceMode: 'sku',
            },
            shopify_b2c: {
                label: 'Shopify B2C',
                saveSpriceUrl: '/shopify/save-sprice',
                pushPriceUrl: '/push-shopify-b2c-price',
                priceField: 'Price',
                cvrField: 'CVR%',
                dilField: 'DIL%',
                invField: 'INV',
                skuField: '(Child) sku',
                soldField: 'B2B L30',
                soldFieldLabel: 'B2C L30',
                saveSpriceMode: 'sku',
            },
            shopify_b2b: {
                label: 'Shopify B2B',
                saveSpriceUrl: '/shopify-b2b/save-sprice',
                pushPriceUrl: '/cvr-master-push-price',
                priceField: 'Price',
                cvrField: 'CVR%',
                dilField: 'DIL%',
                invField: 'INV',
                skuField: '(Child) sku',
                soldField: 'B2B L30',
                soldFieldLabel: 'B2B L30',
                saveSpriceMode: 'sku',
            },
            macys: {
                label: 'Macys',
                saveSpriceUrl: '/macys-save-sprice-tabulator',
                saveSpriceBatchUrl: '/macys-save-sprice-batch',
                pushPriceUrl: '/macys-push-price',
                priceField: 'MC Price',
                cvrField: 'CVR%',
                dilField: 'MC Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                soldField: 'MC L30',
                saveSpriceMode: 'sku',
            },
            bestbuy: {
                label: 'Best Buy',
                saveSpriceUrl: '/bestbuy-save-sprice',
                pushPriceUrl: null,
                priceField: 'BB Price',
                cvrField: 'CVR%',
                dilField: 'BB Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                soldField: 'BB L30',
                saveSpriceMode: 'sku',
            },
            reverb: {
                label: 'Reverb',
                saveSpriceUrl: '/reverb-save-sprice',
                pushPriceUrl: null,
                priceField: 'RV Price',
                cvrField: 'CVR',
                dilField: 'RV Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                soldField: 'RV L30',
                soldFieldLabel: 'RV L30',
                saveSpriceMode: 'updates',
            },
            walmart: {
                label: 'Walmart',
                saveSpriceUrl: '/save-walmart-sprice',
                pushPriceUrl: '/cvr-master-push-price',
                priceField: 'price',
                cvrField: 'CVR_L30',
                dilField: 'E Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                soldField: 'W_L30',
                soldFieldLabel: 'W L30',
                saveSpriceMode: 'sku',
            },
            wayfair: {
                label: 'Wayfair',
                saveSpriceUrl: '/wayfair/pricing-save-sprice',
                pushPriceUrl: null,
                priceField: 'price',
                cvrField: 'cvr',
                dilField: 'dil_percent',
                invField: 'inv',
                skuField: 'sku',
                soldField: 'al30',
                saveSpriceMode: 'updates',
            },
            temu: {
                label: 'Temu',
                saveSpriceUrl: '/temu-pricing/save-sprice',
                pushPriceUrl: '/temu/push-price',
                priceField: 'temu_price',
                cvrField: 'cvr_30',
                dilField: 'dil_percent',
                invField: 'inventory',
                skuField: 'sku',
                soldField: 'temu_l30',
                soldFieldLabel: 'Temu L30',
                saveSpriceMode: 'sku',
            },
            temu2: {
                label: 'Temu 2',
                saveSpriceUrl: '/temu2-pricing/save-sprice',
                pushPriceUrl: '/temu2/push-price',
                priceField: 'temu_price',
                cvrField: 'cvr_percent',
                dilField: 'dil_percent',
                invField: 'inventory',
                skuField: 'sku',
                soldField: 'temu_l30',
                saveSpriceMode: 'sku',
            },
            doba: {
                label: 'Doba',
                saveSpriceUrl: '/doba/save-sprice',
                pushPriceUrl: '/doba/push-price',
                priceField: 'doba Price',
                cvrField: 'CVR%',
                dilField: 'Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                soldField: 'doba L30',
                saveSpriceMode: 'sku',
            },
            doba_withoutship: {
                label: 'Doba (no ship)',
                saveSpriceUrl: '/doba/save-sprice-withoutship',
                pushPriceUrl: '/doba/push-price',
                priceField: 'self_pick_price',
                cvrField: 'CVR%',
                dilField: 'Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                soldField: 'doba L30',
                saveSpriceMode: 'sku',
            },
            tiktok: {
                label: 'TikTok',
                saveSpriceUrl: '/tiktok-save-sprice',
                pushPriceUrl: '/cvr-master-push-price',
                priceField: 'TT Price',
                cvrField: 'CVR%',
                dilField: 'TT Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                soldField: 'TT L30',
                saveSpriceMode: 'sku',
            },
            tiktok2: {
                label: 'TikTok 2',
                saveSpriceUrl: '/tiktok-2-save-sprice',
                pushPriceUrl: '/cvr-master-push-price',
                priceField: 'TT Price',
                cvrField: 'CVR%',
                dilField: 'TT Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                soldField: 'TT L30',
                saveSpriceMode: 'sku',
            },
            topdawg: {
                label: 'TopDawg',
                saveSpriceUrl: '/topdawg-save-sprice',
                pushPriceUrl: '/cvr-master-push-price',
                priceField: 'TD Price',
                cvrField: 'CVR%',
                dilField: 'Dil',
                invField: 'INV',
                skuField: '(Child) sku',
                soldField: 'TD L30',
                saveSpriceMode: 'updates',
            },
            purchasing_power: {
                label: 'Purchasing Power',
                saveSpriceUrl: '/pp-save-sprice-tabulator',
                pushPriceUrl: '/cvr-master-push-price',
                priceField: 'PP Price',
                cvrField: 'CVR%',
                dilField: 'Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                soldField: 'PP L30',
                saveSpriceMode: 'sku',
            },
            aliexpress: {
                label: 'AliExpress',
                saveSpriceUrl: '/aliexpress/save-sprice',
                pushPriceUrl: '/cvr-master-push-price',
                priceField: 'price',
                cvrField: 'CVR%',
                dilField: 'dil_percent',
                invField: 'inv',
                skuField: 'sku',
                soldField: 'al30',
                soldFieldLabel: 'AL30',
                saveSpriceMode: 'updates',
            },
            shein: {
                label: 'Shein',
                saveSpriceUrl: '/shein/save-sprice',
                pushPriceUrl: '/cvr-master-push-price',
                priceField: 'special_offer',
                cvrField: 'CVR%',
                dilField: 'dil_percent',
                invField: 'inv',
                skuField: 'sku',
                soldField: 'al30',
                saveSpriceMode: 'updates',
            },
            newegg: {
                label: 'Newegg',
                saveSpriceUrl: '/newegg-pricing-save-sprice',
                pushPriceUrl: null,
                priceField: 'price',
                cvrField: 'CVR%',
                dilField: 'dil',
                invField: 'inv',
                skuField: 'sku',
                soldField: 'l30',
                saveSpriceMode: 'sku',
            },
            faire: {
                label: 'Faire',
                saveSpriceUrl: '/faire/pricing-save-sprice',
                pushPriceUrl: '/cvr-master-push-price',
                priceField: 'price',
                cvrField: 'CVR%',
                dilField: 'dil_percent',
                invField: 'inv',
                skuField: 'sku',
                soldField: 'al30',
                saveSpriceMode: 'updates',
            },
            pls: {
                label: 'PLS',
                saveSpriceUrl: '/save-pls-sprice',
                pushPriceUrl: '/cvr-master-push-price',
                priceField: 'price',
                cvrField: 'CVR%',
                dilField: 'dil_pct',
                invField: 'inventory',
                skuField: 'sku',
                soldField: 'pls_l30',
                saveSpriceMode: 'sku',
            },
            mercari_wship: {
                label: 'Mercari w Ship',
                saveSpriceUrl: '',
                pushPriceUrl: null,
                priceField: 'price',
                cvrField: 'CVR%',
                dilField: 'Dil%',
                invField: 'INV',
                skuField: 'sku',
                soldField: 'sold',
                saveSpriceMode: 'sku',
            },
            mercari_woship: {
                label: 'Mercari w/o Ship',
                saveSpriceUrl: '',
                pushPriceUrl: null,
                priceField: 'price',
                cvrField: 'CVR%',
                dilField: 'Dil%',
                invField: 'INV',
                skuField: 'sku',
                soldField: 'sold',
                saveSpriceMode: 'sku',
            },
            fb_marketplace: {
                label: 'FB Marketplace',
                saveSpriceUrl: '',
                pushPriceUrl: null,
                priceField: 'price',
                cvrField: 'CVR%',
                dilField: 'Dil%',
                invField: 'INV',
                skuField: 'sku',
                soldField: 'sold',
                saveSpriceMode: 'sku',
            },
            vinted: {
                label: 'Vinted',
                saveSpriceUrl: '/vinted/pricing/save-sprice-tabulator',
                pushPriceUrl: null,
                priceField: 'V Price',
                cvrField: 'CVR%',
                dilField: 'Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                soldField: 'V L30',
                saveSpriceMode: 'sku',
            },
            depop: {
                label: 'Depop',
                saveSpriceUrl: '/depop/pricing/save-sprice',
                pushPriceUrl: null,
                priceField: 'price',
                cvrField: 'CVR%',
                dilField: 'Dil%',
                invField: 'inv',
                skuField: 'sku',
                soldField: 'l30',
                saveSpriceMode: 'sku',
            },
        };
        CHANNEL_PROMO_CFG.macy = CHANNEL_PROMO_CFG.macys;
        const chPromoCfg = CHANNEL_PROMO_CFG[CHANNEL_PROMO_CHANNEL] || {};
        const CH_PROMO_SAVE_URL = '/channel-promo-pricing/save';
        const CH_PROMO_RULES_BASE = '/channel-promo-pricing/' + encodeURIComponent(CHANNEL_PROMO_CHANNEL);
        const CH_PROMO_EBAY1_COUPON_URL = '/pricing-errors-fix-ebay1-coupon';
        const CH_PROMO_EBAY1_PROMOTION_URL = '/pricing-errors-fix-ebay1-promotion';

        function chPromoPushPrcHasSaleCoupon() {
            return CHANNEL_PROMO_CHANNEL === 'ebay1';
        }
        /** eBay1 Prmt% / Push Prc must not create markdown sale events. */
        function chPromoEbay1CreateSaleEvents() {
            return false;
        }

        /** eBay1: create/add markdown sale event from PRMT%. Disabled — column-only. */
        function syncEbay1Promotion(sku, percent) {
            if (!chPromoEbay1CreateSaleEvents()) {
                return Promise.resolve({
                    ok: true,
                    skipped: true,
                    message: 'Sale event create is disabled',
                    promotion_id: null,
                    percent: percent != null ? Number(percent) : null,
                });
            }
            return new Promise(function(resolve) {
                $.ajax({
                    url: CH_PROMO_EBAY1_PROMOTION_URL,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                    data: { sku: sku, percent: percent, _token: chPromoCsrf() },
                }).done(function(res) {
                    resolve({
                        ok: !!(res && res.success),
                        message: (res && res.message) || '',
                        promotion_id: (res && res.promotion_id) || null,
                        percent: (res && res.percent != null) ? Number(res.percent) : null,
                    });
                }).fail(function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message)
                        || 'eBay1 sale event API error';
                    resolve({ ok: false, message: msg, promotion_id: null, percent: null });
                });
            });
        }

        function chPromoPrmtInt(d) {
            return Math.round(Math.max(0, Number(d && (d.prmt_pct != null ? d.prmt_pct : d._prmt_pct_applied)) || 0));
        }
        function chPromoLastSalePct(d) {
            return Math.round(Math.max(0, Number(d && d.PEF_SALE_PCT) || 0));
        }
        /** True when PRMT% should be synced to a 7-day sale (new, changed, remove, or last error). */
        function chPromoSaleNeedsPush(d) {
            const prmt = chPromoPrmtInt(d);
            if (prmt > 0 && (prmt < 5 || prmt > 80)) return false;
            if (String((d && d.PUSH_SALE_STATUS) || '') === 'error') return true;
            const last = chPromoLastSalePct(d);
            if (prmt === 0) return last >= 5;
            return last !== prmt;
        }
        async function pushChannelSaleEventOne(row, opts) {
            opts = opts || {};
            const silent = !!opts.silent;
            if (!chPromoEbay1CreateSaleEvents()) {
                if (!silent) chPromoToast('info', 'Sale event create is disabled — PRMT% column only');
                return { ok: true, skipped: true };
            }
            const d = row.getData() || {};
            const sku = chPromoSku(d);
            const prmt = chPromoPrmtInt(d);
            if (!sku || !chPromoIsChildRow(d)) {
                if (!silent) chPromoToast('error', 'SKU required');
                return { ok: false, skipped: true };
            }
            if (prmt > 0 && (prmt < 5 || prmt > 80)) {
                if (!silent) chPromoToast('error', 'PRMT% must be 5–80 (or 0 to remove from sales)');
                return { ok: false, skipped: true };
            }
            if (prmt === 0 && chPromoLastSalePct(d) < 5 && String(d.PUSH_SALE_STATUS || '') !== 'error') {
                if (!silent) chPromoToast('info', 'Set PRMT% first, then click Sale Event');
                return { ok: true, skipped: true };
            }
            row.update({ PUSH_SALE_STATUS: 'processing' });
            if (!silent) {
                clearTimeout(setChPromoPushPrcProgress._hideTimer);
                setChPromoPushPrcProgress({
                    active: true, done: 0, total: 1, ok: 0, fail: 0, pct: 20,
                    title: 'Pushing',
                    msg: sku + (prmt > 0 ? (' · ' + prmt + '% sale event') : ' · removing from sale'),
                });
            }
            const api = await syncEbay1Promotion(sku, prmt);
            if (api.ok) {
                const applied = prmt > 0 ? prmt : 0;
                row.update({
                    PUSH_SALE_STATUS: 'pushed',
                    PEF_SALE_PCT: applied,
                    PEF_PRMT_PROMOTION_ID: api.promotion_id || (applied ? d.PEF_PRMT_PROMOTION_ID : null),
                });
                if (!silent) {
                    setChPromoPushPrcProgress({
                        active: false, done: 1, total: 1, ok: 1, fail: 0, pct: 100,
                        title: 'Pushed',
                        msg: sku + (applied ? (' · ' + applied + '% sale') : ' · removed from sale'),
                    });
                    chPromoToast('success', api.message || (
                        applied
                            ? ('SKU on ' + applied + '% sale event (7 days)')
                            : 'SKU removed from sale events'
                    ));
                }
                return { ok: true };
            }
            row.update({ PUSH_SALE_STATUS: 'error' });
            if (!silent) {
                setChPromoPushPrcProgress({
                    active: false, done: 1, total: 1, ok: 0, fail: 1, pct: 100,
                    title: 'Push failed',
                    msg: sku + ' · ' + (api.message || 'sale event failed'),
                });
                chPromoToast('error', api.message || ('Sale Event failed for ' + sku));
            }
            return { ok: false };
        }

        function chPromoHeaderWithDelete(label, pushClass, delClass, pushTitle, delTitle) {
            return '<span class="ch-promo-header-with-del">'
                + '<button type="button" class="btn btn-sm p-0 ' + pushClass + '" title="' + chPromoEscAttr(pushTitle) + '" '
                + 'style="border:none;background:none;cursor:pointer;color:#000;font-weight:700;font-size:11px;line-height:1.15;padding:0;">'
                + chPromoEscAttr(label) + '</button>'
                + '<button type="button" class="ch-promo-col-del ' + delClass + '" title="' + chPromoEscAttr(delTitle) + '">'
                + '<i class="fa-solid fa-xmark"></i></button></span>';
        }
        function chPromoDeleteIconBtn(cls, sku, title) {
            return '<button type="button" class="ch-promo-col-del ' + cls + '" data-sku="'
                + chPromoEscAttr(sku) + '" title="' + chPromoEscAttr(title) + '">'
                + '<i class="fa-solid fa-xmark"></i></button>';
        }
        function chPromoCollectDeleteTargets(isEligible) {
            let targets = collectChPromoSelectedRows();
            let scope = 'selected';
            if (!targets.length) {
                targets = collectChPromoVisibleRows();
                scope = 'visible';
            }
            return {
                scope: scope,
                rows: targets.filter(function(t) { return chPromoIsChildRow(t.d) && isEligible(t.d); }),
            };
        }

        async function deleteChannelSaleEventOne(row, opts) {
            opts = opts || {};
            const d = row.getData() || {};
            const sku = chPromoSku(d);
            if (!sku) return { ok: false };
            row.update({ PUSH_SALE_STATUS: 'processing' });
            const api = await syncEbay1Promotion(sku, 0);
            if (api.ok) {
                row.update({
                    PUSH_SALE_STATUS: null,
                    PEF_SALE_PCT: 0,
                    PEF_PRMT_PROMOTION_ID: null,
                    sale_event: null,
                });
                try { row.reformat(); } catch (e) { /* ignore */ }
                if (!opts.silent) chPromoToast('success', api.message || ('Removed ' + sku + ' from sale events'));
                return { ok: true };
            }
            row.update({ PUSH_SALE_STATUS: 'error' });
            if (!opts.silent) chPromoToast('error', api.message || ('Could not remove ' + sku + ' from sale'));
            return { ok: false };
        }
        async function bulkDeleteChannelSaleEvent() {
            const pack = chPromoCollectDeleteTargets(function(d) {
                return chPromoLastSalePct(d) >= 5 || String(d.PUSH_SALE_STATUS || '') === 'pushed';
            });
            if (!pack.rows.length) {
                chPromoToast('info', 'No SKUs on a sale event to remove');
                return;
            }
            if (!confirm('Remove ' + pack.rows.length + ' ' + pack.scope + ' SKU(s) from eBay sale events?\n\nPRMT% stays. This calls the Sale Event remove function.')) return;
            let ok = 0;
            let fail = 0;
            for (let i = 0; i < pack.rows.length; i++) {
                const r = await deleteChannelSaleEventOne(pack.rows[i].row, { silent: true });
                if (r.ok) ok++; else fail++;
            }
            chPromoToast(fail && !ok ? 'error' : 'success', 'Sale Event delete: ' + ok + ' removed' + (fail ? (', ' + fail + ' failed') : ''));
        }

        async function deleteChannelCpnOne(row, opts) {
            opts = opts || {};
            const d = row.getData() || {};
            const sku = chPromoSku(d);
            if (!sku) return { ok: false };
            row.update({ PUSH_CPN_STATUS: 'processing' });
            const api = await syncEbay1CodedCoupon(sku, 0);
            if (api.ok) {
                row.update({
                    PUSH_CPN_STATUS: null,
                    PEF_COUPON_PCT: 0,
                    PEF_COUPON_CODE: null,
                    coupon_code: null,
                    PEF_COUPON_PROMOTION_ID: null,
                    push_cpn: null,
                });
                try { row.reformat(); } catch (e) { /* ignore */ }
                if (!opts.silent) chPromoToast('success', api.message || ('Removed ' + sku + ' from coupons'));
                return { ok: true };
            }
            row.update({ PUSH_CPN_STATUS: 'error' });
            if (!opts.silent) chPromoToast('error', api.message || ('Could not remove ' + sku + ' from coupon'));
            return { ok: false };
        }
        async function bulkDeleteChannelCpn() {
            const pack = chPromoCollectDeleteTargets(function(d) {
                return chPromoLastCouponPct(d) >= 5 || String(d.PUSH_CPN_STATUS || '') === 'pushed';
            });
            if (!pack.rows.length) {
                chPromoToast('info', 'No SKUs on a coupon to remove');
                return;
            }
            if (!confirm('Remove ' + pack.rows.length + ' ' + pack.scope + ' SKU(s) from eBay coupons?\n\nCPN% stays. This calls the Push CPN remove function.')) return;
            let ok = 0;
            let fail = 0;
            for (let i = 0; i < pack.rows.length; i++) {
                const r = await deleteChannelCpnOne(pack.rows[i].row, { silent: true });
                if (r.ok) ok++; else fail++;
            }
            chPromoToast(fail && !ok ? 'error' : 'success', 'Push CPN delete: ' + ok + ' removed' + (fail ? (', ' + fail + ' failed') : ''));
        }

        function deleteChannelStdPrcStatusOne(row, opts) {
            opts = opts || {};
            const d = row.getData() || {};
            const sku = chPromoSku(d);
            if (!sku) return Promise.resolve({ ok: false });
            return saveChannelPromoFields(sku, {
                push_std_prc_status: '',
                push_std_prc_value: 0,
                push_std_prc_pushed_at: '',
            }).then(function() {
                row.update({
                    PUSH_STD_PRC_STATUS: null,
                    PUSH_STD_PRC_VALUE: null,
                    push_std_prc: null,
                });
                try { row.reformat(); } catch (e) { /* ignore */ }
                if (!opts.silent) chPromoToast('success', 'Cleared Push Std Prc for ' + sku);
                return { ok: true };
            }).fail(function(xhr) {
                if (!opts.silent) {
                    chPromoToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Could not clear Push Std Prc');
                }
                return { ok: false };
            });
        }
        async function bulkDeleteChannelStdPrcStatus() {
            const pack = chPromoCollectDeleteTargets(function(d) {
                return String(d.PUSH_STD_PRC_STATUS || '') === 'pushed' || chPromoStdPrcLastPushed(d) > 0;
            });
            if (!pack.rows.length) {
                chPromoToast('info', 'No Push Std Prc checkmarks to clear');
                return;
            }
            if (!confirm('Clear Push Std Prc status for ' + pack.rows.length + ' ' + pack.scope + ' SKU(s)?\n\nDoes not change eBay price. Green checks become the upload icon again.')) return;
            let ok = 0;
            let fail = 0;
            for (let i = 0; i < pack.rows.length; i++) {
                const r = await deleteChannelStdPrcStatusOne(pack.rows[i].row, { silent: true });
                if (r && r.ok) ok++; else fail++;
            }
            chPromoToast(fail && !ok ? 'error' : 'success', 'Push Std Prc delete: ' + ok + ' cleared' + (fail ? (', ' + fail + ' failed') : ''));
        }
        function hideChannelPushCpnColumn() {
            if (typeof table === 'undefined' || !table) return;
            if (!confirm('Hide the Push CPN column?')) return;
            try { table.hideColumn('push_cpn'); } catch (e) { /* ignore */ }
            if (typeof saveColumnVisibilityToServer === 'function') saveColumnVisibilityToServer();
            chPromoToast('success', 'Push CPN column hidden');
        }
        function hideChannelPushStdPrcColumn() {
            if (typeof table === 'undefined' || !table) return;
            if (!confirm('Hide the Push Std Prc column?')) return;
            try { table.hideColumn('push_std_prc'); } catch (e) { /* ignore */ }
            if (typeof saveColumnVisibilityToServer === 'function') saveColumnVisibilityToServer();
            if (typeof chPromoToast === 'function') chPromoToast('success', 'Push Std Prc column hidden');
        }

        let chPromoSaleEventBusy = false;
        async function bulkPushChannelSaleEvent() {
            if (!chPromoEbay1CreateSaleEvents() || CHANNEL_PROMO_CHANNEL !== 'ebay1') {
                chPromoToast('info', 'Sale event create is disabled — change Dil vs PRMT numbers to fill the PRMT% column');
                return;
            }
            if (chPromoSaleEventBusy) {
                chPromoToast('info', 'Sale Event already running');
                return;
            }
            let targets = collectChPromoSelectedRows();
            let scopeLabel = 'selected';
            if (!targets.length) {
                targets = collectChPromoVisibleRows();
                scopeLabel = 'visible';
            }
            const eligible = targets.filter(function(t) { return chPromoSaleNeedsPush(t.d); });
            const skipped = targets.length - eligible.length;
            if (!eligible.length) {
                chPromoToast('info', skipped
                    ? ('No sale-event changes (' + skipped + ' already on matching % or PRMT% not 5–80)')
                    : 'No SKUs for Sale Event');
                return;
            }
            if (!confirm(
                'Push Sale Event for ' + eligible.length + ' ' + scopeLabel + ' SKU(s)?\n\n'
                + 'Creates a 7-day sale at that PRMT% if needed, then adds the SKU.\n'
                + 'If PRMT% changed, the SKU is removed from the old % sale first.\n'
                + (skipped ? skipped + ' already on the matching sale will be skipped.' : '')
            )) return;
            chPromoSaleEventBusy = true;
            let ok = 0, fail = 0, done = 0;
            clearTimeout(setChPromoPushPrcProgress._hideTimer);
            setChPromoPushPrcProgress({
                active: true, done: 0, total: eligible.length, ok: 0, fail: 0, pct: 5,
                title: 'Pushing',
                msg: 'Starting ' + eligible.length + ' sale event(s)…',
            });
            try {
                await chPromoMapLimit(eligible, 3, async function(item) {
                    const sku = chPromoSku(item.d);
                    const res = await pushChannelSaleEventOne(item.row, { silent: true });
                    if (res && res.ok && !res.skipped) ok++;
                    else if (!(res && res.skipped)) fail++;
                    done++;
                    setChPromoPushPrcProgress({
                        active: true, done: done, total: eligible.length, ok: ok, fail: fail,
                        title: 'Pushing',
                        msg: sku + ' · sale event',
                    });
                });
            } finally {
                chPromoSaleEventBusy = false;
                setChPromoPushPrcProgress({
                    active: false, done: eligible.length, total: eligible.length, ok: ok, fail: fail, pct: 100,
                    title: fail && !ok ? 'Push failed' : 'Pushed',
                    msg: ok + ' ok' + (fail ? (' · ' + fail + ' failed') : ''),
                });
            }
            chPromoToast(
                fail && !ok ? 'error' : 'success',
                'Sale Event: ' + ok + ' ok' + (fail ? (' / ' + fail + ' fail') : '')
                + (skipped ? ('; skipped ' + skipped) : '')
            );
            if (typeof table !== 'undefined' && table) table.redraw(true);
        }

        function chPromoPushPrmtCollectEligible() {
            const seen = new Set();
            const out = [];
            function addListing(parentD) {
                const children = chPromoListingChildren(parentD);
                const prmt = chPromoParentPrmt(parentD, children);
                const seed = children.find(function(t) {
                    return String((t.d && (t.d.eBay_item_id || t.d.item_id)) || '').trim();
                }) || children[0];
                if (!seed) return;
                const k = chPromoSkuKey(chPromoSku(seed.d));
                if (!k || seen.has(k)) return;
                seen.add(k);
                out.push({ row: seed.row, d: seed.d, prmt: prmt });
            }
            const selectedParents = collectChPromoSelectedParentRows();
            if (selectedParents.length) {
                selectedParents.forEach(function(p) { addListing(p.d); });
                return out;
            }
            const selectedKids = collectChPromoSelectedRows();
            if (selectedKids.length) {
                selectedKids.forEach(function(t) { addListing(t.d); });
                return out;
            }
            let hadParent = false;
            chPromoEachTableRow(function(row, d) {
                if (chPromoIsParentRow(d)) {
                    hadParent = true;
                    addListing(d);
                }
            });
            if (hadParent) return out;
            chPromoEachTableRow(function(row, d) {
                if (chPromoIsChildRow(d)) addListing(d);
            });
            return out;
        }
        /** Same live spinner as Push Prc / Push Std Prc (Tabulator does not always redraw from status-only fields). */
        function chPromoPaintPushSpinner(btn, title) {
            if (!btn) return;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:14px;"></i>';
            btn.style.color = '#ffc107';
            if (title) btn.title = title;
        }
        function chPromoRefreshPushCell(row, field, btnSelector, statusKey, title) {
            if (!row) return;
            try {
                const cell = row.getCell && row.getCell(field);
                if (cell && typeof cell.reformat === 'function') cell.reformat();
            } catch (e) { /* ignore */ }
            try {
                const d = row.getData() || {};
                if (String(d[statusKey] || '') !== 'processing') return;
                const el = (row.getElement && row.getElement()) || null;
                const btn = el && el.querySelector && el.querySelector(btnSelector);
                chPromoPaintPushSpinner(btn, title);
            } catch (e) { /* ignore */ }
        }
        /** Walk every table row (all pages + tree children). table.getRows() is current page only. */
        function chPromoEachTableRow(fn) {
            if (typeof table === 'undefined' || !table || typeof fn !== 'function') return;
            const seen = new Set();
            function walk(row) {
                if (!row || seen.has(row)) return;
                seen.add(row);
                try { fn(row, row.getData() || {}); } catch (e) { /* ignore */ }
                if (typeof row.getTreeChildren === 'function') {
                    (row.getTreeChildren() || []).forEach(walk);
                }
            }
            let rows = [];
            try { rows = table.getRows() || []; } catch (e) { rows = []; }
            if (!rows.length) {
                try { rows = table.getRows('active') || []; } catch (e) { rows = []; }
            }
            rows.forEach(walk);
        }
        function applyChannelPushPrmtTaskStatusesToTable(tasks) {
            if (typeof table === 'undefined' || !table || !Array.isArray(tasks)) return;
            chPromoPushPrmtLastTasks = tasks;
            const bySku = {};
            tasks.forEach(function(t) {
                if (t && t.sku) bySku[chPromoSkuKey(t.sku)] = t;
            });
            chPromoEachTableRow(function(row, d) {
                if (!chPromoIsChildRow(d)) return;
                const t = bySku[chPromoSkuKey(chPromoSku(d))];
                if (!t) return;
                const st = String(t.status || '');
                if (st === 'ok') {
                    const pct = t.prmt != null ? Math.round(Number(t.prmt) || 0) : chPromoPrmtInt(d);
                    const patch = {
                        PUSH_SALE_STATUS: 'pushed',
                        PEF_SALE_PCT: pct > 0 ? pct : 0,
                        PEF_PRMT_PROMOTION_ID: t.promotion_id || d.PEF_PRMT_PROMOTION_ID || null,
                        push_prmt: 'pushed',
                    };
                    row.update(patch);
                    const parent = chPromoParentName(d).toUpperCase();
                    const itemId = String(d.eBay_item_id || d.item_id || '').trim();
                    chPromoEachTableRow(function(r2, d2) {
                        if (r2 === row) return;
                        const sameItem = itemId && String(d2.eBay_item_id || d2.item_id || '').trim() === itemId;
                        const sameParent = parent && chPromoParentName(d2).toUpperCase() === parent;
                        if (!sameItem && !sameParent) return;
                        r2.update(patch);
                    });
                } else if (st === 'failed') {
                    row.update({ PUSH_SALE_STATUS: 'error', push_prmt: 'error' });
                } else if (st === 'pushing' || st === 'pending' || st === 'queued') {
                    row.update({ PUSH_SALE_STATUS: 'processing', push_prmt: 'processing' });
                }
            });
            try { table.redraw(true); } catch (e) { /* ignore */ }
            chPromoEachTableRow(function(row, d) {
                if (String(d.PUSH_SALE_STATUS || '') === 'processing') {
                    chPromoRefreshPushCell(row, 'push_prmt', '.ch-promo-push-prmt-btn', 'PUSH_SALE_STATUS', 'Pushing PRMT%…');
                }
            });
            chPromoSyncParentPushButtons('prmt');
        }
        function stopChannelPushPrmtPoll() {
            if (chPromoPushPrmtPollTimer) {
                clearInterval(chPromoPushPrmtPollTimer);
                chPromoPushPrmtPollTimer = null;
            }
        }
        function pollChannelPushPrmtStatus() {
            if (!chPromoPushPrmtQueueEnabled) return;
            $.ajax({
                url: CH_PROMO_PUSH_PRMT_QUEUE_URL + '/status',
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                timeout: 20000,
            }).done(function(resp) {
                if (!resp) return;
                const active = !!resp.active;
                const applied = chPromoApplyQueuedPushStatus('prmt', chPromoPushPrmtWatching, resp, 'Pushing PRMT%');
                chPromoPushPrmtWatching = applied.watching;
                applyChannelPushPrmtTaskStatusesToTable(resp.tasks || []);
                if (!active) {
                    stopChannelPushPrmtPoll();
                    const toastKey = applied.jobStatus + '|' + applied.ok + '|' + applied.fail + '|' + applied.total;
                    if (applied.shouldToast && toastKey !== chPromoPushPrmtLastToastKey) {
                        chPromoPushPrmtLastToastKey = toastKey;
                        chPromoToast(
                            applied.fail && !applied.ok ? 'error' : 'success',
                            resp.message || ('Push PRMT%: ' + applied.ok + ' ok' + (applied.fail ? (', ' + applied.fail + ' failed') : ''))
                        );
                    }
                }
            });
        }
        function startChannelPushPrmtPoll() {
            stopChannelPushPrmtPoll();
            chPromoPushPrmtPollTimer = setInterval(pollChannelPushPrmtStatus, 1000);
            pollChannelPushPrmtStatus();
        }
        function cancelChannelPushPrmtJob() {
            if (!confirm('Cancel remaining Push PRMT% jobs?')) return;
            $.ajax({
                url: CH_PROMO_PUSH_PRMT_QUEUE_URL + '/cancel',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: { _token: chPromoCsrf() },
            }).done(function(resp) {
                chPromoToast('success', (resp && resp.message) || 'Push PRMT% cancelled');
                pollChannelPushPrmtStatus();
            }).fail(function(xhr) {
                chPromoToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Cancel failed');
            });
        }
        /** Queue SKUs in chunks of 25; worker processes 10 at a time. */
        async function queueChannelPushPrmtItems(items) {
            if (!items || !items.length) {
                chPromoToast('error', 'Nothing to queue');
                return;
            }
            chPromoPushPrmtWatching = true;
            clearTimeout(setChPromoPushPrcProgress._hideTimer);
            setChPromoPushPrcProgress({
                active: true, done: 0, total: items.length, ok: 0, fail: 0, pct: 5,
                cancelable: true, title: 'Pushing PRMT%',
                msg: 'Queuing ' + items.length + ' SKU(s) in chunks of ' + CH_PROMO_PUSH_PRMT_CHUNK + '…',
            });
            const chunks = [];
            for (let i = 0; i < items.length; i += CH_PROMO_PUSH_PRMT_CHUNK) {
                chunks.push(items.slice(i, i + CH_PROMO_PUSH_PRMT_CHUNK));
            }
            try {
                for (let c = 0; c < chunks.length; c++) {
                    await $.ajax({
                        url: CH_PROMO_PUSH_PRMT_QUEUE_URL,
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                        data: { _token: chPromoCsrf(), items: chunks[c] },
                        timeout: 60000,
                    });
                    setChPromoPushPrcProgress({
                        active: true, done: 0, total: items.length, pct: Math.min(15, Math.round(((c + 1) / chunks.length) * 12)),
                        cancelable: true, title: 'Pushing PRMT%',
                        msg: 'Queued chunk ' + (c + 1) + '/' + chunks.length,
                    });
                }
                chPromoToast('success', 'Queued ' + items.length + ' Push PRMT% job(s) in ' + chunks.length + ' chunk(s)');
                startChannelPushPrmtPoll();
            } catch (xhr) {
                const msg = (xhr && xhr.responseJSON && xhr.responseJSON.message) || 'Could not queue Push PRMT%';
                chPromoToast('error', msg);
                setChPromoPushPrcProgress({ active: false, done: 0, total: 0, msg: msg });
                // Job may already be running/finished — keep polling so spinners clear.
                startChannelPushPrmtPoll();
            }
        }
        function queueChannelPushPrmtRows(rows) {
            const items = [];
            rows.forEach(function(item) {
                const d = item.d || item.row.getData();
                if (chPromoIsParentRow(d)) return;
                const sku = chPromoSku(d);
                const prmt = item.prmt != null ? item.prmt : chPromoPrmtInt(d);
                if (!sku) return;
                item.row.update({ PUSH_SALE_STATUS: 'processing', push_prmt: 'processing' });
                chPromoRefreshPushCell(item.row, 'push_prmt', '.ch-promo-push-prmt-btn', 'PUSH_SALE_STATUS', 'Pushing PRMT%…');
                items.push({ sku: sku, prmt: prmt });
            });
            if (typeof table !== 'undefined' && table) {
                try { table.redraw(true); } catch (e) { /* ignore */ }
            }
            return queueChannelPushPrmtItems(items);
        }
        function chPromoFindParentRowForChild(d) {
            const parent = chPromoParentName(d).toUpperCase();
            const itemId = String((d && (d.eBay_item_id || d.item_id || d.ebay_item_id)) || '').trim();
            let found = null;
            chPromoEachTableRow(function(row, pd) {
                if (found || !chPromoIsParentRow(pd)) return;
                const sameItem = itemId && String(pd.eBay_item_id || pd.item_id || pd.ebay_item_id || '').trim() === itemId;
                const sameParent = parent && chPromoParentName(pd).toUpperCase() === parent;
                if (sameItem || sameParent) found = row;
            });
            return found;
        }
        function pushChannelPrmtFromChild(row) {
            const d = row.getData() || {};
            const parentRow = chPromoFindParentRowForChild(d);
            if (parentRow) {
                pushChannelPrmtFromParent(parentRow);
                return;
            }
            const sku = chPromoSku(d);
            const prmt = chPromoPrmtInt(d);
            if (!sku) {
                chPromoToast('error', 'SKU required');
                return;
            }
            if (prmt > 0 && (prmt < 5 || prmt > 80)) {
                chPromoToast('error', 'PRMT% must be 5–80 (or 0 to remove from sales)');
                return;
            }
            if (prmt === 0 && chPromoLastSalePct(d) < 5 && String(d.PUSH_SALE_STATUS || '') !== 'error') {
                chPromoToast('info', 'Set PRMT% first, then Push PRmt %');
                return;
            }
            if (!confirm(
                'Queue Push PRMT% for ' + sku + '?\n\n'
                + (prmt > 0
                    ? ('7-day ' + prmt + '% markdown for this listing.')
                    : 'Remove this listing from sale events.')
            )) {
                return;
            }
            queueChannelPushPrmtRows([{ row: row, d: d, prmt: prmt }]);
        }
        function pushChannelPrmtQueued(row) {
            const d = row.getData() || {};
            if (chPromoIsParentRow(d)) {
                pushChannelPrmtFromParent(row);
                return;
            }
            pushChannelPrmtFromChild(row);
        }
        function pushChannelPrmtFromParent(row) {
            const d = row.getData() || {};
            const children = chPromoListingChildren(d);
            const parentName = chPromoParentName(d) || chPromoSku(d) || 'this listing';
            if (!children.length) {
                chPromoToast('error', 'No child SKUs on ' + parentName);
                return;
            }
            const prmt = chPromoParentPrmt(d, children);
            if (prmt > 0 && (prmt < 5 || prmt > 80)) {
                chPromoToast('error', 'PRMT% must be 5–80 (or 0 to remove from sales)');
                return;
            }
            if (prmt === 0 && !children.some(function(t) { return chPromoLastSalePct(t.d) >= 5 || chPromoLastSalePct(d) >= 5; })
                && String(d.PUSH_SALE_STATUS || '') !== 'error') {
                chPromoToast('info', 'Set parent PRMT% first, then Push PRmt %');
                return;
            }
            const seed = children.find(function(t) {
                return String((t.d && (t.d.eBay_item_id || t.d.item_id)) || '').trim();
            }) || children[0];
            const seedSku = chPromoSku(seed.d);
            if (!seedSku) {
                chPromoToast('error', 'No child SKU with an eBay listing id on ' + parentName);
                return;
            }
            if (!confirm(
                'Queue Push PRMT% for listing ' + parentName + '?\n\n'
                + (prmt > 0
                    ? ('7-day ' + prmt + '% markdown for all ' + children.length + ' variation(s).')
                    : 'Remove this listing from sale events.')
                + '\nOne eBay job — the sale applies to every child on the item id.'
            )) {
                return;
            }
            row.update({ PUSH_SALE_STATUS: 'processing', push_prmt: 'processing' });
            chPromoRefreshPushCell(row, 'push_prmt', '.ch-promo-push-prmt-btn', 'PUSH_SALE_STATUS', 'Pushing PRMT%…');
            seed.row.update({ PUSH_SALE_STATUS: 'processing', push_prmt: 'processing' });
            queueChannelPushPrmtItems([{ sku: seedSku, prmt: prmt }]);
        }
        function bulkPushChannelPrmtQueued() {
            if (!chPromoPushPrmtQueueEnabled) {
                chPromoToast('error', 'Push PRMT% queue is eBay 2 / eBay 3 only');
                return;
            }
            const eligible = chPromoPushPrmtCollectEligible();
            if (!eligible.length) {
                chPromoToast('info', 'No SKUs with PRMT% changes to push');
                return;
            }
            if (!confirm(
                'Queue Push PRMT% for ' + eligible.length + ' SKU(s)?\n\n'
                + 'Same as eBay 1 Sale Event: 7-day markdown at PRMT%.\n'
                + 'Queued in chunks of ' + CH_PROMO_PUSH_PRMT_CHUNK + '; worker processes 10 at a time.\n'
                + 'Safe to refresh — progress continues.'
            )) {
                eligible.forEach(function(t) {
                    chPromoRefreshPushCell(t.row, 'push_prmt', '.ch-promo-push-prmt-btn', 'PUSH_SALE_STATUS', 'Pushing PRMT%…');
                });
                return;
            }
            queueChannelPushPrmtRows(eligible);
        }

        function channelPromoPushPrmtColumn() {
            return {
                title: 'Push PRmt %',
                field: 'push_prmt',
                width: 72,
                hozAlign: 'center',
                vertAlign: 'middle',
                headerSort: false,
                headerTooltip: 'Push PRMT% — 7-day markdown for the listing. Click the icon on a SKU or parent. Click header to bulk selected (or visible) rows.',
                titleFormatter: function() {
                    return '<button type="button" class="btn btn-sm p-0 ch-promo-push-prmt-header-btn" '
                        + 'title="Bulk queue Push PRMT% for selected (or visible) listings" '
                        + 'style="border:none;background:none;cursor:pointer;color:#000;'
                        + 'font-weight:700;font-size:11px;line-height:1.15;padding:0;">'
                        + 'Push PRmt %</button>';
                },
                headerClick: function(e) {
                    if (e.target.closest('.ch-promo-push-prmt-header-btn')) {
                        e.stopPropagation();
                        e.preventDefault();
                        bulkPushChannelPrmtQueued();
                        return false;
                    }
                },
                formatter: function(cell) {
                    const d = cell.getRow().getData() || {};
                    const isParent = chPromoIsParentRow(d);
                    if (!isParent && !chPromoIsChildRow(d)) return '';
                    const children = isParent ? chPromoListingChildren(d) : [];
                    const sku = chPromoSku(d);
                    const prmt = isParent ? chPromoParentPrmt(d, children) : chPromoPrmtInt(d);
                    const last = isParent && children.length
                        ? Math.max.apply(null, children.map(function(t) { return chPromoLastSalePct(t.d); }).concat([chPromoLastSalePct(d)]))
                        : chPromoLastSalePct(d);
                    const status = String(d.PUSH_SALE_STATUS || '')
                        || (isParent ? chPromoParentPushStatus(children, 'PUSH_SALE_STATUS') : '');
                    const needs = isParent
                        ? (children.some(function(t) { return chPromoSaleNeedsPush(t.d); })
                            || (children.length === 0 && chPromoSaleNeedsPush(d)))
                        : chPromoSaleNeedsPush(d);
                    if (prmt > 0 && (prmt < 5 || prmt > 80)) {
                        return '<span style="color:#adb5bd;" title="eBay sale % must be 5–80">—</span>';
                    }
                    if (prmt === 0 && last < 5 && status !== 'error') {
                        return '<span style="color:#adb5bd;" title="Set PRMT% then click to queue a 7-day sale">—</span>';
                    }
                    let icon = '<i class="fas fa-upload"></i>';
                    let color = '#FF9900';
                    let tip = prmt > 0
                        ? (isParent
                            ? ('Queue 7-day ' + prmt + '% sale for this listing (' + children.length + ' SKU)')
                            : ('Queue 7-day ' + prmt + '% sale for this listing'))
                        : 'Queue remove this listing from sale events';
                    if (status === 'processing') {
                        icon = '<i class="fas fa-spinner fa-spin" style="font-size:14px;"></i>';
                        color = '#ffc107';
                        tip = 'Pushing PRMT%…';
                    } else if (status === 'error') {
                        icon = '<i class="fa-solid fa-xmark"></i>';
                        color = '#dc3545';
                        tip = 'Last Push PRMT% failed — click to retry';
                    } else if (!needs) {
                        icon = '<i class="fa-solid fa-check-double"></i>';
                        color = '#28a745';
                        tip = 'Listing on ' + last + '% sale event — click to queue again';
                    } else if (last >= 5 && prmt > 0 && last !== prmt) {
                        tip = 'PRMT% changed ' + last + '% → ' + prmt + '% — click to queue listing';
                    } else if (prmt === 0 && last >= 5) {
                        icon = '<i class="fa-solid fa-xmark"></i>';
                        color = '#dc3545';
                        tip = 'PRMT% is 0 — click to queue remove from ' + last + '% sale';
                    }
                    return '<button type="button" class="btn btn-sm p-0 ch-promo-push-prmt-btn" '
                        + 'data-sku="' + chPromoEscAttr(sku) + '" '
                        + 'title="' + chPromoEscAttr(tip) + '" '
                        + 'style="border:none;background:none;cursor:pointer;color:' + color
                        + ';padding:0;line-height:1;vertical-align:middle;">'
                        + icon + '</button>';
                },
                cellClick: function(e, cell) {
                    const btn = e.target.closest('.ch-promo-push-prmt-btn');
                    if (!btn) return;
                    e.stopPropagation();
                    e.preventDefault();
                    const d = cell.getRow().getData() || {};
                    if (String(d.PUSH_SALE_STATUS || '') === 'processing') return false;
                    const selectedParents = collectChPromoSelectedParentRows();
                    const selectedKids = collectChPromoSelectedRows();
                    const clickedKey = chPromoSkuKey(chPromoSku(d));
                    if (chPromoIsParentRow(d)
                        && selectedParents.length > 1
                        && selectedParents.some(function(t) {
                            return chPromoSkuKey(chPromoSku(t.d)) === clickedKey;
                        })) {
                        chPromoPaintPushSpinner(btn, 'Pushing PRMT%…');
                        bulkPushChannelPrmtQueued();
                        return false;
                    }
                    if (chPromoIsChildRow(d)
                        && selectedKids.length > 1
                        && selectedKids.some(function(t) {
                            return chPromoSkuKey(chPromoSku(t.d)) === clickedKey;
                        })) {
                        chPromoPaintPushSpinner(btn, 'Pushing PRMT%…');
                        bulkPushChannelPrmtQueued();
                        return false;
                    }
                    chPromoPaintPushSpinner(btn, 'Pushing PRMT%…');
                    pushChannelPrmtQueued(cell.getRow());
                    return false;
                },
            };
        }

        function chPromoPushCpnCollectEligible() {
            const seen = new Set();
            const out = [];
            function addChildren(parentD) {
                chPromoListingChildren(parentD).forEach(function(t) {
                    const k = chPromoSkuKey(chPromoSku(t.d));
                    if (!k || seen.has(k) || !chPromoCpnNeedsPush(t.d)) return;
                    seen.add(k);
                    out.push(t);
                });
            }
            const selectedParents = collectChPromoSelectedParentRows();
            if (selectedParents.length) {
                selectedParents.forEach(function(p) { addChildren(p.d); });
                return out;
            }
            const selectedKids = collectChPromoSelectedRows();
            if (selectedKids.length) {
                selectedKids.forEach(function(t) { addChildren(t.d); });
                return out;
            }
            let hadParent = false;
            chPromoEachTableRow(function(row, d) {
                if (chPromoIsParentRow(d)) {
                    hadParent = true;
                    addChildren(d);
                }
            });
            if (hadParent) return out;
            chPromoEachTableRow(function(row, d) {
                if (chPromoIsChildRow(d)) addChildren(d);
            });
            return out;
        }
        function applyChannelPushCpnTaskStatusesToTable(tasks) {
            if (typeof table === 'undefined' || !table || !Array.isArray(tasks)) return;
            const bySku = {};
            tasks.forEach(function(t) {
                if (t && t.sku) bySku[String(t.sku).toUpperCase()] = t;
            });
            chPromoEachTableRow(function(row, d) {
                if (!chPromoIsChildRow(d)) return;
                const sku = chPromoSku(d).toUpperCase();
                const t = bySku[sku];
                if (!t) return;
                const st = String(t.status || '');
                if (st === 'ok') {
                    const pct = t.cpn != null ? Math.round(Number(t.cpn) || 0) : chPromoCpnInt(d);
                    const code = t.coupon_code || (pct > 0 ? ('SAVE' + String(pct).padStart(2, '0') + 'PCT') : null);
                    row.update({
                        PUSH_CPN_STATUS: 'pushed',
                        PEF_COUPON_PCT: pct > 0 ? pct : 0,
                        PEF_COUPON_CODE: pct > 0 ? code : null,
                        coupon_code: pct > 0 ? code : null,
                        push_cpn: 'pushed',
                    });
                } else if (st === 'failed') {
                    row.update({ PUSH_CPN_STATUS: 'error', push_cpn: 'error' });
                } else if (st === 'pushing' || st === 'pending' || st === 'queued') {
                    row.update({ PUSH_CPN_STATUS: 'processing', push_cpn: 'processing' });
                }
                chPromoRefreshPushCell(
                    row,
                    'push_cpn',
                    '.ch-promo-push-cpn-queue-btn, .ch-promo-push-cpn-col-btn',
                    'PUSH_CPN_STATUS',
                    'Pushing CPN%…'
                );
            });
            try { table.redraw(true); } catch (e) { /* ignore */ }
            chPromoSyncParentPushButtons('cpn');
        }
        function stopChannelPushCpnPoll() {
            if (chPromoPushCpnPollTimer) {
                clearInterval(chPromoPushCpnPollTimer);
                chPromoPushCpnPollTimer = null;
            }
        }
        function pollChannelPushCpnStatus() {
            if (!chPromoPushCpnQueueEnabled) return;
            $.ajax({
                url: CH_PROMO_PUSH_CPN_QUEUE_URL + '/status',
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                timeout: 20000,
            }).done(function(resp) {
                if (!resp) return;
                const active = !!resp.active;
                const applied = chPromoApplyQueuedPushStatus('cpn', chPromoPushCpnWatching, resp, 'Pushing CPN%');
                chPromoPushCpnWatching = applied.watching;
                applyChannelPushCpnTaskStatusesToTable(resp.tasks || []);
                if (!active) {
                    stopChannelPushCpnPoll();
                    const toastKey = applied.jobStatus + '|' + applied.ok + '|' + applied.fail + '|' + applied.total;
                    if (applied.shouldToast && toastKey !== chPromoPushCpnLastToastKey) {
                        chPromoPushCpnLastToastKey = toastKey;
                        chPromoToast(
                            applied.fail && !applied.ok ? 'error' : 'success',
                            resp.message || ('Push CPN%: ' + applied.ok + ' ok' + (applied.fail ? (', ' + applied.fail + ' failed') : ''))
                        );
                    }
                }
            });
        }
        function startChannelPushCpnPoll() {
            stopChannelPushCpnPoll();
            chPromoPushCpnPollTimer = setInterval(pollChannelPushCpnStatus, 1000);
            pollChannelPushCpnStatus();
        }
        function cancelChannelPushCpnJob() {
            if (!confirm('Cancel remaining Push CPN% jobs?')) return;
            $.ajax({
                url: CH_PROMO_PUSH_CPN_QUEUE_URL + '/cancel',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: { _token: chPromoCsrf() },
            }).done(function(resp) {
                chPromoToast('success', (resp && resp.message) || 'Push CPN% cancelled');
                pollChannelPushCpnStatus();
            }).fail(function(xhr) {
                chPromoToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Cancel failed');
            });
        }
        /** Queue SKUs in chunks of 25; worker processes 10 at a time. */
        async function queueChannelPushCpnItems(items) {
            if (!items || !items.length) {
                chPromoToast('error', 'Nothing to queue');
                return;
            }
            chPromoPushCpnWatching = true;
            clearTimeout(setChPromoPushPrcProgress._hideTimer);
            setChPromoPushPrcProgress({
                active: true, done: 0, total: items.length, ok: 0, fail: 0, pct: 5,
                cancelable: true, title: 'Pushing CPN%',
                msg: 'Queuing ' + items.length + ' SKU(s) in chunks of ' + CH_PROMO_PUSH_CPN_CHUNK + '…',
            });
            const chunks = [];
            for (let i = 0; i < items.length; i += CH_PROMO_PUSH_CPN_CHUNK) {
                chunks.push(items.slice(i, i + CH_PROMO_PUSH_CPN_CHUNK));
            }
            try {
                for (let c = 0; c < chunks.length; c++) {
                    await $.ajax({
                        url: CH_PROMO_PUSH_CPN_QUEUE_URL,
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                        data: { _token: chPromoCsrf(), items: chunks[c] },
                        timeout: 60000,
                    });
                    setChPromoPushPrcProgress({
                        active: true, done: 0, total: items.length, pct: Math.min(15, Math.round(((c + 1) / chunks.length) * 12)),
                        cancelable: true, title: 'Pushing CPN%',
                        msg: 'Queued chunk ' + (c + 1) + '/' + chunks.length,
                    });
                }
                chPromoToast('success', 'Queued ' + items.length + ' Push CPN% job(s) in ' + chunks.length + ' chunk(s)');
                startChannelPushCpnPoll();
            } catch (xhr) {
                const msg = (xhr && xhr.responseJSON && xhr.responseJSON.message) || 'Could not queue Push CPN%';
                chPromoToast('error', msg);
                setChPromoPushPrcProgress({ active: false, done: 0, total: 0, msg: msg });
            }
        }
        function queueChannelPushCpnRows(rows) {
            const items = [];
            rows.forEach(function(item) {
                const d = item.d || item.row.getData();
                if (chPromoIsParentRow(d)) return;
                const sku = chPromoSku(d);
                const cpn = chPromoCpnInt(d);
                if (!sku) return;
                item.row.update({ PUSH_CPN_STATUS: 'processing', push_cpn: 'processing' });
                chPromoRefreshPushCell(
                    item.row,
                    'push_cpn',
                    '.ch-promo-push-cpn-queue-btn, .ch-promo-push-cpn-col-btn',
                    'PUSH_CPN_STATUS',
                    'Pushing CPN%…'
                );
                items.push({ sku: sku, cpn: cpn });
            });
            if (typeof table !== 'undefined' && table) {
                try { table.redraw(true); } catch (e) { /* ignore */ }
            }
            return queueChannelPushCpnItems(items);
        }
        function pushChannelCpnFromChild(row) {
            const d = row.getData() || {};
            const parentRow = chPromoFindParentRowForChild(d);
            if (parentRow) {
                pushChannelCpnFromParent(parentRow);
                return;
            }
            if (!chPromoCpnNeedsPush(d) && String(d.PUSH_CPN_STATUS || '') !== 'error') {
                chPromoToast('info', 'SKU already on matching coupon');
                return;
            }
            const sku = chPromoSku(d);
            const cpn = chPromoCpnInt(d);
            if (!sku) {
                chPromoToast('error', 'SKU required');
                return;
            }
            if (cpn > 0 && (cpn < 5 || cpn > 80)) {
                chPromoToast('error', 'CPN% must be 5–80 (or 0 to remove from coupons)');
                return;
            }
            if (!confirm(
                'Queue Push CPN% for ' + sku + '?\n\n'
                + (cpn > 0
                    ? ('Public coded coupon SAVE' + String(cpn).padStart(2, '0') + 'PCT at ' + cpn + '%.')
                    : 'Remove this listing from coupon campaigns.')
            )) {
                return;
            }
            queueChannelPushCpnRows([{ row: row, d: d }]);
        }
        function pushChannelCpnQueued(row) {
            const d = row.getData() || {};
            if (chPromoIsParentRow(d)) {
                pushChannelCpnFromParent(row);
                return;
            }
            pushChannelCpnFromChild(row);
        }
        function pushChannelCpnFromParent(row) {
            const d = row.getData() || {};
            const children = chPromoListingChildren(d);
            const parentName = chPromoParentName(d) || chPromoSku(d) || 'this listing';
            if (!children.length) {
                chPromoToast('error', 'No child SKUs on ' + parentName);
                return;
            }
            const cpn = chPromoParentCpn(d, children);
            if (cpn > 0 && (cpn < 5 || cpn > 80)) {
                chPromoToast('error', 'CPN% must be 5–80 (or 0 to remove from coupons)');
                return;
            }
            const eligible = children.filter(function(t) {
                return chPromoCpnNeedsPush(t.d) || String(t.d.PUSH_CPN_STATUS || '') === 'error';
            });
            if (!eligible.length) {
                chPromoToast('info', 'Listing already on matching coupon for ' + parentName);
                return;
            }
            if (!confirm(
                'Queue Push CPN% for ' + eligible.length + ' SKU(s) on ' + parentName + '?\n\n'
                + (cpn > 0
                    ? ('Public coded coupon SAVE' + String(cpn).padStart(2, '0') + 'PCT at ' + cpn + '%.')
                    : 'Remove this listing from coupon campaigns.')
                + '\nRuns in the background in chunks — safe to refresh.'
            )) {
                return;
            }
            row.update({ PUSH_CPN_STATUS: 'processing', push_cpn: 'processing' });
            chPromoRefreshPushCell(row, 'push_cpn', '.ch-promo-push-cpn-queue-btn, .ch-promo-push-cpn-col-btn', 'PUSH_CPN_STATUS', 'Pushing CPN%…');
            queueChannelPushCpnRows(eligible);
        }
        function bulkPushChannelCpnQueued() {
            if (!chPromoPushCpnQueueEnabled) {
                chPromoToast('error', 'Push CPN% queue is eBay 2 / eBay 3 only');
                return;
            }
            const eligible = chPromoPushCpnCollectEligible();
            if (!eligible.length) {
                chPromoToast('info', 'No SKUs with CPN% changes to push');
                return;
            }
            if (!confirm(
                'Queue Push CPN% for ' + eligible.length + ' SKU(s)?\n\n'
                + 'Same as eBay 1 Push CPN: public coded coupon SAVE{nn}PCT at CPN%.\n'
                + 'Queued in chunks of ' + CH_PROMO_PUSH_CPN_CHUNK + '; worker processes 10 at a time.\n'
                + 'Safe to refresh — progress continues.'
            )) {
                eligible.forEach(function(t) {
                    chPromoRefreshPushCell(t.row, 'push_cpn', '.ch-promo-push-cpn-queue-btn, .ch-promo-push-cpn-col-btn', 'PUSH_CPN_STATUS', 'Pushing CPN%…');
                });
                return;
            }
            queueChannelPushCpnRows(eligible);
        }

        function channelPromoPushCpnColumn() {
            return {
                title: 'Push CPN %',
                field: 'push_cpn',
                width: 72,
                hozAlign: 'center',
                vertAlign: 'middle',
                headerSort: false,
                headerTooltip: 'Push CPN% from the parent row — coded coupon for the whole listing. Click header to bulk selected (or visible) parents.',
                titleFormatter: function() {
                    return '<button type="button" class="btn btn-sm p-0 ch-promo-push-cpn-queue-header-btn" '
                        + 'title="Bulk queue Push CPN% for selected (or visible) parent listings" '
                        + 'style="border:none;background:none;cursor:pointer;color:#000;'
                        + 'font-weight:700;font-size:11px;line-height:1.15;padding:0;">'
                        + 'Push CPN %</button>';
                },
                headerClick: function(e) {
                    if (e.target.closest('.ch-promo-push-cpn-queue-header-btn')) {
                        e.stopPropagation();
                        e.preventDefault();
                        bulkPushChannelCpnQueued();
                        return false;
                    }
                },
                formatter: function(cell) {
                    const d = cell.getRow().getData() || {};
                    const isParent = chPromoIsParentRow(d);
                    if (!isParent && !chPromoIsChildRow(d)) return '';
                    const children = isParent ? chPromoListingChildren(d) : [];
                    const sku = chPromoSku(d);
                    const cpn = isParent ? chPromoParentCpn(d, children) : chPromoCpnInt(d);
                    const last = isParent && children.length
                        ? Math.max.apply(null, children.map(function(t) { return chPromoLastCouponPct(t.d); }).concat([chPromoLastCouponPct(d)]))
                        : chPromoLastCouponPct(d);
                    const status = String(d.PUSH_CPN_STATUS || '')
                        || (isParent ? chPromoParentPushStatus(children, 'PUSH_CPN_STATUS') : '');
                    const code = d.PEF_COUPON_CODE || d.coupon_code
                        || (children[0] && (children[0].d.PEF_COUPON_CODE || children[0].d.coupon_code)) || '';
                    const needs = isParent
                        ? (children.some(function(t) { return chPromoCpnNeedsPush(t.d); })
                            || (children.length === 0 && chPromoCpnNeedsPush(d)))
                        : chPromoCpnNeedsPush(d);
                    if (cpn > 0 && (cpn < 5 || cpn > 80)) {
                        return '<span style="color:#adb5bd;" title="eBay coupon % must be 5–80">—</span>';
                    }
                    if (cpn === 0 && last < 5 && status !== 'error') {
                        return '<span style="color:#adb5bd;" title="Set CPN% then click to queue a coded coupon">—</span>';
                    }
                    let icon = '<i class="fas fa-upload"></i>';
                    let color = '#FF9900';
                    let tip = cpn > 0
                        ? ('Queue ' + cpn + '% public coupon for this listing (' + children.length + ' SKU)')
                        : 'Queue remove this listing from coupons';
                    if (status === 'processing') {
                        icon = '<i class="fas fa-spinner fa-spin" style="font-size:14px;"></i>';
                        color = '#ffc107';
                        tip = 'Pushing CPN%…';
                    } else if (status === 'error') {
                        icon = '<i class="fa-solid fa-xmark"></i>';
                        color = '#dc3545';
                        tip = 'Last Push CPN% failed — click to retry';
                    } else if (!needs) {
                        icon = '<i class="fa-solid fa-check-double"></i>';
                        color = '#28a745';
                        tip = 'Listing on ' + last + '% coupon'
                            + (code ? (' (' + code + ')') : '')
                            + ' — click to queue again';
                    } else if (last >= 5 && cpn > 0 && last !== cpn) {
                        tip = 'CPN% changed ' + last + '% → ' + cpn + '% — click to queue listing';
                    } else if (cpn === 0 && last >= 5) {
                        icon = '<i class="fa-solid fa-xmark"></i>';
                        color = '#dc3545';
                        tip = 'CPN% is 0 — click to queue remove from ' + last + '% coupon';
                    }
                    return '<button type="button" class="btn btn-sm p-0 ch-promo-push-cpn-queue-btn" '
                        + 'data-sku="' + chPromoEscAttr(sku) + '" '
                        + 'title="' + chPromoEscAttr(tip) + '" '
                        + 'style="border:none;background:none;cursor:pointer;color:' + color
                        + ';padding:0;line-height:1;vertical-align:middle;">'
                        + icon + '</button>';
                },
                cellClick: function(e, cell) {
                    const btn = e.target.closest('.ch-promo-push-cpn-queue-btn');
                    if (!btn) return;
                    e.stopPropagation();
                    e.preventDefault();
                    const d = cell.getRow().getData() || {};
                    if (String(d.PUSH_CPN_STATUS || '') === 'processing') return false;
                    const selectedParents = collectChPromoSelectedParentRows();
                    const selectedKids = collectChPromoSelectedRows();
                    const clickedKey = chPromoSkuKey(chPromoSku(d));
                    if (chPromoIsParentRow(d)
                        && selectedParents.length > 1
                        && selectedParents.some(function(t) {
                            return chPromoSkuKey(chPromoSku(t.d)) === clickedKey;
                        })) {
                        chPromoPaintPushSpinner(btn, 'Pushing CPN%…');
                        bulkPushChannelCpnQueued();
                        return false;
                    }
                    if (chPromoIsChildRow(d)
                        && selectedKids.length > 1
                        && selectedKids.some(function(t) {
                            return chPromoSkuKey(chPromoSku(t.d)) === clickedKey;
                        })) {
                        chPromoPaintPushSpinner(btn, 'Pushing CPN%…');
                        bulkPushChannelCpnQueued();
                        return false;
                    }
                    chPromoPaintPushSpinner(btn, 'Pushing CPN%…');
                    pushChannelCpnQueued(cell.getRow());
                    return false;
                },
            };
        }

        /** eBay1: push CPN% to public coded coupon (create or add SKU to same-% campaign). */
        function syncEbay1CodedCoupon(sku, percent) {
            return new Promise(function(resolve) {
                $.ajax({
                    url: CH_PROMO_EBAY1_COUPON_URL,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                    data: { sku: sku, percent: percent, _token: chPromoCsrf() },
                }).done(function(res) {
                    resolve({
                        ok: !!(res && res.success),
                        message: (res && res.message) || '',
                        coupon_code: (res && res.coupon_code) || null,
                        promotion_id: (res && res.promotion_id) || null,
                    });
                }).fail(function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message)
                        || 'eBay1 coupon API error';
                    resolve({ ok: false, message: msg, coupon_code: null, promotion_id: null });
                });
            });
        }

        function chPromoCpnInt(d) {
            return Math.round(Math.max(0, Number(d && (d.cpn_pct != null ? d.cpn_pct : d._cpn_pct_applied)) || 0));
        }
        /** PRMT% used in S PRC (Dil vs PRMT slab on eBay / AliExpress / Shein). */
        function chPromoEbayPrmtUsed(d) {
            let prmt = Math.max(0, Number(d && (d.prmt_pct != null && d.prmt_pct !== ''
                ? d.prmt_pct : d._prmt_pct_applied)) || 0);
            if (CHANNEL_PROMO_CHANNEL === 'aliexpress'
                && typeof chPromoHasSaleQty === 'function'
                && !chPromoHasSaleQty(d)) {
                if (typeof chPromoKeepZeroSoldPrcSprice === 'function' && chPromoKeepZeroSoldPrcSprice(d)) {
                    return chPromoRound2(prmt);
                }
                return 0;
            }
            const zeroSoldRoi = !!(d && (d.ZERO_SOLD_PRC_APPLIED === true || d.ZERO_SOLD_PRC_APPLIED === 1
                || d.ZERO_SOLD_PRC_APPLIED === '1' || d.ZERO_SOLD_PRC_APPLIED === 'true'
                || Number(d.PEF_PRMT_PCT) > 0 || Number(d.PEF_CPN_PCT) > 0));
            if (!zeroSoldRoi && chPromoUsesLiveDilPrmtSlabs()) {
                const slab = typeof chPromoEbaySlabPrmt === 'function' ? chPromoEbaySlabPrmt(d) : null;
                if (slab != null) prmt = slab;
            }
            return chPromoRound2(prmt);
        }
        /** cvr % / CPN% used in S PRC. */
        function chPromoEbayCpnUsed(d) {
            let cpn = Math.max(0, Number(d && (d.cpn_pct != null && d.cpn_pct !== ''
                ? d.cpn_pct : d._cpn_pct_applied)) || 0);
            if (chPromoIsEbayChannel() && !(cpn > 0) && typeof chPromoCpnForCvr === 'function') {
                const cvrField = (chPromoCfg && chPromoCfg.cvrField) ? chPromoCfg.cvrField : 'SCVR';
                const cvr = Number(d && (d[cvrField] != null ? d[cvrField] : d.SCVR)) || 0;
                const slabCpn = chPromoCpnForCvr(cvr);
                if (slabCpn > 0) cpn = slabCpn;
            }
            return chPromoRound2(cpn);
        }
        /** PRMT% + CPN% + CVR Up/Dn (T Promo / T Discounts). */
        function chPromoTPromoPct(d) {
            const adj = (typeof computeCvrUpDnPct === 'function') ? (Number(computeCvrUpDnPct(d)) || 0) : 0;
            return chPromoRound2(chPromoEbayPrmtUsed(d) + chPromoEbayCpnUsed(d) + adj);
        }
        function chPromoCvrUpDnEnabled() {
            return CHANNEL_PROMO_CHANNEL === 'ebay1'
                || CHANNEL_PROMO_CHANNEL === 'temu'
                || CHANNEL_PROMO_CHANNEL === 'temu2';
        }
        function chPromoLastCouponPct(d) {
            return Math.round(Math.max(0, Number(d && d.PEF_COUPON_PCT) || 0));
        }
        /** True when CPN% should be synced to a public coded coupon (new, changed, remove, or last error). */
        function chPromoCpnNeedsPush(d) {
            const cpn = chPromoCpnInt(d);
            if (cpn > 0 && (cpn < 5 || cpn > 80)) return false;
            if (String((d && d.PUSH_CPN_STATUS) || '') === 'error') return true;
            const last = chPromoLastCouponPct(d);
            if (cpn === 0) return last >= 5;
            return last !== cpn;
        }
        async function pushChannelCpnOne(row, opts) {
            opts = opts || {};
            const silent = !!opts.silent;
            const d = row.getData() || {};
            const sku = chPromoSku(d);
            const cpn = chPromoCpnInt(d);
            if (chPromoPushCpnQueueEnabled) {
                if (!sku || !chPromoIsChildRow(d)) {
                    if (!silent) chPromoToast('error', 'SKU required');
                    return { ok: false, skipped: true };
                }
                if (cpn > 0 && (cpn < 5 || cpn > 80)) {
                    if (!silent) chPromoToast('error', 'CPN% must be 5–80 (or 0 to clear)');
                    return { ok: false, skipped: true };
                }
                if (cpn === 0 && chPromoLastCouponPct(d) < 5 && String(d.PUSH_CPN_STATUS || '') !== 'error') {
                    if (!silent) chPromoToast('info', 'Set CPN% first, then click Push CPN');
                    return { ok: true, skipped: true };
                }
                return queueChannelPushCpnRows([{ row: row, d: d }]).then(function() {
                    return { ok: true };
                }).catch(function() {
                    return { ok: false };
                });
            }
            if (!sku || !chPromoIsChildRow(d)) {
                if (!silent) chPromoToast('error', 'SKU required');
                return { ok: false, skipped: true };
            }
            if (cpn > 0 && (cpn < 5 || cpn > 80)) {
                if (!silent) chPromoToast('error', 'CPN% must be 5–80 (or 0 to remove from coupons)');
                return { ok: false, skipped: true };
            }
            if (cpn === 0 && chPromoLastCouponPct(d) < 5 && String(d.PUSH_CPN_STATUS || '') !== 'error') {
                if (!silent) chPromoToast('info', 'Set CPN% first, then click Push CPN');
                return { ok: true, skipped: true };
            }
            row.update({ PUSH_CPN_STATUS: 'processing' });
            if (!silent) {
                clearTimeout(setChPromoPushPrcProgress._hideTimer);
                setChPromoPushPrcProgress({
                    active: true, done: 0, total: 1, ok: 0, fail: 0, pct: 20,
                    title: 'Pushing',
                    msg: sku + (cpn > 0 ? (' · ' + cpn + '% coupon') : ' · removing from coupon'),
                });
            }
            const api = await syncEbay1CodedCoupon(sku, cpn);
            if (api.ok) {
                const applied = cpn > 0 ? cpn : 0;
                const code = api.coupon_code || d.PEF_COUPON_CODE || d.coupon_code || null;
                row.update({
                    PUSH_CPN_STATUS: 'pushed',
                    PEF_COUPON_PCT: applied,
                    PEF_COUPON_CODE: applied ? code : null,
                    coupon_code: applied ? code : null,
                    PEF_COUPON_PROMOTION_ID: api.promotion_id || (applied ? d.PEF_COUPON_PROMOTION_ID : null),
                });
                if (!silent) {
                    setChPromoPushPrcProgress({
                        active: false, done: 1, total: 1, ok: 1, fail: 0, pct: 100,
                        title: 'Pushed',
                        msg: sku + (applied ? (' · ' + (code || (applied + '% coupon'))) : ' · removed from coupon'),
                    });
                    chPromoToast('success', api.message || (
                        applied
                            ? ('SKU on ' + (code || (applied + '% coupon')))
                            : 'SKU removed from coupons'
                    ));
                }
                return { ok: true, coupon_code: code };
            }
            row.update({ PUSH_CPN_STATUS: 'error' });
            if (!silent) {
                setChPromoPushPrcProgress({
                    active: false, done: 1, total: 1, ok: 0, fail: 1, pct: 100,
                    title: 'Push failed',
                    msg: sku + ' · ' + (api.message || 'coupon failed'),
                });
                chPromoToast('error', api.message || ('Push CPN failed for ' + sku));
            }
            return { ok: false };
        }
        let chPromoPushCpnBusy = false;
        async function bulkPushChannelCpn() {
            if (chPromoPushCpnQueueEnabled) {
                let targets = collectChPromoSelectedRows();
                let scopeLabel = 'selected';
                if (!targets.length) {
                    targets = collectChPromoVisibleRows();
                    scopeLabel = 'visible';
                }
                const eligible = targets.filter(function(t) {
                    return chPromoIsChildRow(t.d) && chPromoCpnNeedsPush(t.d);
                });
                const skipped = targets.length - eligible.length;
                if (!eligible.length) {
                    chPromoToast('info', skipped
                        ? ('No CPN changes (' + skipped + ' already pushed or CPN% not 5–80)')
                        : 'No SKUs for Push CPN');
                    return;
                }
                if (!confirm(
                    'Queue Push CPN for ' + eligible.length + ' ' + scopeLabel + ' SKU(s)?\n\n'
                    + (CHANNEL_PROMO_CHANNEL === 'temu'
                        ? 'Saves CPN% and pushes S PRC to Temu in the background (chunks of '
                            + CH_PROMO_PUSH_CPN_CHUNK + ').\n'
                        : 'Creates/adds the public coded coupon in the background.\n')
                    + (skipped ? skipped + ' already matching will be skipped.' : '')
                )) return;
                return queueChannelPushCpnRows(eligible);
            }
            if (CHANNEL_PROMO_CHANNEL !== 'ebay1') {
                chPromoToast('error', 'Push CPN is eBay1 only');
                return;
            }
            if (chPromoPushCpnBusy) {
                chPromoToast('info', 'Push CPN already running');
                return;
            }
            let targets = collectChPromoSelectedRows();
            let scopeLabel = 'selected';
            if (!targets.length) {
                targets = collectChPromoVisibleRows();
                scopeLabel = 'visible';
            }
            const eligible = targets.filter(function(t) { return chPromoCpnNeedsPush(t.d); });
            const skipped = targets.length - eligible.length;
            if (!eligible.length) {
                chPromoToast('info', skipped
                    ? ('No coupon changes (' + skipped + ' already on matching % or CPN% not 5–80)')
                    : 'No SKUs for Push CPN');
                return;
            }
            if (!confirm(
                'Push CPN for ' + eligible.length + ' ' + scopeLabel + ' SKU(s)?\n\n'
                + 'Creates the public coded coupon for that CPN% if it does not exist (SAVE{nn}PCT), then adds the SKU.\n'
                + 'If CPN% changed, the SKU is removed from the old % coupon first.\n'
                + (skipped ? skipped + ' already on the matching coupon will be skipped.' : '')
            )) return;
            chPromoPushCpnBusy = true;
            let ok = 0, fail = 0, done = 0;
            clearTimeout(setChPromoPushPrcProgress._hideTimer);
            setChPromoPushPrcProgress({
                active: true, done: 0, total: eligible.length, ok: 0, fail: 0, pct: 5,
                title: 'Pushing',
                msg: 'Starting ' + eligible.length + ' coupon(s)…',
            });
            try {
                await chPromoMapLimit(eligible, 3, async function(item) {
                    const sku = chPromoSku(item.d);
                    const res = await pushChannelCpnOne(item.row, { silent: true });
                    if (res && res.ok && !res.skipped) ok++;
                    else if (!(res && res.skipped)) fail++;
                    done++;
                    setChPromoPushPrcProgress({
                        active: true, done: done, total: eligible.length, ok: ok, fail: fail,
                        title: 'Pushing',
                        msg: sku + ' · coupon',
                    });
                });
            } finally {
                chPromoPushCpnBusy = false;
                setChPromoPushPrcProgress({
                    active: false, done: eligible.length, total: eligible.length, ok: ok, fail: fail, pct: 100,
                    title: fail && !ok ? 'Push failed' : 'Pushed',
                    msg: ok + ' ok' + (fail ? (' · ' + fail + ' failed') : ''),
                });
            }
            chPromoToast(
                fail && !ok ? 'error' : 'success',
                'Push CPN: ' + ok + ' ok' + (fail ? (' / ' + fail + ' fail') : '')
                + (skipped ? ('; skipped ' + skipped) : '')
            );
            if (typeof table !== 'undefined' && table) table.redraw(true);
        }

        async function chPromoMapLimit(items, limit, fn, onProgress) {
            const n = Math.max(1, Number(limit) || 8);
            let i = 0;
            let done = 0;
            const total = items.length;
            async function worker() {
                while (i < items.length) {
                    const idx = i++;
                    await fn(items[idx], idx);
                    done++;
                    if (typeof onProgress === 'function') {
                        onProgress(done, total);
                    }
                }
            }
            const workers = [];
            for (let w = 0; w < Math.min(n, items.length || 1); w++) workers.push(worker());
            await Promise.all(workers);
        }

        function chPromoSetApplyBtnProgress($btn, done, total, label) {
            if (!$btn || !$btn.length) return;
            const t = Math.max(0, Number(total) || 0);
            const d = Math.max(0, Math.min(Number(done) || 0, t || Number(done) || 0));
            const pct = t > 0 ? Math.round((d / t) * 100) : 0;
            const text = t > 0
                ? ((label || 'Applying') + '… ' + d + '/' + t + ' (' + pct + '%)')
                : ((label || 'Applying') + '…');
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>' + text);
            const $status = $('#ch-promo-dil-prmt-status');
            if ($status.length) $status.text(text);
        }

        const CH_PEF_DIL_PRMT_DEFAULTS_FULL = [
            { key: '0-10', label: '0–10%', prmt: 10 },
            { key: '10-20', label: '10–20%', prmt: 9 },
            { key: '20-30', label: '20–30%', prmt: 8 },
            { key: '30-40', label: '30–40%', prmt: 7 },
            { key: '40-50', label: '40–50%', prmt: 6 },
            { key: '50-60', label: '50–60%', prmt: 5 },
            { key: '60-70', label: '60–70%', prmt: 4 },
            { key: '70-80', label: '70–80%', prmt: 3 },
            { key: '80-90', label: '80–90%', prmt: 2 },
            { key: '90-100', label: '90–100%', prmt: 1 },
            { key: 'gt-100', label: '> 100%', prmt: 0 },
        ];
        const CH_PEF_SKIP_DIL_EQ_ZERO = CHANNEL_PROMO_CHANNEL === 'aliexpress';
        const CH_PEF_DIL_PRMT_DEFAULTS_EBAY = (function() {
            const rules = [];
            if (!CH_PEF_SKIP_DIL_EQ_ZERO) {
                rules.push({ key: 'eq-0', label: '0–0%', prmt: 12 });
            }
            rules.push({
                key: '0.1-2',
                label: CH_PEF_SKIP_DIL_EQ_ZERO ? '0–2%' : '0.1–2%',
                prmt: 11,
            });
            let prmt = 10;
            for (let max = 4; max <= 26; max += 2) {
                const min = max - 2;
                rules.push({ key: min + '-' + max, label: min + '–' + max + '%', prmt: Math.max(0, prmt) });
                prmt -= 1;
            }
            return rules;
        })();
        const CH_PEF_USES_AMAZON_DIL_SLABS = CHANNEL_PROMO_CHANNEL === 'tiktok' || CHANNEL_PROMO_CHANNEL === 'tiktok2';
        const CH_PEF_USES_EBAY_FINE_DIL = !CH_PEF_USES_AMAZON_DIL_SLABS;
        const CH_PEF_DIL_PRMT_DEFAULTS_ZERO_SOLD = [
            { key: '0-sold-red', label: '0 Sold · Red (<25%)', prmt: 10 },
            { key: '0-sold-green', label: '0 Sold · Green (25–50%)', prmt: 8 },
            { key: '0-sold-pink', label: '0 Sold · Pink (50%+)', prmt: 3 },
        ];
        const CH_PEF_DIL_PRMT_DEFAULTS_REVERB_SLABS = [
            { key: '0-20', label: CHANNEL_PROMO_CHANNEL === 'reverb' ? '0.1–20%' : '0–20%', prmt: 10 },
            { key: '20-40', label: '20–40%', prmt: 8 },
            { key: '40-60', label: '40–60%', prmt: 5 },
            { key: '60-80', label: '60–80%', prmt: 3 },
            { key: '80-100', label: '80–100%', prmt: 1 },
            { key: 'gt-100', label: '> 100%', prmt: 0 },
        ];
        const CH_PEF_DIL_PRMT_DEFAULTS_REVERB = CHANNEL_PROMO_SHOW_ZERO_SOLD_RULES
            ? CH_PEF_DIL_PRMT_DEFAULTS_ZERO_SOLD.concat(CH_PEF_DIL_PRMT_DEFAULTS_REVERB_SLABS)
            : CH_PEF_DIL_PRMT_DEFAULTS_REVERB_SLABS;
        const CH_PEF_USES_REVERB_SLABS = false;
        const CH_PEF_DIL_PRMT_DEFAULTS = CH_PEF_USES_REVERB_SLABS
            ? CH_PEF_DIL_PRMT_DEFAULTS_REVERB
            : (CH_PEF_USES_EBAY_FINE_DIL ? CH_PEF_DIL_PRMT_DEFAULTS_EBAY : CH_PEF_DIL_PRMT_DEFAULTS_FULL);
        const CH_PEF_CVR_CPN_DEFAULTS_ALL = [
            { key: 'eq-0', label: '0%', cpn: 10 },
            { key: '0.01-1', label: '0.01–1%', cpn: 9 },
            { key: '1-1.5', label: '1–1.5%', cpn: 8 },
            { key: '1.5-2', label: '1.5–2%', cpn: 7 },
            { key: '2-3', label: '2–3%', cpn: 6 },
            { key: '3-4', label: '3–4%', cpn: 5 },
            { key: '4-5', label: '4–5%', cpn: 4 },
            { key: '5-6', label: '5–6%', cpn: 3 },
            { key: '6-6.5', label: '6–6.5%', cpn: 2 },
            { key: '6.5-7', label: '6.5–7%', cpn: 1 },
            { key: 'gt-7', label: '> 7%', cpn: 0 },
        ];
        const CH_PEF_CVR_CPN_SKIP_ZERO = CHANNEL_PROMO_CHANNEL === 'ebay2op';
        const CH_PEF_CVR_CPN_DEFAULTS = CH_PEF_CVR_CPN_SKIP_ZERO
            ? CH_PEF_CVR_CPN_DEFAULTS_ALL.filter(function(r) { return r.key !== 'eq-0'; })
            : CH_PEF_CVR_CPN_DEFAULTS_ALL;

        let chPromoDilPrmtRules = CH_PEF_DIL_PRMT_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let chPromoCvrCpnRules = CH_PEF_CVR_CPN_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        const CH_PEF_ZERO_SOLD_DIL_DEFAULTS = CHANNEL_PROMO_ZERO_SOLD_DIL_COLOR_SLABS
            ? [
                { key: 'red', label: 'Red Dil (<25%)', groi: 40 },
                { key: 'green', label: 'Green Dil (25–50%)', groi: 25 },
                { key: 'pink', label: 'Pink Dil (50%+)', groi: 10 },
            ]
            : [
                { key: '0-10', label: '0–10%', groi: 40 },
                { key: '10-20', label: '10–20%', groi: 35 },
                { key: '20-30', label: '20–30%', groi: 30 },
                { key: '30-40', label: '30–40%', groi: 25 },
                { key: '40-50', label: '40–50%', groi: 20 },
                { key: '50-60', label: '50–60%', groi: 15 },
                { key: '60-70', label: '60–70%', groi: 12 },
                { key: '70-80', label: '70–80%', groi: 10 },
                { key: '80-90', label: '80–90%', groi: 8 },
                { key: '90-100', label: '90–100%', groi: 5 },
                { key: 'gt-100', label: '> 100%', groi: 0 },
            ];
        let chPromoZeroSoldDilRules = CH_PEF_ZERO_SOLD_DIL_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let chPromoZeroSoldDilRun = {
            active: false,
            paused: false,
            jobs: [],
            index: 0,
            ok: 0,
            fail: 0,
            skipped: 0,
            label: '',
        };
        const CH_PEF_GT_SOLD_PRC_DEFAULTS = [
            { key: 'gt-sold-red', label: 'Red Dil (<25%)', pct: 0, dir: 'increase' },
            { key: 'gt-sold-green', label: 'Green Dil (25–50%)', pct: 0, dir: 'increase' },
            { key: 'gt-sold-pink', label: 'Pink Dil (50%+)', pct: 0, dir: 'increase' },
        ];
        let chPromoGtSoldPrcRules = CH_PEF_GT_SOLD_PRC_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let chPromoPushPrcCancel = false;
        let chPromoPushPrcPollTimer = null;
        let chPromoPushPrcLastToastKey = '';
        let chPromoPushPrcWatching = false;
        let chPromoPushPrmtWatching = false;
        let chPromoPushCpnWatching = false;

        function chPromoPushAckKey(kind, jobId) {
            return 'chPromoPushAck:' + CHANNEL_PROMO_CHANNEL + ':' + kind + ':' + String(jobId || '');
        }
        function chPromoPushIsAcked(kind, jobId) {
            if (!jobId) return false;
            try { return sessionStorage.getItem(chPromoPushAckKey(kind, jobId)) === '1'; } catch (e) { return false; }
        }
        function chPromoPushAck(kind, jobId) {
            if (!jobId) return;
            try { sessionStorage.setItem(chPromoPushAckKey(kind, jobId), '1'); } catch (e) { /* ignore */ }
        }

        /**
         * Show the push popup only while a job is running, or when this tab
         * watched it finish. A completed job on page reload stays hidden.
         */
        function chPromoApplyQueuedPushStatus(kind, watching, resp, activeTitle) {
            const active = !!resp.active;
            const total = Number(resp.total) || 0;
            const done = Number(resp.done_count) || 0;
            const ok = Number(resp.ok_count) || 0;
            const fail = Number(resp.fail_count) || 0;
            const pct = Number(resp.pct) || 0;
            const jobStatus = resp.job && resp.job.status ? String(resp.job.status) : 'idle';
            const jobId = resp.job && resp.job.id ? String(resp.job.id) : '';
            const finished = !active && total > 0;
            const acked = chPromoPushIsAcked(kind, jobId);
            let nextWatching = watching;
            let shouldToast = false;

            if (active) {
                nextWatching = true;
                setChPromoPushPrcProgress({
                    active: true, done: done, total: total, ok: ok, fail: fail, pct: pct,
                    cancelable: true, title: activeTitle,
                    msg: resp.message || (resp.job && resp.job.last_message) || '',
                });
            } else if (finished && watching && !acked) {
                setChPromoPushPrcProgress({
                    active: false, done: done, total: total, ok: ok, fail: fail, pct: pct,
                    cancelable: false,
                    msg: resp.message || (resp.job && resp.job.last_message) || '',
                });
                chPromoPushAck(kind, jobId);
                nextWatching = false;
                shouldToast = (jobStatus === 'completed' || jobStatus === 'failed' || jobStatus === 'stopped');
            } else if (finished) {
                chPromoPushAck(kind, jobId);
                nextWatching = false;
            }

            return {
                watching: nextWatching,
                shouldToast: shouldToast,
                jobStatus: jobStatus,
                ok: ok,
                fail: fail,
                total: total,
            };
        }
        const CH_PROMO_PUSH_QUEUE_CHANNELS = ['ebay1', 'ebay2', 'ebay2op', 'ebay3'];
        const chPromoPushQueueEnabled = CH_PROMO_PUSH_QUEUE_CHANNELS.indexOf(CHANNEL_PROMO_CHANNEL) !== -1;
        const CH_PROMO_PUSH_QUEUE_URL = '/channel-push-prc/' + encodeURIComponent(CHANNEL_PROMO_CHANNEL);
        @include('partials.channel-push-sprice-queue', ['channelPushSpriceChannel' => $channelPromoChannel])
        const CH_PROMO_PUSH_PRMT_QUEUE_CHANNELS = ['ebay2', 'ebay2op', 'ebay3'];
        const chPromoPushPrmtQueueEnabled = CH_PROMO_PUSH_PRMT_QUEUE_CHANNELS.indexOf(CHANNEL_PROMO_CHANNEL) !== -1;
        const CH_PROMO_PUSH_PRMT_QUEUE_URL = '/channel-push-prmt/' + encodeURIComponent(CHANNEL_PROMO_CHANNEL);
        const CH_PROMO_PUSH_PRMT_CHUNK = 25;
        let chPromoPushPrmtPollTimer = null;
        let chPromoPushPrmtLastToastKey = '';
        let chPromoPushPrmtLastTasks = [];
        const CH_PROMO_PUSH_CPN_QUEUE_CHANNELS = ['ebay2', 'ebay2op', 'ebay3', 'temu'];
        const chPromoPushCpnQueueEnabled = CH_PROMO_PUSH_CPN_QUEUE_CHANNELS.indexOf(CHANNEL_PROMO_CHANNEL) !== -1;
        const CH_PROMO_PUSH_CPN_QUEUE_URL = '/channel-push-cpn/' + encodeURIComponent(CHANNEL_PROMO_CHANNEL);
        const CH_PROMO_PUSH_CPN_CHUNK = 25;
        let chPromoPushCpnPollTimer = null;
        let chPromoPushCpnLastToastKey = '';

        function chPromoCsrf() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        }
        function chPromoCapSpriceToLmp(d, sprice) {
            if (window.SpriceLmpCap) return SpriceLmpCap.prepare(d, sprice);
            const lmp = parseFloat(d && (d.lmp_price || d.lmp || d.LMP)) || 0;
            let s = chPromoRound2(sprice);
            if (lmp > 0 && s + 0.0001 >= lmp) s = chPromoRound2(lmp);
            return s;
        }
        function chPromoRowDataFromExtra(extra) {
            extra = extra || {};
            if (extra.row && typeof extra.row.getData === 'function') return extra.row.getData();
            if (extra.row && typeof extra.row === 'object' && !extra.row.getData) return extra.row;
            if (extra.rowData) return extra.rowData;
            return null;
        }
        function chPromoRound2(n) {
            return Math.round((Number(n) || 0) * 100) / 100;
        }
        function chPromoToast(type, msg) {
            if (typeof showToast === 'function') {
                try { showToast(type, msg); } catch (e) { showToast(msg, type); }
            } else if (typeof toast === 'function') toast(msg, type);
            else console.log(type, msg);
        }
        function chPromoSku(d) {
            const f = chPromoCfg.skuField || '(Child) sku';
            return String((d && (d[f] || d['(Child) sku'] || d.sku)) || '').trim();
        }
        function chPromoSkuKey(sku) {
            return String(sku == null ? '' : sku).trim().toUpperCase();
        }
        function chPromoIsChildRow(d) {
            return !!(d && !d.is_parent_summary && chPromoSku(d) && String(chPromoSku(d)).indexOf('PARENT') === -1);
        }
        function chPromoIsEndedListing(d) {
            if (!d || !chPromoIsChildRow(d)) return false;
            if (d.listing_ended === true || d.listing_ended === 1 || d.listing_ended === '1') return true;
            const raw = String(d.listing_status || '').trim().toUpperCase();
            return raw === 'ENDED' || raw === 'INACTIVE' || raw === 'UNSOLD'
                || raw === 'COMPLETED' || raw === 'SOLD';
        }
        function chPromoIsParentRow(d) {
            if (!d) return false;
            if (d.is_parent_summary || d.is_parent_row || d.is_parent) return true;
            if (d.Parent && String(d.Parent).toUpperCase().startsWith('PARENT')) return true;
            const sku = chPromoSku(d);
            return !!(sku && String(sku).toUpperCase().indexOf('PARENT') !== -1);
        }
        /** Child SKUs that share this parent / variation listing. */
        function chPromoListingChildren(parentD) {
            const parent = chPromoParentName(parentD).toUpperCase();
            const itemId = String((parentD && (parentD.eBay_item_id || parentD.item_id)) || '').trim();
            const out = [];
            const seen = new Set();
            chPromoEachTableRow(function(row, d) {
                if (!chPromoIsChildRow(d)) return;
                const sameItem = itemId && String(d.eBay_item_id || d.item_id || '').trim() === itemId;
                const sameParent = parent && chPromoParentName(d).toUpperCase() === parent;
                if (!sameItem && !sameParent) return;
                const sku = chPromoSkuKey(chPromoSku(d));
                if (!sku || seen.has(sku)) return;
                seen.add(sku);
                out.push({ row: row, d: d });
            });
            return out;
        }
        function chPromoParentPrmt(d, children) {
            const p = chPromoPrmtInt(d);
            if (p > 0) return p;
            if (children && children[0]) return chPromoPrmtInt(children[0].d);
            return 0;
        }
        function chPromoParentCpn(d, children) {
            const p = chPromoCpnInt(d);
            if (p > 0) return p;
            if (children && children[0]) return chPromoCpnInt(children[0].d);
            return 0;
        }
        function chPromoParentPushStatus(children, statusKey) {
            let processing = 0;
            let error = 0;
            let pushed = 0;
            (children || []).forEach(function(t) {
                const st = String((t.d && t.d[statusKey]) || '');
                if (st === 'processing') processing++;
                else if (st === 'error') error++;
                else if (st === 'pushed') pushed++;
            });
            if (processing) return 'processing';
            if (pushed) return 'pushed';
            if (error) return 'error';
            return '';
        }
        function chPromoSyncParentPushButtons(kind) {
            const statusKey = kind === 'cpn' ? 'PUSH_CPN_STATUS' : 'PUSH_SALE_STATUS';
            const field = kind === 'cpn' ? 'push_cpn' : 'push_prmt';
            const btn = kind === 'cpn'
                ? '.ch-promo-push-cpn-queue-btn, .ch-promo-push-cpn-col-btn'
                : '.ch-promo-push-prmt-btn';
            const title = kind === 'cpn' ? 'Pushing CPN%…' : 'Pushing PRMT%…';
            chPromoEachTableRow(function(row, d) {
                if (!chPromoIsParentRow(d)) return;
                const children = chPromoListingChildren(d);
                const st = chPromoParentPushStatus(children, statusKey) || String(d[statusKey] || '');
                if (!st) return;
                const patch = {};
                patch[statusKey] = st;
                patch[field] = st;
                if (st === 'pushed' && kind === 'prmt') {
                    patch.PEF_SALE_PCT = chPromoParentPrmt(d, children);
                }
                if (st === 'pushed' && kind === 'cpn') {
                    patch.PEF_COUPON_PCT = chPromoParentCpn(d, children);
                }
                row.update(patch);
                if (st === 'processing') chPromoRefreshPushCell(row, field, btn, statusKey, title);
            });
        }
        function chPromoInv(d) {
            return Number(d[chPromoCfg.invField || 'INV']) || Number(d.INV) || Number(d.inventory) || Number(d.inv) || 0;
        }
        function chPromoPrice(d) {
            const f = chPromoCfg.priceField;
            let p = f ? Number(d[f]) : NaN;
            if (isFinite(p) && p > 0) return p;
            // Macys: sheet field (MC Price) only — never fall back to a generic/product price.
            if (CHANNEL_PROMO_CHANNEL === 'macys' || CHANNEL_PROMO_CHANNEL === 'macy') {
                return 0;
            }
            p = Number(d.price);
            if (isFinite(p) && p > 0) return p;
            return 0;
        }
        function chPromoGetSprice(d) {
            let s = Number(d.SPRICE);
            if (isFinite(s) && s > 0) return s;
            s = Number(d.sprice);
            if (isFinite(s) && s > 0) return s;
            return 0;
        }
        /** Live S PRC from Dil/cvr rules only. Never uses stored SPRICE or live Price. */
        function chPromoLiveSprice(d) {
            if (!d || !chPromoIsChildRow(d)) return 0;
            return chPromoSpriceFromStdTPromo(d) || 0;
        }
        function chPromoSpricePatch(val) {
            const n = Number(val);
            const patch = { SPRICE: val, sprice: val };
            // Keep S PRC visible even when it equals listing price (ebay formatter hides matches)
            if (isFinite(n) && n > 0) patch.has_custom_sprice = true;
            return patch;
        }
        function chPromoStdBase(d) {
            const raw = d && (d.STANDARD_PRICE != null && d.STANDARD_PRICE !== ''
                ? d.STANDARD_PRICE
                : d.standard_price);
            const std = Number(raw);
            if (isFinite(std) && std > 0) return chPromoRound2(std);
            // AliExpress Std is Amazon's shared list — never fall back to live AE Price.
            if (chPromoIsEbayChannel() || CHANNEL_PROMO_CHANNEL === 'aliexpress') return 0;
            const price = chPromoPrice(d);
            return price > 0 ? chPromoRound2(price) : 0;
        }
        let chPromoListingDilCache = null;
        let chPromoListingDilSrc = null;
        function chPromoInvalidateListingDilCache() {
            chPromoListingDilCache = null;
            chPromoListingDilSrc = null;
        }
        function chPromoSkuDil(d) {
            const inv = chPromoInv(d);
            if (inv <= 0) return 0;
            const ovl30 = Number(d && (d.L30 != null ? d.L30 : d['L30']))
                || Number(d && d.ov_l30)
                || Number(d && d.ovl30)
                || 0;
            return (ovl30 / inv) * 100;
        }
        function chPromoListingDilMap() {
            const src = (typeof allTableData !== 'undefined' && Array.isArray(allTableData) && allTableData.length)
                ? allTableData
                : null;
            if (chPromoListingDilCache && chPromoListingDilSrc === src) return chPromoListingDilCache;
            const dataset = chPromoPromoDataset();
            const keyOf = chPromoVariationKeyFn(dataset);
            chPromoListingDilSrc = src;
            chPromoListingDilCache = {
                keyOf: keyOf,
                byKey: chPromoParentDilByKey(dataset, keyOf),
            };
            return chPromoListingDilCache;
        }
        /** Listing Dil = Σ OV L30 ÷ Σ INV by variation item id (same as Dil vs PRMT Apply). */
        function chPromoListingDil(d) {
            if (!d) return 0;
            const cache = chPromoListingDilMap();
            const k = cache.keyOf(d);
            const agg = k ? cache.byKey[k] : null;
            if (agg) return agg.inv > 0 ? agg.dil : 0;
            return chPromoSkuDil(d);
        }
        function chPromoDil(d) {
            const inv = chPromoInv(d);
            // eBay Dil vs PRMT uses listing Dil so the Dil column and Apply slab match
            if (String(CHANNEL_PROMO_CHANNEL).indexOf('ebay') === 0) {
                return chPromoListingDil(d);
            }
            // Temu Dil column = (OV L30 / INV) × 100 — already stored as dil_percent
            if (CHANNEL_PROMO_CHANNEL === 'temu' || CHANNEL_PROMO_CHANNEL === 'temu2') {
                let dil = Number(d.dil_percent);
                if (isFinite(dil)) return dil;
                if (inv <= 0) return 0;
                const ovl30 = Number(d.ovl30 != null ? d.ovl30 : d.L30) || 0;
                return (ovl30 / inv) * 100;
            }
            // AliExpress / Shein / Wayfair: dil_percent is already 0–100 (e.g. 1.5 = 1.5%).
            // Do NOT ×100 — that maps low Dil into the 24%+ slab and forces PRMT% = 0.
            if (CHANNEL_PROMO_CHANNEL === 'aliexpress' || CHANNEL_PROMO_CHANNEL === 'shein'
                || CHANNEL_PROMO_CHANNEL === 'wayfair') {
                let dil = Number(d.dil_percent != null ? d.dil_percent : d[chPromoCfg.dilField]);
                if (isFinite(dil)) return dil;
                if (inv <= 0) return 0;
                const ovl30 = Number(d.ov_l30 != null ? d.ov_l30 : (d.ovl30 != null ? d.ovl30 : d.L30)) || 0;
                return (ovl30 / inv) * 100;
            }
            // TikTok Dil = (OV L30 / INV) × 100 — same as Amazon / the Dil column.
            if (CHANNEL_PROMO_CHANNEL === 'tiktok' || CHANNEL_PROMO_CHANNEL === 'tiktok2') {
                return chPromoSkuDil(d);
            }
            // Shopify Dil column = (OV L30 / INV) × 100 — already stored as DIL%
            if (CHANNEL_PROMO_CHANNEL === 'shopify_b2c' || CHANNEL_PROMO_CHANNEL === 'shopify_b2b') {
                let dil = Number(d['DIL%'] != null ? d['DIL%'] : d[chPromoCfg.dilField]);
                if (isFinite(dil)) return dil;
                if (inv <= 0) return 0;
                const ovl30 = Number(d.L30 != null ? d.L30 : d['L30']) || 0;
                return (ovl30 / inv) * 100;
            }
            // Reverb Dil column = (L30 / INV) × 100 — already stored as RV Dil% (0–100)
            if (CHANNEL_PROMO_CHANNEL === 'reverb') {
                let dil = Number(d['RV Dil%']);
                if (isFinite(dil)) return dil;
                if (inv <= 0) return 0;
                const l30 = Number(d.L30) || 0;
                return (l30 / inv) * 100;
            }
            // Macys / Best Buy Dil column = (OV L30 / INV) × 100
            if (CHANNEL_PROMO_CHANNEL === 'macys' || CHANNEL_PROMO_CHANNEL === 'macy'
                || CHANNEL_PROMO_CHANNEL === 'bestbuy') {
                if (inv <= 0) return 0;
                const ovl30 = Number(d['L30'] != null ? d['L30'] : d.L30) || 0;
                return (ovl30 / inv) * 100;
            }
            let dil = Number(d[chPromoCfg.dilField]);
            if (!isFinite(dil)) {
                const l30 = Number(d['eBay L30'] || d.L30 || d['MC L30'] || d['W_L30'] || d['B2B L30'] || 0) || 0;
                dil = inv > 0 ? (l30 / inv) : 0;
            }
            if (dil > 0 && dil <= 2) dil = dil * 100;
            return dil;
        }
        function chPromoIsEbayChannel() {
            return String(CHANNEL_PROMO_CHANNEL).indexOf('ebay') === 0;
        }
        function chPromoUsesAmazonDilPrmtSlabs() {
            return !!CH_PEF_USES_AMAZON_DIL_SLABS;
        }
        /** Live PRMT% from Dil slabs (eBay / AE / Shein / TikTok Amazon map). */
        function chPromoUsesLiveDilPrmtSlabs() {
            return chPromoIsEbayChannel()
                || CHANNEL_PROMO_CHANNEL === 'aliexpress'
                || CHANNEL_PROMO_CHANNEL === 'shein'
                || chPromoUsesAmazonDilPrmtSlabs();
        }
        function chPromoHasSaleQty(d) {
            if (chPromoIsEbayChannel()) return chPromoEbayListingSaleQty(d) > 0;
            if (CHANNEL_PROMO_CHANNEL === 'aliexpress' || CHANNEL_PROMO_CHANNEL === 'shein') {
                return (Number(d && (d.al30 != null ? d.al30 : d.AL30)) || 0) > 0;
            }
            const f = chPromoCfg.soldField;
            if (f) return (Number(d && d[f]) || 0) > 0;
            return true;
        }
        function chPromoSaleGateLabel() {
            if (chPromoIsEbayChannel()) return 'eBay sale (E L30) > 0';
            if (chPromoCfg.soldField) return chPromoSoldFieldLabel() + ' > 0';
            return '';
        }
        function chPromoLivePrice(d) {
            return chPromoRound2(chPromoPrice(d));
        }
        function chPromoOvl30(d) {
            return Number(d && (d.L30 != null ? d.L30 : d['eBay L30']))
                || Number(d && d.ov_l30)
                || Number(d && d.ovl30)
                || 0;
        }
        function chPromoParentName(d) {
            return String((d && d.Parent) || '').trim().replace(/^PARENT\s+/i, '').trim();
        }
        /** Variation listing key: shared eBay item_id, else Parent. Same sale hits every child. */
        function chPromoVariationKeyFn(dataset) {
            const parentToItem = {};
            (dataset || []).forEach(function(d) {
                if (!d || !chPromoIsChildRow(d)) return;
                const parent = chPromoParentName(d).toUpperCase();
                const itemId = String(d.eBay_item_id || d.item_id || d.ebay_item_id || '').trim();
                if (parent && itemId && itemId !== '0') parentToItem[parent] = parentToItem[parent] || itemId;
            });
            return function keyOf(d) {
                if (!d) return '';
                const itemId = String(d.eBay_item_id || d.item_id || d.ebay_item_id || '').trim();
                if (itemId && itemId !== '0') return 'item:' + itemId;
                const parent = chPromoParentName(d).toUpperCase();
                if (parent && parentToItem[parent]) return 'item:' + parentToItem[parent];
                if (parent) return 'parent:' + parent;
                const sku = chPromoSku(d);
                return sku ? 'sku:' + chPromoSkuKey(sku) : '';
            };
        }
        function chPromoFlattenPromoRows(rows) {
            const out = [];
            const seen = new Set();
            function walk(d) {
                if (!d || typeof d !== 'object') return;
                const sku = chPromoSkuKey(chPromoSku(d));
                if (sku) {
                    if (seen.has(sku)) {
                        const kids = d._children;
                        if (Array.isArray(kids)) kids.forEach(walk);
                        return;
                    }
                    seen.add(sku);
                }
                out.push(d);
                const kids = d._children;
                if (Array.isArray(kids)) kids.forEach(walk);
            }
            (rows || []).forEach(walk);
            return out;
        }
        function chPromoPromoDataset() {
            let raw;
            if (typeof allTableData !== 'undefined' && Array.isArray(allTableData) && allTableData.length) {
                raw = allTableData;
            } else {
                raw = [];
                chPromoEachTableRow(function(row, d) { raw.push(d); });
            }
            return chPromoFlattenPromoRows(raw);
        }
        /** Parent/listing Dil = Σ OV L30 / Σ INV (same formula as the Dil column). */
        function chPromoParentDilByKey(dataset, keyOf) {
            const sums = {};
            (dataset || []).forEach(function(d) {
                if (!d || !chPromoIsChildRow(d)) return;
                const key = keyOf(d);
                if (!key) return;
                if (!sums[key]) sums[key] = { inv: 0, l30: 0 };
                sums[key].inv += chPromoInv(d);
                sums[key].l30 += chPromoOvl30(d);
            });
            const out = {};
            Object.keys(sums).forEach(function(key) {
                const inv = sums[key].inv;
                out[key] = { dil: inv > 0 ? (sums[key].l30 / inv) * 100 : 0, inv: inv };
            });
            return out;
        }
        function chPromoCvr(d) {
            const f = chPromoCfg.cvrField;
            let cvr = Number(d[f]);
            if (isFinite(cvr) && cvr >= 0) return cvr;
            cvr = Number(d.CVR_L30);
            if (isFinite(cvr) && cvr >= 0) return cvr;
            cvr = Number(d['CVR%']);
            if (isFinite(cvr) && cvr >= 0) return cvr;
            cvr = Number(d.SCVR);
            if (isFinite(cvr) && cvr >= 0) return cvr;
            cvr = Number(d.CVR);
            if (isFinite(cvr) && cvr >= 0) return cvr;
            const views = Number(d.Views || d.Sess30 || d.views || d.t_views || 0) || 0;
            const l30 = Number(d['RV L30'] || d['eBay L30'] || d['B2B L30'] || d['MC L30'] || d['W_L30'] || d['TT L30'] || d.L30 || 0) || 0;
            if (views > 0) return chPromoRound2((l30 / views) * 100);
            const sold = Number((chPromoCfg.soldField && d[chPromoCfg.soldField]) || d.al30 || d.sold || 0) || 0;
            const ov = chPromoOvl30(d);
            return ov > 0 ? chPromoRound2((sold / ov) * 100) : 0;
        }
        function parseChPromoPercentAmount(raw) {
            const s = String(raw == null ? '' : raw).trim();
            if (!s) return null;
            const num = parseFloat(s.replace(/[%$,\s]/g, '').replace(',', '.'));
            if (!isFinite(num) || num === 0) return null;
            return { type: 'percent', value: Math.abs(num) };
        }
        function parseChPromoPercentAllowZero(raw) {
            const s = String(raw == null ? '' : raw).trim();
            if (s === '') return null;
            const num = parseFloat(s.replace(/[%$,\s]/g, '').replace(',', '.'));
            if (!isFinite(num) || num < 0) return null;
            return { type: 'percent', value: Math.abs(num) };
        }
        function chPromoTemuSpriceFromStdPrmtCpn(d, overrides) {
            const std = chPromoStdBase(d);
            if (!(std > 0)) return null;
            const prmt = overrides && overrides.prmt != null
                ? Math.max(0, Number(overrides.prmt) || 0)
                : Math.max(0, Number(d.prmt_pct != null ? d.prmt_pct : d._prmt_pct_applied) || 0);
            const cpn = overrides && overrides.cpn != null
                ? Math.max(0, Number(overrides.cpn) || 0)
                : Math.max(0, Number(d.cpn_pct != null ? d.cpn_pct : d._cpn_pct_applied) || 0);
            const adj = (typeof computeCvrUpDnPct === 'function') ? (Number(computeCvrUpDnPct(d)) || 0) : 0;
            const totalDisc = Math.min(99.99, Math.max(0, prmt + cpn + adj));
            const price = totalDisc > 0
                ? chPromoRound2(std * (1 - (totalDisc / 100)))
                : chPromoRound2(std);
            return price >= 0.01 ? price : null;
        }
        function chPromoReverbComboEnabled() {
            return !!CHANNEL_PROMO_SHOW_ZERO_SOLD_RULES;
        }
        /** S PRC = Std × (1 − (PRMT% + CPN%)/100) — Temu and Reverb coupon. */
        function chPromoPrmtCpnComboEnabled() {
            return CHANNEL_PROMO_CHANNEL === 'temu'
                || CHANNEL_PROMO_CHANNEL === 'temu2'
                || (CHANNEL_PROMO_CHANNEL === 'reverb' && !CHANNEL_PROMO_HIDE_CVR_CPN);
        }
        function chPromoZeroSoldPrmtInt(d) {
            return Math.max(0, Number(d && (d.zero_sold_prmt != null && d.zero_sold_prmt !== ''
                ? d.zero_sold_prmt : d._zero_sold_prmt_applied)) || 0);
        }
        function chPromoKeepZeroSoldPrcSprice(d) {
            return !!(d && (d.ZERO_SOLD_PRC_APPLIED === true || d.ZERO_SOLD_PRC_APPLIED === 1
                || d.ZERO_SOLD_PRC_APPLIED === '1' || d.ZERO_SOLD_PRC_APPLIED === 'true'));
        }
        function chPromoReverbSpriceFromStdBothPrmt(d, overrides) {
            const std = chPromoStdBase(d);
            if (!(std > 0)) return null;
            const prmt = overrides && overrides.prmt != null
                ? Math.max(0, Number(overrides.prmt) || 0)
                : Math.max(0, Number(d.prmt_pct != null ? d.prmt_pct : d._prmt_pct_applied) || 0);
            const zeroSold = overrides && overrides.zeroSold != null
                ? Math.max(0, Number(overrides.zeroSold) || 0)
                : chPromoZeroSoldPrmtInt(d);
            const totalDisc = Math.min(99.99, prmt + zeroSold);
            const price = totalDisc > 0
                ? chPromoRound2(std * (1 - (totalDisc / 100)))
                : chPromoRound2(std);
            return price >= 0.01 ? price : null;
        }
        function applyChPromoToSpriceBase(base, promo) {
            if (!(base > 0) || !promo) return null;
            const next = base * (1 - (promo.value / 100));
            return Math.max(0.01, chPromoRound2(next));
        }
        function getChPromoDiscountBase(d, appliedKey) {
            let base = chPromoGetSprice(d) > 0 ? chPromoGetSprice(d) : chPromoPrice(d);
            const prev = Number(d[appliedKey] || 0) || 0;
            if (prev > 0 && prev < 100) base = base / (1 - (prev / 100));
            return chPromoRound2(base);
        }
        function fmtChPromoCell(value, placeholder) {
            if (value === null || value === undefined || value === '') {
                return '<span class="ch-pef-promo-cell">' + placeholder + '</span>';
            }
            return '<span class="ch-pef-promo-cell has-val">' + String(value) + '</span>';
        }
        function chPromoEscAttr(s) {
            if (typeof escAttr === 'function') return escAttr(s);
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }
        function chPromoHistoryDotHtml(sku, metric, pct) {
            if (!sku) return '';
            const n = Number(pct);
            const has = isFinite(n) && n > 0;
            let color = '#adb5bd';
            let label = metric;
            if (metric === 'cpn') { color = has ? '#20c997' : '#adb5bd'; label = 'CPN%'; }
            else if (metric === 'prmt') { color = has ? '#0d6efd' : '#adb5bd'; label = 'PRMT%'; }
            else if (metric === 'push_prc') { color = has ? '#FF9900' : '#adb5bd'; label = 'Push Prc'; }
            const tip = label
                + (has ? (metric === 'push_prc' ? (' = $' + Number(n).toFixed(2)) : (' = ' + n + '%')) : '')
                + ' — click for daily history (PDT)';
            return '<button type="button" class="btn btn-sm p-0 view-sku-chart ch-pef-hist-dot align-middle" '
                + 'data-sku="' + chPromoEscAttr(sku) + '" data-metric="' + chPromoEscAttr(metric) + '" '
                + 'title="' + chPromoEscAttr(tip) + '" '
                + 'style="border:none;background:none;cursor:pointer;padding:0;line-height:1;vertical-align:middle;">'
                + '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;'
                + 'background:' + color + ';flex-shrink:0;"></span></button>';
        }

        function saveChannelPromoFields(sku, fields) {
            const payload = Object.assign({
                channel: CHANNEL_PROMO_CHANNEL,
                sku: sku,
                _token: chPromoCsrf(),
            }, fields || {});
            return $.ajax({
                url: CH_PROMO_SAVE_URL,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: payload,
            });
        }

        function saveChannelSprice(sku, sprice, silent, extra) {
            extra = extra || {};
            const rowData = chPromoRowDataFromExtra(extra);
            const val = rowData ? chPromoCapSpriceToLmp(rowData, sprice) : chPromoRound2(sprice);
            if (!sku || !chPromoCfg.saveSpriceUrl) {
                return $.Deferred().reject().promise();
            }
            let data;
            if (chPromoCfg.saveSpriceMode === 'updates') {
                data = { updates: [{ sku: sku, sprice: val }], _token: chPromoCsrf() };
            } else {
                data = { sku: sku, sprice: val, _token: chPromoCsrf() };
            }
            const queueEnabled = chPromoEbayStdMinusPrmtCpnEnabled()
                && typeof enqueueChannelPushSpriceAfterSave === 'function';
            if (queueEnabled || extra.skip_push) data.skip_push = 1;
            return $.ajax({
                url: chPromoCfg.saveSpriceUrl,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: data,
            }).done(function() {
                let shouldQueue = false;
                if (queueEnabled) {
                    if (extra.queue_push === true) shouldQueue = true;
                    else if (extra.queue_push === false) shouldQueue = false;
                    else shouldQueue = extra.skip_push !== true;
                }
                if (shouldQueue) {
                    enqueueChannelPushSpriceAfterSave(sku, val, extra.row || null);
                }
                if (!silent) chPromoToast('success', shouldQueue ? 'S PRC saved — push queued' : 'S PRC updated');
            }).fail(function() {
                if (!silent) chPromoToast('error', 'Failed to save S PRC');
            });
        }

        /** Persist promo % first (so refresh keeps PRMT/CPN), then optional S PRC. Returns a Promise. */
        function saveChannelSpriceAndPromo(row, sprice, silent, extra) {
            const d = row.getData();
            const sku = chPromoSku(d);
            const val = chPromoCapSpriceToLmp(d, sprice);
            extra = extra || {};
            extra.row = extra.row || row;
            if (!sku) return Promise.resolve(null);
            if (val > 0) {
                row.update(chPromoSpricePatch(val));
            }
            const promoFields = {};
            if (extra.prmt_pct !== undefined && extra.prmt_pct !== null) {
                promoFields.prmt_pct = Number(extra.prmt_pct) || 0;
            }
            if (extra.zero_sold_prmt !== undefined && extra.zero_sold_prmt !== null) {
                promoFields.zero_sold_prmt = Number(extra.zero_sold_prmt) || 0;
            }
            if (extra.cpn_pct !== undefined && extra.cpn_pct !== null) {
                promoFields.cpn_pct = Number(extra.cpn_pct) || 0;
            }
            if (extra.dsc !== undefined && extra.dsc !== null) {
                promoFields.dsc = Number(extra.dsc) || 0;
            }
            if (extra.appr !== undefined) {
                promoFields.appr = extra.appr ? 1 : 0;
            }

            const applyPromoPres = function(pres, updates) {
                if (pres) {
                    if (pres.prmt_pct !== undefined && pres.prmt_pct !== null) {
                        updates.prmt_pct = String(pres.prmt_pct);
                        updates._prmt_pct_applied = Number(pres.prmt_pct) || 0;
                        updates.PEF_PRMT_PCT = Number(pres.prmt_pct) || 0;
                    }
                    if (pres.zero_sold_prmt !== undefined && pres.zero_sold_prmt !== null) {
                        updates.zero_sold_prmt = String(pres.zero_sold_prmt);
                        updates._zero_sold_prmt_applied = Number(pres.zero_sold_prmt) || 0;
                    }
                    if (pres.cpn_pct !== undefined && pres.cpn_pct !== null) {
                        updates.cpn_pct = String(pres.cpn_pct);
                        updates._cpn_pct_applied = Number(pres.cpn_pct) || 0;
                        updates.PEF_CPN_PCT = Number(pres.cpn_pct) || 0;
                    }
                    if (pres.dsc !== undefined && pres.dsc !== null) {
                        updates.dsc = String(pres.dsc);
                        updates._dsc_applied = Number(pres.dsc) || 0;
                    }
                    if (pres.appr !== undefined) updates.appr = !!pres.appr;
                } else {
                    if (promoFields.prmt_pct !== undefined) {
                        updates.prmt_pct = String(promoFields.prmt_pct);
                        updates._prmt_pct_applied = Number(promoFields.prmt_pct) || 0;
                        updates.PEF_PRMT_PCT = Number(promoFields.prmt_pct) || 0;
                    }
                    if (promoFields.zero_sold_prmt !== undefined) {
                        updates.zero_sold_prmt = String(promoFields.zero_sold_prmt);
                        updates._zero_sold_prmt_applied = Number(promoFields.zero_sold_prmt) || 0;
                    }
                    if (promoFields.cpn_pct !== undefined) {
                        updates.cpn_pct = String(promoFields.cpn_pct);
                        updates._cpn_pct_applied = Number(promoFields.cpn_pct) || 0;
                        updates.PEF_CPN_PCT = Number(promoFields.cpn_pct) || 0;
                    }
                    if (promoFields.dsc !== undefined) {
                        updates.dsc = String(promoFields.dsc);
                        updates._dsc_applied = Number(promoFields.dsc) || 0;
                    }
                    if (promoFields.appr !== undefined) updates.appr = !!promoFields.appr;
                }
            };

            return new Promise(function(resolve) {
                const afterPromo = function(pres) {
                    const updates = {};
                    applyPromoPres(pres, updates);
                    const finish = function(response) {
                        if (response) {
                            if (response.data !== undefined) Object.assign(updates, chPromoSpricePatch(response.data));
                            if (response.sgpft_percent !== undefined) updates.SGPFT = response.sgpft_percent;
                            if (response.spft_percent !== undefined) {
                                updates['Spft%'] = response.spft_percent;
                                updates.SPFT = response.spft_percent;
                                updates.SNPFT = response.spft_percent;
                            }
                            if (response.sroi_percent !== undefined) updates.SROI = response.sroi_percent;
                            if (response.sgroi_percent !== undefined) updates.SGROI = response.sgroi_percent;
                            if (response.snroi_percent !== undefined) updates.SNROI = response.snroi_percent;
                            if (response.sroi_percent !== undefined) updates.sroi_percent = response.sroi_percent;
                            if (response.sgprft_percent !== undefined) updates.sgprft_percent = response.sgprft_percent;
                            if (response.sgpft_percent !== undefined && updates.sgprft_percent == null) {
                                updates.sgprft_percent = response.sgpft_percent;
                            }
                            if (response.spft_percent !== undefined) updates.spft_percent = response.spft_percent;
                        }
                        if (Object.keys(updates).length) row.update(updates);
                        if (typeof applyTemuSpriceRelatedToRow === 'function' && val > 0) {
                            applyTemuSpriceRelatedToRow(row, val, response);
                        } else {
                            try { row.reformat(); } catch (e) { /* ignore */ }
                        }
                        if (!silent) chPromoToast('success', 'S PRC / promo updated');
                        resolve(pres || response || null);
                    };
                    if (val > 0 && chPromoCfg.saveSpriceUrl) {
                        saveChannelSprice(sku, val, true, extra).done(finish).fail(function() {
                            if (!silent) chPromoToast('error', 'Failed to save S PRC');
                            finish(null);
                        });
                    } else {
                        finish(null);
                    }
                };

                if (Object.keys(promoFields).length) {
                    saveChannelPromoFields(sku, promoFields)
                        .done(function(pres) { afterPromo(pres); })
                        .fail(function() {
                            if (!silent) chPromoToast('error', 'Promo save failed');
                            // Still keep UI values and try S PRC
                            afterPromo(null);
                        });
                } else if (val > 0 && chPromoCfg.saveSpriceUrl) {
                    afterPromo(null);
                } else {
                    resolve(null);
                }
            });
        }

        function collectChPromoSelectedRows() {
            if (typeof table === 'undefined' || !table) return [];
            // Merge selectedSkus + selectedRows + checked DOM boxes (eBay uses selectedSkus;
            // Amazon aliases both). Match case-insensitively like other ebay bulk actions.
            const keys = new Set();
            function addSku(s) {
                const k = chPromoSkuKey(s);
                if (k) keys.add(k);
            }
            if (typeof selectedSkus !== 'undefined' && selectedSkus && selectedSkus.forEach) {
                selectedSkus.forEach(addSku);
            }
            if (typeof selectedRows !== 'undefined' && selectedRows && selectedRows.forEach) {
                selectedRows.forEach(addSku);
            }
            try {
                document.querySelectorAll('.sku-select-checkbox:checked, .ae-sku-chk:checked, .shein-sku-chk:checked').forEach(function(el) {
                    addSku(el.getAttribute('data-sku') || (el.dataset && el.dataset.sku) || '');
                });
            } catch (e) { /* ignore */ }
            if (!keys.size) return [];
            return table.getRows().filter(function(row) {
                const d = row.getData();
                return chPromoIsChildRow(d) && keys.has(chPromoSkuKey(chPromoSku(d)));
            }).map(function(row) {
                return { row: row, d: row.getData() };
            });
        }
        function collectChPromoSelectedParentRows() {
            if (typeof table === 'undefined' || !table) return [];
            const keys = new Set();
            function addSku(s) {
                const k = chPromoSkuKey(s);
                if (k) keys.add(k);
            }
            if (typeof selectedSkus !== 'undefined' && selectedSkus && selectedSkus.forEach) {
                selectedSkus.forEach(addSku);
            }
            try {
                document.querySelectorAll('.sku-select-checkbox:checked').forEach(function(el) {
                    addSku(el.getAttribute('data-sku') || (el.dataset && el.dataset.sku) || '');
                });
            } catch (e) { /* ignore */ }
            if (!keys.size) return [];
            const out = [];
            chPromoEachTableRow(function(row, d) {
                if (!chPromoIsParentRow(d)) return;
                const sku = chPromoSkuKey(chPromoSku(d));
                const parent = chPromoSkuKey(chPromoParentName(d));
                if (keys.has(sku) || (parent && (keys.has(parent) || keys.has('PARENT ' + parent)))) {
                    out.push({ row: row, d: d });
                }
            });
            return out;
        }
        function collectChPromoVisibleRows() {
            if (typeof table === 'undefined' || !table) return [];
            const out = [];
            const seen = new Set();
            function addRow(row) {
                if (!row || typeof row.getData !== 'function') return;
                const d = row.getData();
                if (chPromoIsChildRow(d)) {
                    const k = chPromoSkuKey(chPromoSku(d));
                    if (k && !seen.has(k)) {
                        seen.add(k);
                        out.push({ row: row, d: d });
                    }
                }
                if (typeof row.getTreeChildren === 'function') {
                    const kids = row.getTreeChildren() || [];
                    for (let i = 0; i < kids.length; i++) addRow(kids[i]);
                }
            }
            const roots = table.getRows('active') || [];
            for (let i = 0; i < roots.length; i++) addRow(roots[i]);
            // View=Parent hides children. Apply still needs those SKUs.
            if (!out.length) {
                const visibleParents = new Set();
                for (let i = 0; i < roots.length; i++) {
                    const d = roots[i] && typeof roots[i].getData === 'function' ? roots[i].getData() : null;
                    if (!d || !chPromoIsParentRow(d)) continue;
                    const p = chPromoParentName(d).toUpperCase();
                    if (p) visibleParents.add(p);
                }
                if (visibleParents.size) {
                    chPromoEachTableRow(function(row, d) {
                        if (!chPromoIsChildRow(d)) return;
                        const p = chPromoParentName(d).toUpperCase();
                        if (p && visibleParents.has(p)) addRow(row);
                    });
                }
            }
            return out;
        }

        function chPromoDilSlabKey(dil) {
            const n = Number(dil);
            if (CH_PEF_USES_REVERB_SLABS) {
                if (!isFinite(n) || n < 0) {
                    return CHANNEL_PROMO_CHANNEL === 'reverb' ? 'lt-0.1' : '0-20';
                }
                if (n > 100) return 'gt-100';
                if (n >= 80) return '80-100';
                if (n >= 60) return '60-80';
                if (n >= 40) return '40-60';
                if (n >= 20) return '20-40';
                // Reverb first slab starts at 0.1% — Dil 0% does not get PRMT.
                if (CHANNEL_PROMO_CHANNEL === 'reverb' && n < 0.1) return 'lt-0.1';
                return '0-20';
            }
            if (CH_PEF_USES_EBAY_FINE_DIL) {
                if (typeof CH_PEF_SKIP_DIL_EQ_ZERO !== 'undefined' && CH_PEF_SKIP_DIL_EQ_ZERO) {
                    if (!isFinite(n) || n <= 2) return '0.1-2';
                } else if (!isFinite(n) || n <= 0) {
                    return 'eq-0';
                }
                if (n <= 2) return '0.1-2';
                if (n <= 4) return '2-4';
                if (n <= 6) return '4-6';
                if (n <= 8) return '6-8';
                if (n <= 10) return '8-10';
                if (n <= 12) return '10-12';
                if (n <= 14) return '12-14';
                if (n <= 16) return '14-16';
                if (n <= 18) return '16-18';
                if (n <= 20) return '18-20';
                if (n <= 22) return '20-22';
                if (n <= 24) return '22-24';
                return '24-26';
            }
            if (!isFinite(n) || n < 0) return '0-10';
            if (n > 100) return 'gt-100';
            if (n >= 90) return '90-100';
            if (n >= 80) return '80-90';
            if (n >= 70) return '70-80';
            if (n >= 60) return '60-70';
            if (n >= 50) return '50-60';
            if (n >= 40) return '40-50';
            if (n >= 30) return '30-40';
            if (n >= 20) return '20-30';
            if (n >= 10) return '10-20';
            return '0-10';
        }
        function chPromoPrmtForRuleKey(key) {
            const rule = chPromoDilPrmtRules.find(function(r) { return r.key === key; });
            if (!rule) return 0;
            const n = Number(rule.prmt);
            return isFinite(n) && n >= 0 ? n : 0;
        }
        function chPromoPrmtForDil(dil) {
            return chPromoPrmtForRuleKey(chPromoDilSlabKey(dil));
        }
        /** Canonical B2B discount from Dil% (0–0 → 12, 0.1–2 → 11, … 22%+ → 0). Independent of saved PRMT. */
        function chPromoB2bDiscDefaultForKey(key) {
            const def = CH_PEF_DIL_PRMT_DEFAULTS_EBAY.find(function(r) { return r.key === key; });
            return def ? Math.max(0, Number(def.prmt) || 0) : 0;
        }
        function chPromoB2bDiscForDil(dil) {
            return chPromoB2bDiscDefaultForKey(chPromoDilSlabKey(dil));
        }
        function chPromoB2bDiscForRow(d) {
            if (!d || d.is_parent_summary || !chPromoIsChildRow(d)) return 0;
            if (chPromoInv(d) === 0) return 0;
            return chPromoB2bDiscForDil(chPromoDil(d));
        }
        /** PRMT% the Dil vs PRMT slabs produce for this row (listing Dil on eBay). */
        function chPromoEbaySlabPrmt(d) {
            if (!d || d.is_parent_summary) return null;
            if (!chPromoIsChildRow(d)) return null;
            if (chPromoInv(d) === 0) return 0;
            if (CHANNEL_PROMO_CHANNEL === 'aliexpress'
                && typeof chPromoHasSaleQty === 'function'
                && !chPromoHasSaleQty(d)) {
                return null;
            }
            return chPromoPrmtForDil(chPromoDil(d));
        }
        function chPromoSyncEbayPrmtColumnFromSlabs() {
            // Live-fill PRMT% from Dil slabs (no row selection required).
            if (!chPromoUsesLiveDilPrmtSlabs()) {
                return;
            }
            if (typeof table === 'undefined' || !table) return;
            chPromoInvalidateListingDilCache();
            const blocked = typeof table.blockRedraw === 'function';
            if (blocked) table.blockRedraw();
            try {
                chPromoEachTableRow(function(row, d) {
                    if (!chPromoIsChildRow(d)) return;
                    if (CHANNEL_PROMO_CHANNEL === 'aliexpress'
                        && typeof chPromoHasSaleQty === 'function'
                        && !chPromoHasSaleQty(d)) {
                        if (typeof chPromoKeepZeroSoldPrcSprice === 'function' && chPromoKeepZeroSoldPrcSprice(d)) {
                            return;
                        }
                        const leftover = Number(d.prmt_pct != null && d.prmt_pct !== ''
                            ? d.prmt_pct : d._prmt_pct_applied);
                        if (isFinite(leftover) && leftover > 0) {
                            row.update({
                                prmt_pct: '0',
                                _prmt_pct_applied: 0,
                                PEF_PRMT_PCT: 0,
                            });
                        }
                        return;
                    }
                    const prmt = chPromoEbaySlabPrmt(d);
                    if (prmt == null) return;
                    const current = Number(d.prmt_pct != null && d.prmt_pct !== ''
                        ? d.prmt_pct : d._prmt_pct_applied);
                    if (isFinite(current) && current === prmt) return;
                    row.update({ prmt_pct: String(prmt), _prmt_pct_applied: prmt });
                });
            } finally {
                if (blocked) table.restoreRedraw();
            }
            try { table.redraw(true); } catch (e) { /* ignore */ }
        }
        function chPromoDilColorBand(dil) {
            const n = Number(dil);
            if (!isFinite(n) || n < 25) return 'red';
            if (n < 50) return 'green';
            return 'pink';
        }
        function chPromoDilColorHex(band) {
            if (band === 'red') return '#a00211';
            if (band === 'green') return '#28a745';
            if (band === 'pink') return '#e83e8c';
            return '#6c757d';
        }
        function chPromoSoldFieldLabel() {
            return chPromoCfg.soldFieldLabel || chPromoCfg.soldField || 'L30';
        }
        /** eBay units sold (E L30). Dil vs PRMT / CVR vs CPN Apply only write SKUs with sale > 0. */
        function chPromoEbaySaleQty(d) {
            return Number(d && (d['eBay L30'] != null ? d['eBay L30'] : (d.ebay_l30 || d['E L30']))) || 0;
        }
        /**
         * Listing-level E L30: own qty, else max among the same eBay item_id / Parent.
         * Variation children often have 0 on the SKU row while the listing sold.
         */
        function chPromoEbayListingSaleQty(d) {
            const own = chPromoEbaySaleQty(d);
            if (own > 0) return own;
            const itemId = String((d && (d.eBay_item_id || d.item_id || d.ebay_item_id)) || '').trim();
            const parent = chPromoParentName(d).toUpperCase();
            if (!itemId && !parent) return 0;
            let max = 0;
            const dataset = chPromoPromoDataset();
            for (let i = 0; i < dataset.length; i++) {
                const row = dataset[i];
                if (!row) continue;
                const sameItem = itemId && itemId !== '0'
                    && String(row.eBay_item_id || row.item_id || row.ebay_item_id || '').trim() === itemId;
                const sameParent = parent && chPromoParentName(row).toUpperCase() === parent;
                if (!sameItem && !sameParent) continue;
                const q = chPromoEbaySaleQty(row);
                if (q > max) max = q;
            }
            return max;
        }
        function chPromoLp(d) {
            const lp = Number(d && (
                d.LP_productmaster != null ? d.LP_productmaster
                : (d.LP != null ? d.LP : d.lp)
            ));
            return (isFinite(lp) && lp > 0) ? lp : 0;
        }
        function chPromoShipCost(d) {
            const raw = d && (
                d.Ship_productmaster != null && d.Ship_productmaster !== ''
                    ? d.Ship_productmaster
                    : (d.ebay2_ship != null
                        ? d.ebay2_ship
                        : (d.Ship != null ? d.Ship : d.ship))
            );
            const n = Number(raw);
            return isFinite(n) && n > 0 ? n : 0;
        }
        function chPromoTakehomeMargin(d) {
            let raw = Number(d && d._margin);
            if (isFinite(raw) && raw > 0) return raw > 1 ? (raw / 100) : raw;
            raw = Number(d && d.percentage);
            if (isFinite(raw) && raw > 0) return raw > 1 ? (raw / 100) : raw;
            if (typeof EBAY2_TAKEHOME !== 'undefined') {
                const t = Number(EBAY2_TAKEHOME);
                if (isFinite(t) && t > 0) return t > 1 ? (t / 100) : t;
            }
            return 1;
        }
        function chPromoAdsFrac() {
            if (typeof EBAY2_CHANNEL_ADS_PCT !== 'undefined') {
                return (parseFloat(EBAY2_CHANNEL_ADS_PCT) || 0) / 100;
            }
            return 0;
        }
        function chPromoRoiForZeroSoldDil(dil) {
            const key = CHANNEL_PROMO_ZERO_SOLD_DIL_COLOR_SLABS
                ? chPromoDilColorBand(dil)
                : chPromoDilSlabKey(dil);
            const rule = chPromoZeroSoldDilRules.find(function(r) { return r.key === key; });
            const n = rule ? Number(rule.groi) : 0;
            return isFinite(n) ? n : 0;
        }
        /** GROI back-solve: (sprice×margin − ship − lp) / lp × 100 = Target. */
        function chPromoSpriceFromTargetRoi(d, roiPct) {
            const lp = chPromoLp(d);
            if (!(lp > 0)) return 0;
            const margin = chPromoTakehomeMargin(d);
            if (!(margin > 0)) return 0;
            const roi = isFinite(Number(roiPct)) ? Number(roiPct) : 0;
            const price = (lp * (1 + roi / 100) + chPromoShipCost(d)) / margin;
            return (isFinite(price) && price > 0) ? chPromoRound2(price) : 0;
        }
        function chPromoPatchDatasetSprice(sku, updates) {
            const key = chPromoSkuKey(sku);
            if (!key || !updates) return;
            const extra = (typeof window !== 'undefined' && Array.isArray(window.allTableData))
                ? window.allTableData
                : ((typeof allTableData !== 'undefined' && Array.isArray(allTableData)) ? allTableData : []);
            extra.forEach(function(r) {
                if (!r) return;
                if (chPromoSkuKey(chPromoSku(r)) === key) Object.assign(r, updates);
            });
        }
        function chPromoApplySpriceSavePatch(row, fill, saveRes, sku) {
            const updates = Object.assign({}, chPromoSpricePatch(fill));
            if (saveRes && (saveRes.sgpft_percent !== undefined || saveRes.sroi_percent !== undefined
                || saveRes.snroi_percent !== undefined)) {
                updates.SGPFT = saveRes.sgpft_percent;
                updates['Spft%'] = saveRes.spft_percent;
                updates.SPFT = saveRes.spft_percent;
                updates.SNPFT = saveRes.spft_percent;
                updates.SROI = saveRes.sroi_percent;
                updates.sroi = saveRes.sroi_percent;
                updates.SGROI = saveRes.sgroi_percent;
                updates.SNROI = saveRes.snroi_percent != null ? saveRes.snroi_percent : saveRes.sroi_percent;
                updates.sroi_percent = saveRes.sroi_percent;
                if (saveRes.sgpft_percent !== undefined) {
                    updates.sgpft = saveRes.sgpft_percent;
                }
                updates.sgprft_percent = saveRes.sgprft_percent != null ? saveRes.sgprft_percent : saveRes.sgpft_percent;
                updates.spft_percent = saveRes.spft_percent;
            }
            if (saveRes && saveRes.ebay_price != null) {
                const live = parseFloat(saveRes.ebay_price);
                if (isFinite(live) && live > 0) {
                    updates['eBay Price'] = live;
                }
            }
            // Local cannot revise eBay. Match Price to the saved calc so the blue
            // triangle can clear on this page (production still uses live Price).
            if (window._chPushSpriceLiveAllowed === false && fill > 0 && updates['eBay Price'] == null) {
                updates['eBay Price'] = fill;
            }
            if (saveRes && saveRes.SPRICE_STATUS) {
                updates.SPRICE_STATUS = saveRes.SPRICE_STATUS;
            } else if (saveRes && saveRes.price_push_success) {
                updates.SPRICE_STATUS = 'pushed';
            } else if (window._chPushSpriceLiveAllowed === false && fill > 0) {
                updates.SPRICE_STATUS = 'saved';
            }
            if (fill > 0 && updates.sroi == null && updates.SROI == null && row && typeof row.getData === 'function') {
                const d = row.getData() || {};
                const lp = chPromoLp(d);
                const ship = chPromoShipCost(d);
                const margin = chPromoTakehomeMargin(d);
                if (lp > 0) {
                    const sroi = Math.round(((fill * margin - lp - ship) / lp) * 100);
                    const sgpft = fill > 0 ? Math.round(((fill * margin - lp - ship) / fill) * 100) : 0;
                    updates.sroi = sroi;
                    updates.SROI = sroi;
                    updates.sgpft = sgpft;
                    updates.SGPFT = sgpft;
                }
            }
            if (row && typeof row.update === 'function') {
                try { row.update(updates); } catch (e) { /* ignore */ }
                try { row.reformat(); } catch (e) { /* ignore */ }
            }
            const skuKey = sku || (row && typeof row.getData === 'function'
                ? chPromoSku(row.getData() || {})
                : '');
            if (skuKey) chPromoPatchDatasetSprice(skuKey, updates);
        }
        function chPromoReverbSoldQty(d) {
            const f = chPromoCfg.soldField;
            if (f) return Number(d && d[f]) || 0;
            return Number(d && (d['RV L30'] != null ? d['RV L30'] : d.RV_L30)) || 0;
        }
        function chPromoIsZeroSoldRow(d) {
            return !!CHANNEL_PROMO_SHOW_ZERO_SOLD_RULES && chPromoReverbSoldQty(d) <= 0;
        }
        function chPromoZeroSoldUsesAmazonPrice() {
            return CHANNEL_PROMO_CHANNEL === 'macys' || CHANNEL_PROMO_CHANNEL === 'macy';
        }
        function chPromoAmazonPrice(d) {
            return Number(d && (d['A Price'] != null ? d['A Price'] : (d.a_price || d.amazon_price))) || 0;
        }
        async function applyChPromoZeroSoldAmazonToTargets(targets, label, opts) {
            opts = opts || {};
            const soldLabel = chPromoSoldFieldLabel();
            const applyTargets = (targets || []).filter(function(item) {
                const d = (item.row && item.row.getData()) || item.d || {};
                return chPromoIsChildRow(d)
                    && chPromoReverbSoldQty(d) <= 0
                    && chPromoInv(d) > 0
                    && chPromoAmazonPrice(d) > 0;
            });
            if (!applyTargets.length) {
                chPromoToast('error', 'No 0 Sold rows (' + soldLabel + ' = 0) with Amazon Price and INV > 0');
                return [];
            }
            const jobs = [];
            const blocked = typeof table !== 'undefined' && table && typeof table.blockRedraw === 'function';
            if (blocked) table.blockRedraw();
            try {
                applyTargets.forEach(function(item) {
                    const d = item.row.getData() || {};
                    const sku = chPromoSku(d);
                    const amazonPrice = chPromoRound2(chPromoAmazonPrice(d));
                    if (!sku || !(amazonPrice > 0)) return;
                    const patch = Object.assign(
                        chPromoSpricePatch(amazonPrice),
                        chPromoGtSoldMetricsPatch(amazonPrice, d),
                        { SPRICE_STATUS: 'applied' }
                    );
                    item.row.update(patch);
                    jobs.push({ row: item.row, sku: sku, price: amazonPrice });
                });
            } finally {
                if (blocked) table.restoreRedraw();
            }
            if (!jobs.length) {
                chPromoToast('error', 'No 0 Sold Amazon prices to apply');
                return [];
            }
            const updates = jobs.map(function(j) { return { sku: j.sku, sprice: j.price }; });
            try {
                if (chPromoCfg.saveSpriceBatchUrl) {
                    await saveChannelSpriceBatch(updates, { skip_push: true });
                } else {
                    await chPromoMapLimit(jobs, 8, async function(job) {
                        await Promise.resolve(saveChannelSprice(job.sku, job.price, true, { skip_push: true }));
                    });
                }
            } catch (e) {
                chPromoToast('error', 'Failed to save S PRC');
                return jobs;
            }
            if (!opts.silentToast) {
                chPromoToast(
                    'success',
                    '0 Sold Rule (' + label + '): Amazon Price → S PRC on ' + jobs.length + ' SKU(s).'
                );
            }
            $('#ch-promo-zero-sold-prmt-status').text('Applied Amazon Price to S PRC for ' + jobs.length + ' SKU(s).');
            if (opts.push && jobs.length) {
                await pushChPromoGtSoldPrices(jobs);
            }
            return jobs;
        }
        async function saveAndApplyChPromoZeroSoldAmazon(opts) {
            opts = opts || {};
            const selected = collectChPromoSelectedRows();
            let targets = selected;
            let label = 'selected';
            if (!targets.length) {
                targets = collectChPromoVisibleRows();
                label = 'all visible';
                if (!targets.length) {
                    chPromoToast('error', 'No rows to apply');
                    return;
                }
                if (!opts.push && !confirm('No rows selected — apply Amazon Price to S PRC for visible 0 Sold row(s)?')) {
                    return;
                }
            }
            if (opts.push) {
                if (!confirm(
                    'Apply Amazon Price to S PRC and push to '
                    + (chPromoCfg.label || CHANNEL_PROMO_CHANNEL)
                    + ' for ' + label + ' 0 Sold SKU(s)?'
                )) return;
            }
            const $btn = $(opts.push
                ? '#ch-promo-zero-sold-amz-push-btn, #ch-promo-push-zero-sold-btn, #ch-promo-zero-sold-menu-btn'
                : '#ch-promo-zero-sold-prmt-apply-btn');
            const html = $btn.first().html();
            $btn.prop('disabled', true);
            $('#ch-promo-zero-sold-prmt-apply-btn').html('<i class="fas fa-spinner fa-spin"></i> Applying…');
            try {
                await applyChPromoZeroSoldAmazonToTargets(targets, label, {
                    silentToast: !!opts.push,
                    push: !!opts.push,
                });
            } catch (xhr) {
                chPromoToast('error', 'Apply failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'));
            } finally {
                $btn.prop('disabled', false);
                $('#ch-promo-zero-sold-prmt-apply-btn').html('Apply');
                $('#ch-promo-zero-sold-amz-push-btn').html('<i class="fas fa-upload me-1"></i> Push');
            }
        }
        function chPromoIsZeroSoldRuleKey(key) {
            return String(key || '').indexOf('0-sold-') === 0;
        }
        function chPromoPrmtForZeroSoldRow(d) {
            if (chPromoInv(d) === 0) return 0;
            return chPromoPrmtForRuleKey('0-sold-' + chPromoDilColorBand(chPromoDil(d)));
        }
        function chPromoPrmtForRow(d) {
            if (chPromoInv(d) === 0) return 0;
            if (chPromoIsZeroSoldRow(d)) return chPromoPrmtForZeroSoldRow(d);
            if (CHANNEL_PROMO_CHANNEL === 'reverb' && chPromoReverbSoldQty(d) <= 0) return 0;
            return chPromoPrmtForDil(chPromoDil(d));
        }
        function chPromoCvrSlabKey(cvr) {
            const n = Number(cvr);
            if (!isFinite(n) || n <= 0) return 'eq-0';
            if (n > 7) return 'gt-7';
            if (n >= 6.5) return '6.5-7';
            if (n >= 6) return '6-6.5';
            if (n >= 5) return '5-6';
            if (n >= 4) return '4-5';
            if (n >= 3) return '3-4';
            if (n >= 2) return '2-3';
            if (n >= 1.5) return '1.5-2';
            if (n >= 1) return '1-1.5';
            return '0.01-1';
        }
        function chPromoCpnForCvr(cvr) {
            const key = chPromoCvrSlabKey(cvr);
            const rule = chPromoCvrCpnRules.find(function(r) { return r.key === key; });
            if (!rule) return 0;
            const n = Number(rule.cpn);
            return isFinite(n) && n >= 0 ? n : 0;
        }

        function cascadeChPromoDilPrmtFromFirst() {
            const $rows = $('#ch-promo-dil-prmt-tbody tr');
            if (!$rows.length) return;
            const firstVal = parseFloat($rows.eq(0).find('.ch-promo-dil-prmt-input').val());
            if (!isFinite(firstVal)) return;
            $rows.each(function(i) {
                const prmt = Math.max(0, firstVal - i);
                const $inp = $(this).find('.ch-promo-dil-prmt-input');
                if (i > 0) $inp.val(prmt);
                const key = String($(this).attr('data-key') || '');
                const rule = chPromoDilPrmtRules.find(function(r) { return r.key === key; });
                if (rule) rule.prmt = prmt;
            });
        }
        /** Any Dil vs PRMT number change: update in-memory slabs and refill PRMT% (no sale event). */
        function chPromoOnDilPrmtNumberChanged(inputEl) {
            const first = $('#ch-promo-dil-prmt-tbody .ch-promo-dil-prmt-input').get(0);
            if (inputEl === first) {
                cascadeChPromoDilPrmtFromFirst();
            } else {
                readChPromoDilPrmtRulesFromModal();
            }
            chPromoSyncEbayPrmtColumnFromSlabs();
        }
        function renderChPromoDilPrmtModalTable() {
            const $tb = $('#ch-promo-dil-prmt-tbody').empty();
            const showB2bDisc = CHANNEL_PROMO_CHANNEL === 'shopify_b2b';
            let visibleIdx = 0;
            chPromoDilPrmtRules.forEach(function(r, idx) {
                if (chPromoIsZeroSoldRuleKey(r.key)) return;
                const prmt = isFinite(Number(r.prmt)) ? Number(r.prmt) : 0;
                const first = visibleIdx === 0;
                const b2bDisc = chPromoB2bDiscDefaultForKey(r.key);
                $tb.append(
                    '<tr data-key="' + String(r.key).replace(/"/g, '&quot;') + '">'
                    + '<td>' + String(r.label || r.key) + '</td>'
                    + (showB2bDisc
                        ? ('<td class="text-end">'
                            + '<input type="number" class="form-control form-control-sm ch-promo-b2b-disc-input" '
                            + 'readonly tabindex="-1" value="' + b2bDisc + '" '
                            + 'title="Auto from Dil% rule: 0–0% → 12, then −1 each 2% slab (min 0)">'
                            + '</td>')
                        : '')
                    + '<td class="text-end">'
                    + '<input type="number" class="form-control form-control-sm ch-promo-dil-prmt-input" '
                    + 'min="0" step="0.1" value="' + prmt + '" data-idx="' + idx + '"'
                    + (first ? ' title="Changing this sets following slabs to −1 each, minimum 0"' : '')
                    + '>'
                    + '</td></tr>'
                );
                visibleIdx++;
            });
        }
        function renderChPromoZeroSoldPrmtModalTable() {
            const $tb = $('#ch-promo-zero-sold-prmt-tbody').empty();
            if (!$tb.length) return;
            const soldColor = {
                '0-sold-red': '#a00211',
                '0-sold-green': '#28a745',
                '0-sold-pink': '#e83e8c',
            };
            chPromoDilPrmtRules.forEach(function(r, idx) {
                if (!chPromoIsZeroSoldRuleKey(r.key)) return;
                const prmt = isFinite(Number(r.prmt)) ? Number(r.prmt) : 0;
                const hex = soldColor[r.key] || '#212529';
                $tb.append(
                    '<tr data-key="' + String(r.key).replace(/"/g, '&quot;') + '">'
                    + '<td><span style="color:' + hex + ';font-weight:600;">' + String(r.label || r.key) + '</span></td>'
                    + '<td class="text-end">'
                    + '<input type="number" class="form-control form-control-sm ch-promo-dil-prmt-input" '
                    + 'min="0" step="0.1" value="' + prmt + '" data-idx="' + idx + '">'
                    + '</td></tr>'
                );
            });
        }
        function readChPromoDilPrmtRulesFromModal() {
            $('#ch-promo-dil-prmt-tbody tr, #ch-promo-zero-sold-prmt-tbody tr').each(function() {
                const key = String($(this).attr('data-key') || '');
                const val = parseFloat($(this).find('.ch-promo-dil-prmt-input').val());
                const rule = chPromoDilPrmtRules.find(function(r) { return r.key === key; });
                if (!rule) return;
                rule.prmt = (isFinite(val) && val >= 0) ? val : 0;
            });
            return chPromoDilPrmtRules.map(function(r) {
                return { key: r.key, label: r.label, prmt: Number(r.prmt) || 0 };
            });
        }
        async function loadChPromoDilPrmtRules() {
            $('#ch-promo-dil-prmt-status').text('Loading…');
            try {
                const res = await $.ajax({
                    url: CH_PROMO_RULES_BASE + '/dil-prmt',
                    method: 'GET',
                    dataType: 'json',
                });
                if (res && Array.isArray(res.rules) && res.rules.length) {
                    chPromoDilPrmtRules = res.rules.map(function(r) { return Object.assign({}, r); });
                }
                if (typeof CH_PEF_SKIP_DIL_EQ_ZERO !== 'undefined' && CH_PEF_SKIP_DIL_EQ_ZERO) {
                    chPromoDilPrmtRules = chPromoDilPrmtRules.filter(function(r) {
                        return r && r.key !== 'eq-0';
                    });
                }
                if (!CHANNEL_PROMO_SHOW_ZERO_SOLD_RULES) {
                    chPromoDilPrmtRules = chPromoDilPrmtRules.filter(function(r) {
                        return !chPromoIsZeroSoldRuleKey(r.key);
                    });
                }
                if (CHANNEL_PROMO_SHOW_ZERO_SOLD_RULES) {
                    const have = {};
                    chPromoDilPrmtRules.forEach(function(r) { have[r.key] = true; });
                    CH_PEF_DIL_PRMT_DEFAULTS_REVERB.forEach(function(d) {
                        if (!have[d.key]) chPromoDilPrmtRules.push(Object.assign({}, d));
                    });
                }
                renderChPromoDilPrmtModalTable();
                renderChPromoZeroSoldPrmtModalTable();
                const defaultMsg = CHANNEL_PROMO_SHOW_ZERO_SOLD_RULES
                    ? 'Using first-time defaults. Apply to save & apply.'
                    : (CH_PEF_USES_EBAY_FINE_DIL
                        ? (typeof CH_PEF_SKIP_DIL_EQ_ZERO !== 'undefined' && CH_PEF_SKIP_DIL_EQ_ZERO
                            ? 'Using first-time defaults (0–2, 2–4, … 24–26). Apply to save & apply.'
                            : 'Using first-time defaults (0–0, 0.1–2, … 24–26). Apply to save & apply.')
                        : (CHANNEL_PROMO_CHANNEL === 'reverb'
                            ? 'Using first-time defaults (0.1–20). Apply to save & apply.'
                            : 'Using first-time defaults (0–10). Apply to save & apply.'));
                $('#ch-promo-dil-prmt-status').text(res && res.is_default
                    ? defaultMsg
                    : 'Loaded saved Dil vs PRMT rules for ' + (chPromoCfg.label || CHANNEL_PROMO_CHANNEL) + '.');
                $('#ch-promo-zero-sold-prmt-status').text(res && res.is_default
                    ? 'Using first-time defaults (0 Sold Red / Green / Pink). Apply to save & apply.'
                    : 'Loaded saved 0 Sold Dil color rules.');
                chPromoSyncEbayPrmtColumnFromSlabs();
                if (typeof chPromoMarkEbaySpriceRuleReady === 'function') {
                    chPromoMarkEbaySpriceRuleReady('dil');
                }
            } catch (e) {
                renderChPromoDilPrmtModalTable();
                renderChPromoZeroSoldPrmtModalTable();
                $('#ch-promo-dil-prmt-status').text('Could not load saved rules — showing defaults.');
                $('#ch-promo-zero-sold-prmt-status').text('Could not load saved rules — showing defaults.');
                chPromoSyncEbayPrmtColumnFromSlabs();
                if (typeof chPromoMarkEbaySpriceRuleReady === 'function') {
                    chPromoMarkEbaySpriceRuleReady('dil');
                }
            }
        }
        function saveChPromoDilPrmtRules() {
            const rules = readChPromoDilPrmtRulesFromModal();
            return $.ajax({
                url: CH_PROMO_RULES_BASE + '/dil-prmt',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': chPromoCsrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                contentType: 'application/json',
                data: JSON.stringify({ rules: rules, _token: chPromoCsrf() }),
            }).then(function(res) {
                if (res && Array.isArray(res.rules)) {
                    chPromoDilPrmtRules = res.rules.map(function(r) { return Object.assign({}, r); });
                    renderChPromoDilPrmtModalTable();
                    renderChPromoZeroSoldPrmtModalTable();
                }
                $('#ch-promo-dil-prmt-status').text('Saved.');
                $('#ch-promo-zero-sold-prmt-status').text('Saved.');
                return res;
            });
        }

        function cascadeChPromoCvrCpnFromInput(inputEl) {
            const $inputs = $('#ch-promo-cvr-cpn-tbody .ch-promo-cvr-cpn-input');
            const start = $inputs.index(inputEl);
            if (start < 0) return;
            const startVal = parseFloat($(inputEl).val());
            if (!isFinite(startVal)) return;
            $inputs.each(function(i) {
                if (i < start) return;
                const cpn = Math.max(0, startVal - (i - start));
                $(this).val(cpn);
                const key = String($(this).closest('tr').attr('data-key') || '');
                const rule = chPromoCvrCpnRules.find(function(r) { return r.key === key; });
                if (rule) rule.cpn = cpn;
            });
        }
        function renderChPromoCvrCpnModalTable() {
            const $tb = $('#ch-promo-cvr-cpn-tbody').empty();
            chPromoCvrCpnRules.forEach(function(r, idx) {
                const cpn = isFinite(Number(r.cpn)) ? Number(r.cpn) : 0;
                $tb.append(
                    '<tr data-key="' + String(r.key).replace(/"/g, '&quot;') + '">'
                    + '<td>' + String(r.label || r.key) + '</td>'
                    + '<td class="text-end">'
                    + '<input type="number" class="form-control form-control-sm ch-promo-cvr-cpn-input" '
                    + 'min="0" step="0.1" value="' + cpn + '" data-idx="' + idx + '">'
                    + '</td></tr>'
                );
            });
        }
        function readChPromoCvrCpnRulesFromModal() {
            $('#ch-promo-cvr-cpn-tbody tr').each(function() {
                const key = String($(this).attr('data-key') || '');
                const val = parseFloat($(this).find('.ch-promo-cvr-cpn-input').val());
                const rule = chPromoCvrCpnRules.find(function(r) { return r.key === key; });
                if (!rule) return;
                rule.cpn = (isFinite(val) && val >= 0) ? val : 0;
            });
            return chPromoCvrCpnRules.map(function(r) {
                return { key: r.key, label: r.label, cpn: Number(r.cpn) || 0 };
            });
        }
        async function loadChPromoCvrCpnRules() {
            $('#ch-promo-cvr-cpn-status').text('Loading…');
            try {
                const res = await $.ajax({
                    url: CH_PROMO_RULES_BASE + '/cvr-cpn',
                    method: 'GET',
                    dataType: 'json',
                });
                if (res && Array.isArray(res.rules) && res.rules.length) {
                    chPromoCvrCpnRules = res.rules.map(function(r) { return Object.assign({}, r); });
                    if (CH_PEF_CVR_CPN_SKIP_ZERO) {
                        chPromoCvrCpnRules = chPromoCvrCpnRules.filter(function(r) { return r.key !== 'eq-0'; });
                    }
                }
                renderChPromoCvrCpnModalTable();
                $('#ch-promo-cvr-cpn-status').text(res && res.is_default
                    ? 'Using first-time defaults (0–10). Apply to save & apply.'
                    : 'Loaded saved CVR vs CPN rules for ' + (chPromoCfg.label || CHANNEL_PROMO_CHANNEL) + '.');
                if (typeof chPromoMarkEbaySpriceRuleReady === 'function') {
                    chPromoMarkEbaySpriceRuleReady('cvr');
                }
            } catch (e) {
                renderChPromoCvrCpnModalTable();
                $('#ch-promo-cvr-cpn-status').text('Could not load saved rules — showing defaults.');
                if (typeof chPromoMarkEbaySpriceRuleReady === 'function') {
                    chPromoMarkEbaySpriceRuleReady('cvr');
                }
            }
        }
        function saveChPromoCvrCpnRules() {
            const rules = readChPromoCvrCpnRulesFromModal();
            return $.ajax({
                url: CH_PROMO_RULES_BASE + '/cvr-cpn',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: { rules: rules, _token: chPromoCsrf() },
            }).then(function(res) {
                if (res && Array.isArray(res.rules)) {
                    chPromoCvrCpnRules = res.rules.map(function(r) { return Object.assign({}, r); });
                    renderChPromoCvrCpnModalTable();
                }
                $('#ch-promo-cvr-cpn-status').text('Saved.');
                return res;
            });
        }

        function chPromoIsGtSoldRow(d) {
            return !!CHANNEL_PROMO_SHOW_GT_SOLD_RULES && chPromoReverbSoldQty(d) > 0;
        }
        function chPromoGtSoldRuleForRow(d) {
            if (!chPromoIsGtSoldRow(d) || chPromoInv(d) === 0) return null;
            const key = 'gt-sold-' + chPromoDilColorBand(chPromoDil(d));
            return chPromoGtSoldPrcRules.find(function(r) { return r.key === key; }) || null;
        }
        function chPromoGtSoldIsAmazonRule(rule) {
            return !!(rule && String(rule.key || '') === 'gt-sold-red');
        }
        function chPromoGtSoldSpriceFromStd(d, rule) {
            if (!rule) return null;
            if (chPromoGtSoldIsAmazonRule(rule)) {
                const amazon = chPromoAmazonPrice(d);
                return amazon > 0 ? chPromoRound2(amazon) : null;
            }
            const std = chPromoStdBase(d);
            if (!(std > 0)) return null;
            const pct = Math.max(0, Number(rule.pct) || 0);
            const dir = String(rule.dir || 'increase') === 'decrease' ? 'decrease' : 'increase';
            let price = dir === 'decrease'
                ? chPromoRound2(std * (1 - (pct / 100)))
                : chPromoRound2(std * (1 + (pct / 100)));
            if (!(price >= 0.01)) price = 0.01;
            return price;
        }
        function chPromoGtSoldMetricsPatch(sprice, d) {
            const lp = Number(d && (d.LP_productmaster != null ? d.LP_productmaster : d.lp)) || 0;
            const ship = Number(d && (d.Ship_productmaster != null ? d.Ship_productmaster : d.ship)) || 0;
            const margin = 0.80;
            const sgpft = sprice > 0 ? chPromoRound2(((sprice * margin - ship - lp) / sprice) * 100) : 0;
            const sroi = lp > 0 ? chPromoRound2(((sprice * margin - lp - ship) / lp) * 100) : 0;
            return { SGPFT: sgpft, SPFT: sgpft, SROI: sroi };
        }
        function chPromoRedrawGtSoldColumn() {
            if (typeof table === 'undefined' || !table) return;
            try { table.redraw(true); } catch (e) { /* ignore */ }
        }
        function renderChPromoGtSoldPrcModalTable() {
            const $tb = $('#ch-promo-gt-sold-prc-tbody').empty();
            if (!$tb.length) return;
            const colors = {
                'gt-sold-red': '#a00211',
                'gt-sold-green': '#28a745',
                'gt-sold-pink': '#e83e8c',
            };
            chPromoGtSoldPrcRules.forEach(function(r, idx) {
                const pct = isFinite(Number(r.pct)) ? Number(r.pct) : 0;
                const dir = String(r.dir || 'increase') === 'decrease' ? 'decrease' : 'increase';
                const hex = colors[r.key] || '#212529';
                const isAmz = chPromoGtSoldIsAmazonRule(r);
                const sign = dir === 'decrease' ? '−' : '+';
                if (isAmz) {
                    $tb.append(
                        '<tr data-key="' + String(r.key).replace(/"/g, '&quot;') + '" data-amazon="1">'
                        + '<td><span style="color:' + hex + ';font-weight:600;">' + String(r.label || r.key) + '</span></td>'
                        + '<td><span class="small fw-semibold">Amazon Price</span></td>'
                        + '<td class="text-end text-muted">—</td>'
                        + '<td class="text-end ch-promo-gt-sold-rule-preview" style="font-weight:600;color:' + hex + ';">A Price</td></tr>'
                    );
                    return;
                }
                $tb.append(
                    '<tr data-key="' + String(r.key).replace(/"/g, '&quot;') + '">'
                    + '<td><span style="color:' + hex + ';font-weight:600;">' + String(r.label || r.key) + '</span></td>'
                    + '<td><select class="form-select form-select-sm ch-promo-gt-sold-dir-select" data-idx="' + idx + '">'
                    + '<option value="increase"' + (dir === 'increase' ? ' selected' : '') + '>Increase</option>'
                    + '<option value="decrease"' + (dir === 'decrease' ? ' selected' : '') + '>Decrease</option>'
                    + '</select></td>'
                    + '<td class="text-end">'
                    + '<input type="number" class="form-control form-control-sm ch-promo-gt-sold-pct-input" '
                    + 'min="0" step="0.1" value="' + pct + '" data-idx="' + idx + '">'
                    + '</td>'
                    + '<td class="text-end ch-promo-gt-sold-rule-preview" style="font-weight:600;color:' + hex + ';">'
                    + sign + pct + '%</td></tr>'
                );
            });
        }
        function chPromoRefreshGtSoldRulePreviews() {
            $('#ch-promo-gt-sold-prc-tbody tr').each(function() {
                if ($(this).attr('data-amazon') === '1') {
                    $(this).find('.ch-promo-gt-sold-rule-preview').text('A Price');
                    return;
                }
                const dir = String($(this).find('.ch-promo-gt-sold-dir-select').val() || 'increase');
                const pct = parseFloat($(this).find('.ch-promo-gt-sold-pct-input').val());
                const n = (isFinite(pct) && pct >= 0) ? pct : 0;
                const sign = dir === 'decrease' ? '−' : '+';
                $(this).find('.ch-promo-gt-sold-rule-preview').text(sign + n + '%');
            });
        }
        function readChPromoGtSoldPrcRulesFromModal() {
            $('#ch-promo-gt-sold-prc-tbody tr').each(function() {
                const key = String($(this).attr('data-key') || '');
                const rule = chPromoGtSoldPrcRules.find(function(r) { return r.key === key; });
                if (!rule) return;
                if ($(this).attr('data-amazon') === '1' || chPromoGtSoldIsAmazonRule(rule)) {
                    rule.pct = 0;
                    rule.dir = 'increase';
                    return;
                }
                const pct = parseFloat($(this).find('.ch-promo-gt-sold-pct-input').val());
                const dir = String($(this).find('.ch-promo-gt-sold-dir-select').val() || 'increase');
                rule.pct = (isFinite(pct) && pct >= 0) ? pct : 0;
                rule.dir = dir === 'decrease' ? 'decrease' : 'increase';
            });
            return chPromoGtSoldPrcRules.map(function(r) {
                return {
                    key: r.key,
                    label: r.label,
                    pct: Number(r.pct) || 0,
                    dir: r.dir === 'decrease' ? 'decrease' : 'increase',
                };
            });
        }
        async function loadChPromoGtSoldPrcRules() {
            $('#ch-promo-gt-sold-prc-status').text('Loading…');
            try {
                const res = await $.ajax({
                    url: CH_PROMO_RULES_BASE + '/gt-sold-prc',
                    method: 'GET',
                    dataType: 'json',
                });
                if (res && Array.isArray(res.rules) && res.rules.length) {
                    chPromoGtSoldPrcRules = res.rules.map(function(r) {
                        return {
                            key: r.key,
                            label: r.label,
                            pct: Number(r.pct) || 0,
                            dir: String(r.dir || 'increase') === 'decrease' ? 'decrease' : 'increase',
                        };
                    });
                }
                renderChPromoGtSoldPrcModalTable();
                $('#ch-promo-gt-sold-prc-status').text(res && res.is_default
                    ? 'Using first-time defaults (0%). Set % and Increase/Decrease, then Save Rule or Apply.'
                    : 'Loaded saved >0 Sold Dil color rules.');
                chPromoRedrawGtSoldColumn();
            } catch (e) {
                renderChPromoGtSoldPrcModalTable();
                $('#ch-promo-gt-sold-prc-status').text('Could not load saved rules — showing defaults.');
            }
        }
        function saveChPromoGtSoldPrcRules() {
            const rules = readChPromoGtSoldPrcRulesFromModal();
            return $.ajax({
                url: CH_PROMO_RULES_BASE + '/gt-sold-prc',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: { rules: rules, _token: chPromoCsrf() },
            }).then(function(res) {
                if (res && Array.isArray(res.rules)) {
                    chPromoGtSoldPrcRules = res.rules.map(function(r) {
                        return {
                            key: r.key,
                            label: r.label,
                            pct: Number(r.pct) || 0,
                            dir: String(r.dir || 'increase') === 'decrease' ? 'decrease' : 'increase',
                        };
                    });
                    renderChPromoGtSoldPrcModalTable();
                }
                $('#ch-promo-gt-sold-prc-status').text('Saved.');
                chPromoRedrawGtSoldColumn();
                return res;
            });
        }
        function saveChannelSpriceBatch(updates, extra) {
            extra = extra || {};
            const url = chPromoCfg.saveSpriceBatchUrl;
            if (!url || !updates.length) return Promise.resolve(null);
            const data = { updates: updates, _token: chPromoCsrf() };
            const queueEnabled = chPromoEbayStdMinusPrmtCpnEnabled()
                && typeof enqueueChannelPushSprice === 'function';
            if (queueEnabled || extra.skip_push) data.skip_push = 1;
            return $.ajax({
                url: url,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: data,
            });
        }
        function collectChPromoGtSoldTargets() {
            let targets = collectChPromoSelectedRows();
            let label = 'selected';
            if (!targets.length) {
                targets = collectChPromoVisibleRows();
                label = 'all visible';
            }
            const soldLabel = chPromoSoldFieldLabel();
            targets = targets.filter(function(item) {
                const d = (item.row && item.row.getData()) || item.d || {};
                return chPromoIsChildRow(d) && chPromoReverbSoldQty(d) > 0;
            });
            return { targets: targets, label: label, soldLabel: soldLabel };
        }
        async function applyChPromoGtSoldPrcToTargets(targets, label, opts) {
            opts = opts || {};
            readChPromoGtSoldPrcRulesFromModal();
            if (!targets.length) {
                chPromoToast('error', 'No >0 Sold rows to apply');
                return [];
            }
            const jobs = [];
            let skipped = 0;
            let filled = 0;
            const blocked = typeof table !== 'undefined' && table && typeof table.blockRedraw === 'function';
            if (blocked) table.blockRedraw();
            try {
                for (let i = 0; i < targets.length; i++) {
                    const item = targets[i];
                    const d = item.row.getData() || {};
                    if (!chPromoIsChildRow(d)) { skipped++; continue; }
                    if (chPromoInv(d) === 0) { skipped++; continue; }
                    const rule = chPromoGtSoldRuleForRow(d);
                    const useAmz = chPromoGtSoldIsAmazonRule(rule);
                    const newPrice = chPromoGtSoldSpriceFromStd(d, rule);
                    const sku = chPromoSku(d);
                    if (!(newPrice > 0) || !sku) { skipped++; continue; }
                    const pct = useAmz ? 0 : (rule ? (Number(rule.pct) || 0) : 0);
                    const dir = useAmz ? 'amazon' : (rule && rule.dir === 'decrease' ? 'decrease' : 'increase');
                    const patch = Object.assign(
                        chPromoSpricePatch(newPrice),
                        chPromoGtSoldMetricsPatch(newPrice, d),
                        {
                            gt_sold_pct: pct,
                            gt_sold_dir: dir,
                            SPRICE_STATUS: 'applied',
                        }
                    );
                    item.row.update(patch);
                    filled++;
                    jobs.push({ row: item.row, sku: sku, price: newPrice, pct: pct, dir: dir });
                }
            } finally {
                if (blocked) table.restoreRedraw();
            }
            if (!jobs.length) {
                chPromoToast('error', '>0 Sold Rule (' + label + '): no rows with Std Prc / Amazon Price and INV > 0');
                return [];
            }
            const updates = jobs.map(function(j) { return { sku: j.sku, sprice: j.price }; });
            try {
                if (chPromoCfg.saveSpriceBatchUrl) {
                    await saveChannelSpriceBatch(updates, { skip_push: true });
                } else {
                    await chPromoMapLimit(jobs, 8, async function(job) {
                        await Promise.resolve(saveChannelSprice(job.sku, job.price, true, { skip_push: true }));
                    });
                }
            } catch (e) {
                chPromoToast('error', 'Failed to save S PRC');
                return jobs;
            }
            if (!opts.silentToast) {
                chPromoToast(
                    'success',
                    '>0 Sold Rule (' + label + '): Red Dil → Amazon Price; Green/Pink → Std ± % → '
                    + filled + ' row(s)'
                    + (skipped ? ('; skipped ' + skipped) : '') + '.'
                );
            }
            return jobs;
        }
        async function pushChPromoGtSoldPrices(jobs) {
            if (!jobs.length) {
                chPromoToast('error', 'No prices to push');
                return;
            }
            if (!chPromoCfg.pushPriceUrl) {
                chPromoToast('error', 'Push not configured');
                return;
            }
            chPromoPushPrcCancel = false;
            clearTimeout(setChPromoPushPrcProgress._hideTimer);
            const total = jobs.length;
            let ok = 0;
            let fail = 0;
            setChPromoPushPrcProgress({
                active: true, done: 0, total: total, ok: 0, fail: 0,
                cancelable: true, title: 'Pushing',
                msg: 'Starting…',
            });
            for (let i = 0; i < jobs.length; i++) {
                if (chPromoPushPrcCancel) break;
                const job = jobs[i];
                setChPromoPushPrcProgress({
                    active: true, done: i, total: total, ok: ok, fail: fail,
                    pct: Math.round((i / total) * 100),
                    cancelable: true, title: 'Pushing',
                    msg: job.sku + ' → $' + Number(job.price).toFixed(2),
                });
                try {
                    const res = await pushChannelPriceAjax(job.sku, job.price);
                    if (res && res.success) {
                        ok++;
                        job.row.update({ SPRICE_STATUS: 'pushed' });
                    } else {
                        fail++;
                        job.row.update({ SPRICE_STATUS: 'error' });
                    }
                } catch (e) {
                    fail++;
                    job.row.update({ SPRICE_STATUS: 'error' });
                }
            }
            setChPromoPushPrcProgress({
                active: false, done: total, total: total, ok: ok, fail: fail, pct: 100,
                title: fail && !ok ? 'Push failed' : 'Pushed',
                msg: ok + ' ok' + (fail ? (' · ' + fail + ' failed') : '')
                    + (chPromoPushPrcCancel ? ' (cancelled)' : ''),
            });
            chPromoToast(
                fail && !ok ? 'error' : 'success',
                '>0 Sold Push: ' + ok + ' ok' + (fail ? (', ' + fail + ' failed') : '')
            );
        }
        async function saveAndApplyChPromoGtSoldPrc(opts) {
            opts = opts || {};
            const collected = collectChPromoGtSoldTargets();
            let targets = collected.targets;
            let label = collected.label;
            if (!targets.length) {
                chPromoToast('error', 'No >0 Sold rows (' + collected.soldLabel + ' > 0) to apply');
                return;
            }
            if (label === 'all visible' && !opts.push) {
                if (!confirm('No rows selected — save rules and apply Std ± % to ' + targets.length + ' visible >0 Sold row(s)?')) {
                    return;
                }
            }
            if (opts.push) {
                if (!confirm(
                    'Apply Std ± % and push price to ' + (chPromoCfg.label || CHANNEL_PROMO_CHANNEL)
                    + ' for ' + targets.length + ' ' + label + ' >0 Sold SKU(s)?'
                )) return;
            }
            const $btn = $(opts.push ? '#ch-promo-gt-sold-prc-push-btn' : '#ch-promo-gt-sold-prc-apply-btn');
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> ' + (opts.push ? 'Pushing…' : 'Applying…'));
            try {
                await saveChPromoGtSoldPrcRules();
                const jobs = await applyChPromoGtSoldPrcToTargets(targets, label, { silentToast: !!opts.push });
                if (opts.push && jobs.length) {
                    await pushChPromoGtSoldPrices(jobs);
                }
            } catch (xhr) {
                chPromoToast('error', 'Save failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'));
            } finally {
                $btn.prop('disabled', false).html(html);
            }
        }

        async function saveAndApplyChPromoDilPrmt() {
            const $btn = $('#ch-promo-dil-prmt-apply-btn');
            const $menuBtn = $('#ch-promo-dil-vs-prmt-btn');
            const html = $btn.html();
            const menuHtml = $menuBtn.length ? $menuBtn.html() : '';
            chPromoSetApplyBtnProgress($btn, 0, 0, 'Applying');
            if ($menuBtn.length) {
                $menuBtn.prop('disabled', true)
                    .html('<i class="fas fa-spinner fa-spin me-1"></i>Applying…');
            }
            try {
                await saveChPromoDilPrmtRules();
            } catch (xhr) {
                $btn.prop('disabled', false).html(html);
                if ($menuBtn.length) $menuBtn.prop('disabled', false).html(menuHtml);
                chPromoToast('error', 'Save failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'));
                return;
            }

            let targets = collectChPromoSelectedRows();
            let label = 'selected';
            if (!targets.length) {
                targets = collectChPromoVisibleRows();
                label = 'all visible';
            }
            if (chPromoSaleGatedApply() && !chPromoUsesAmazonDilPrmtSlabs()) {
                targets = targets.filter(function(t) {
                    const d = (t.d || (t.row && t.row.getData())) || {};
                    return chPromoHasSaleQty(d);
                });
            }
            if (!targets.length) {
                $btn.prop('disabled', false).html(html);
                if ($menuBtn.length) $menuBtn.prop('disabled', false).html(menuHtml);
                chPromoToast(
                    'success',
                    (chPromoSaleGatedApply() && !chPromoUsesAmazonDilPrmtSlabs())
                        ? ('Rules saved. No rows with ' + chPromoSaleGateLabel() + ' to apply.')
                        : 'Rules saved. No SKUs to apply.'
                );
                chPromoSyncEbayPrmtColumnFromSlabs();
                return;
            }
            if (label === 'all visible') {
                if (!confirm(
                    'No rows selected — apply Dil→PRMT % to '
                    + targets.length + ' visible SKU(s)'
                    + ((chPromoSaleGatedApply() && !chPromoUsesAmazonDilPrmtSlabs())
                        ? (' with ' + chPromoSaleGateLabel()) : '')
                    + '?'
                )) {
                    $btn.prop('disabled', false).html(html);
                    if ($menuBtn.length) $menuBtn.prop('disabled', false).html(menuHtml);
                    return;
                }
            }
            try {
                chPromoSetApplyBtnProgress($btn, 0, targets.length, 'Applying');
                if ($menuBtn.length) {
                    $menuBtn.prop('disabled', true)
                        .html('<i class="fas fa-spinner fa-spin me-1"></i>Applying…');
                }
                await applyChPromoDilPrmtToTargets(targets, label, { $btn: $btn, $menuBtn: $menuBtn });
            } catch (xhr) {
                chPromoToast('error', 'Apply failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'));
            } finally {
                $btn.prop('disabled', false).html(html);
                if ($menuBtn.length) $menuBtn.prop('disabled', false).html(menuHtml);
            }
        }

        async function saveChPromoCvrCpnRulesOnly() {
            const $btn = $('#ch-promo-cvr-cpn-save-btn');
            const html = $btn.length ? $btn.html() : '';
            if ($btn.length) $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Saving…');
            try {
                await saveChPromoCvrCpnRules();
                chPromoToast('success', 'CVR vs CPN rules saved');
            } catch (xhr) {
                chPromoToast('error', 'Save failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'));
            } finally {
                if ($btn.length) $btn.prop('disabled', false).html(html || '<i class="fas fa-save me-1"></i>Save Rule');
            }
        }
        async function saveAndApplyChPromoCvrCpn() {
            const $btn = $('#ch-promo-cvr-cpn-apply-btn');
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying…');
            try {
                await saveChPromoCvrCpnRules();
            } catch (xhr) {
                $btn.prop('disabled', false).html(html);
                chPromoToast('error', 'Save failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'));
                return;
            }

            let targets = collectChPromoSelectedRows();
            let label = 'selected';
            if (!targets.length) {
                targets = collectChPromoVisibleRows();
                label = 'all visible';
            }
            if (chPromoSaleGatedApply()) {
                targets = targets.filter(function(t) {
                    const d = (t.d || (t.row && t.row.getData())) || {};
                    return chPromoHasSaleQty(d);
                });
            }
            if (!targets.length) {
                $btn.prop('disabled', false).html(html);
                chPromoToast(
                    'success',
                    (chPromoSaleGatedApply())
                        ? ('Rules saved. No rows with ' + chPromoSaleGateLabel() + ' to apply.')
                        : 'Rules saved. No SKUs to apply.'
                );
                return;
            }
            if (label === 'all visible') {
                if (!confirm(
                    'No rows selected — apply CVR→CPN % to '
                    + targets.length + ' visible row(s)'
                    + ((chPromoSaleGatedApply())
                        ? (' with ' + chPromoSaleGateLabel()) : '')
                    + '?'
                )) {
                    $btn.prop('disabled', false).html(html);
                    return;
                }
            }
            try {
                await applyChPromoCvrCpnToTargets(targets, label);
            } catch (xhr) {
                chPromoToast('error', 'Apply failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'));
            } finally {
                $btn.prop('disabled', false).html(html);
            }
        }

        function renderChPromoZeroSoldDilModalTable() {
            const $tb = $('#ch-promo-zero-sold-dil-tbody').empty();
            chPromoZeroSoldDilRules.forEach(function(r, idx) {
                const groi = isFinite(Number(r.groi)) ? Number(r.groi) : 0;
                let labelHtml = String(r.label || r.key);
                if (CHANNEL_PROMO_ZERO_SOLD_DIL_COLOR_SLABS) {
                    const hex = chPromoDilColorHex(r.key);
                    labelHtml = '<span style="color:' + hex + ';font-weight:700;">'
                        + '<i class="fas fa-circle me-1" style="font-size:0.65em;"></i>'
                        + String(r.label || r.key) + '</span>';
                }
                $tb.append(
                    '<tr data-key="' + String(r.key).replace(/"/g, '&quot;') + '">'
                    + '<td>' + labelHtml + '</td>'
                    + '<td class="text-end">'
                    + '<input type="number" class="form-control form-control-sm ch-promo-zero-sold-dil-roi-input" '
                    + 'step="0.1" value="' + groi + '" data-idx="' + idx + '" title="Target ROI% for this Dil slab">'
                    + '</td></tr>'
                );
            });
        }
        function readChPromoZeroSoldDilRulesFromModal() {
            $('#ch-promo-zero-sold-dil-tbody tr').each(function() {
                const key = String($(this).attr('data-key') || '');
                const val = parseFloat($(this).find('.ch-promo-zero-sold-dil-roi-input').val());
                const rule = chPromoZeroSoldDilRules.find(function(r) { return r.key === key; });
                if (!rule) return;
                rule.groi = isFinite(val) ? val : 0;
            });
            return chPromoZeroSoldDilRules.map(function(r) {
                return { key: r.key, label: r.label, groi: Number(r.groi) || 0 };
            });
        }
        async function loadChPromoZeroSoldDilRules() {
            $('#ch-promo-zero-sold-dil-status').text('Loading…');
            try {
                const res = await $.ajax({
                    url: CH_PROMO_RULES_BASE + '/zero-sold-prc',
                    method: 'GET',
                    dataType: 'json',
                });
                if (res && Array.isArray(res.rules) && res.rules.length) {
                    chPromoZeroSoldDilRules = res.rules.map(function(r) { return Object.assign({}, r); });
                }
                renderChPromoZeroSoldDilModalTable();
                $('#ch-promo-zero-sold-dil-status').text(res && res.is_default
                    ? (CHANNEL_PROMO_ZERO_SOLD_DIL_COLOR_SLABS
                        ? 'Using first-time Dil color defaults. Autopopulate to save & set S PRC on 0 Sold (AL30 = 0).'
                        : 'Using first-time defaults. Apply to save & set S PRC on 0 Sold rows.')
                    : 'Loaded saved 0 Sold vs ' + (CHANNEL_PROMO_ZERO_SOLD_DIL_COLOR_SLABS ? 'ROI' : 'Dil')
                        + ' rules for ' + (chPromoCfg.label || CHANNEL_PROMO_CHANNEL) + '.');
            } catch (e) {
                renderChPromoZeroSoldDilModalTable();
                $('#ch-promo-zero-sold-dil-status').text('Could not load saved rules — showing defaults.');
            }
        }
        function saveChPromoZeroSoldDilRules() {
            const rules = readChPromoZeroSoldDilRulesFromModal();
            return $.ajax({
                url: CH_PROMO_RULES_BASE + '/zero-sold-prc',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: { rules: rules, _token: chPromoCsrf() },
            }).then(function(res) {
                if (res && Array.isArray(res.rules)) {
                    chPromoZeroSoldDilRules = res.rules.map(function(r) { return Object.assign({}, r); });
                    renderChPromoZeroSoldDilModalTable();
                }
                $('#ch-promo-zero-sold-dil-status').text('Saved.');
                return res;
            });
        }
        function chPromoZeroSoldGateLabel() {
            if (CHANNEL_PROMO_CHANNEL === 'aliexpress') return 'AL30 = 0';
            if (chPromoIsEbayChannel()) return 'E L30 = 0';
            if (chPromoCfg.soldField) return chPromoSoldFieldLabel() + ' = 0';
            return '0 Sold';
        }
        /** Saved PRMT% / cvr% (not Dil live-slab). Dil display does not count as "existed". */
        function chPromoPrmtAndCvrBothZero(d) {
            const savedPrmt = Number(d && (d.PEF_PRMT_PCT != null && d.PEF_PRMT_PCT !== ''
                ? d.PEF_PRMT_PCT : d._saved_prmt_pct));
            const savedCvr = Number(d && (d.PEF_CPN_PCT != null && d.PEF_CPN_PCT !== ''
                ? d.PEF_CPN_PCT : (d._cpn_pct_applied != null ? d._cpn_pct_applied : d.cpn_pct)));
            const prmt = isFinite(savedPrmt) ? Math.max(0, savedPrmt) : 0;
            const cvr = isFinite(savedCvr) ? Math.max(0, savedCvr) : 0;
            return !(prmt > 0) && !(cvr > 0);
        }
        /**
         * Encode 0 sold vs ROI target price as PRMT% + cvr% off Std.
         * PRMT gets the Dil vs PRMT slab (capped); remainder goes to cvr%.
         */
        function chPromoZeroSoldRoiAsPrmtCvr(d, targetSprice) {
            const target = chPromoRound2(targetSprice);
            if (!(target > 0)) return null;
            const std = chPromoStdBase(d);
            if (!(std > 0)) return null;
            if (target >= std) {
                return { prmt: 0, cpn: 0, sprice: std };
            }
            const total = Math.min(99.99, Math.max(0, chPromoRound2((1 - (target / std)) * 100)));
            let prmt = 0;
            if (typeof chPromoPrmtForDil === 'function') {
                const slab = chPromoPrmtForDil(chPromoDil(d));
                if (slab != null) prmt = Math.min(total, Math.max(0, Number(slab) || 0));
            }
            prmt = chPromoRound2(prmt);
            let cpn = chPromoRound2(Math.max(0, total - prmt));
            if (prmt + cpn > 99.99) cpn = chPromoRound2(Math.max(0, 99.99 - prmt));
            const fromStd = chPromoRound2(std * (1 - ((prmt + cpn) / 100)));
            return {
                prmt: prmt,
                cpn: cpn,
                sprice: fromStd >= 0.01 ? fromStd : target,
            };
        }
        function chPromoIsAeZeroSoldRoiRow(d) {
            return CHANNEL_PROMO_CHANNEL === 'aliexpress'
                && chPromoIsChildRow(d)
                && !d.is_parent
                && typeof chPromoHasSaleQty === 'function'
                && !chPromoHasSaleQty(d)
                && chPromoInv(d) > 0
                && chPromoLp(d) > 0
                && chPromoStdBase(d) > 0;
        }
        /** 0-sold S PRC from Std − PRMT − cvr (Target ROI chooses the discount). Never uses live Price. */
        function chPromoAeZeroSoldStdSprice(d) {
            if (!chPromoIsAeZeroSoldRoiRow(d)) return 0;
            const roi = chPromoRoiForZeroSoldDil(chPromoDil(d));
            const target = chPromoSpriceFromTargetRoi(d, roi);
            if (!(target > 0)) return 0;
            const encoded = chPromoZeroSoldRoiAsPrmtCvr(d, target);
            return encoded && encoded.sprice > 0 ? encoded.sprice : 0;
        }
        function collectChPromoZeroSoldDilTargets() {
            let targets = collectChPromoSelectedRows();
            let label = 'selected';
            if (!targets.length) {
                targets = collectChPromoVisibleRows();
                label = 'all visible';
            }
            const ready = targets.filter(function(t) {
                const d = (t.d || (t.row && t.row.getData())) || {};
                if (!chPromoIsChildRow(d)
                    || d.is_parent
                    || chPromoHasSaleQty(d)
                    || !(chPromoInv(d) > 0)
                    || !(chPromoLp(d) > 0)
                    || (CHANNEL_PROMO_CHANNEL === 'aliexpress' && !(chPromoStdBase(d) > 0))) {
                    return false;
                }
                return true;
            });
            return { targets: ready, label: label, selectedCount: targets.length };
        }
        function chPromoZeroSoldDilSetProgress(text, show) {
            const $p = $('#ch-promo-zero-sold-dil-progress');
            if (!$p.length) return;
            if (show === false) {
                $p.hide().text('');
                return;
            }
            $p.show().text(text || '');
        }
        function chPromoZeroSoldDilSetRunUi(state) {
            const $apply = $('#ch-promo-zero-sold-dil-apply-btn');
            const $pause = $('#ch-promo-zero-sold-dil-pause-btn');
            const colorMode = CHANNEL_PROMO_ZERO_SOLD_DIL_COLOR_SLABS;
            if (state === 'running') {
                $apply.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Running…');
                $pause.prop('disabled', false).html('<i class="fas fa-pause me-1"></i> Pause');
                $('#ch-promo-zero-sold-dil-save-btn').prop('disabled', true);
            } else if (state === 'paused') {
                $apply.prop('disabled', false).html('<i class="fas fa-play me-1"></i> Resume');
                $pause.prop('disabled', true).html('<i class="fas fa-pause me-1"></i> Paused');
                $('#ch-promo-zero-sold-dil-save-btn').prop('disabled', false);
            } else {
                $apply.prop('disabled', false).html(
                    colorMode
                        ? '<i class="fas fa-magic me-1"></i> Autopopulate'
                        : 'Apply'
                );
                $pause.prop('disabled', true).html('<i class="fas fa-pause me-1"></i> Pause');
                $('#ch-promo-zero-sold-dil-save-btn').prop('disabled', false);
            }
        }
        async function chPromoZeroSoldDilProcessNext() {
            const run = chPromoZeroSoldDilRun;
            if (!run.active) return;
            if (run.paused) {
                chPromoZeroSoldDilSetRunUi('paused');
                chPromoZeroSoldDilSetProgress(
                    'Paused at ' + run.index + '/' + run.jobs.length
                    + ' (ok ' + run.ok + ', fail ' + run.fail + '). Click Resume to continue.'
                );
                return;
            }
            if (run.index >= run.jobs.length) {
                run.active = false;
                chPromoZeroSoldDilSetRunUi('idle');
                if (typeof table !== 'undefined' && table) table.redraw(true);
                chPromoZeroSoldDilSetProgress('', false);
                const title = CHANNEL_PROMO_ZERO_SOLD_DIL_COLOR_SLABS ? '0 sold vs ROI' : '0 sold Vs Dil Rule';
                chPromoToast(
                    run.fail && !run.ok ? 'error' : 'success',
                    title + ' (' + run.label + '): PRMT% + cvr% from Target ROI% → ' + run.ok + ' row(s)'
                        + (run.fail ? (', ' + run.fail + ' failed') : '')
                        + (run.skipped ? (', ' + run.skipped + ' skipped') : '') + '.'
                );
                $('#ch-promo-zero-sold-dil-status').text(
                    'Done: ' + run.ok + ' saved'
                    + (run.fail ? (', ' + run.fail + ' failed') : '')
                    + (run.skipped ? (', ' + run.skipped + ' skipped') : '') + '.'
                );
                return;
            }
            const job = run.jobs[run.index];
            chPromoZeroSoldDilSetProgress(
                'Autopopulating ' + (run.index + 1) + '/' + run.jobs.length
                + ' — ' + (job.sku || '') + ' → $' + Number(job.price).toFixed(2)
                + ' (PRMT ' + job.prmt + '% + cvr ' + job.cpn + '% · Target ROI ' + Math.round(job.roi) + '%)'
            );
            try {
                const extra = {
                    skip_push: true,
                    prmt_pct: job.prmt,
                    cpn_pct: job.cpn,
                };
                const saveRes = job.row
                    ? await Promise.resolve(saveChannelSpriceAndPromo(job.row, job.price, true, extra))
                    : await Promise.resolve(saveChannelSprice(job.sku, job.price, true, extra));
                chPromoApplySpriceSavePatch(job.row, job.price, saveRes);
                run.ok++;
            } catch (e) {
                chPromoApplySpriceSavePatch(job.row, job.price, null);
                run.fail++;
            }
            run.index++;
            setTimeout(function() { chPromoZeroSoldDilProcessNext(); }, 40);
        }
        async function applyChPromoZeroSoldDilToTargets(targets, label) {
            readChPromoZeroSoldDilRulesFromModal();
            if (!targets.length) {
                chPromoToast('error', 'No 0 Sold rows (' + chPromoZeroSoldGateLabel() + ', INV > 0, LP > 0) to price');
                return;
            }
            const jobs = [];
            let skipped = 0;
            const blocked = typeof table !== 'undefined' && table && typeof table.blockRedraw === 'function';
            if (blocked) table.blockRedraw();
            try {
                for (let i = 0; i < targets.length; i++) {
                    const item = targets[i];
                    const d = item.row.getData();
                    if (!chPromoIsChildRow(d) || d.is_parent || chPromoHasSaleQty(d)) {
                        skipped++;
                        continue;
                    }
                    const roi = chPromoRoiForZeroSoldDil(chPromoDil(d));
                    const target = chPromoSpriceFromTargetRoi(d, roi);
                    if (!(target > 0)) { skipped++; continue; }
                    if (CHANNEL_PROMO_CHANNEL === 'aliexpress' && !(chPromoStdBase(d) > 0)) {
                        skipped++;
                        continue;
                    }
                    const encoded = (CHANNEL_PROMO_CHANNEL === 'aliexpress'
                        && typeof chPromoZeroSoldRoiAsPrmtCvr === 'function')
                        ? chPromoZeroSoldRoiAsPrmtCvr(d, target)
                        : { prmt: 0, cpn: 0, sprice: target };
                    if (CHANNEL_PROMO_CHANNEL === 'aliexpress' && !(encoded && encoded.sprice > 0)) {
                        skipped++;
                        continue;
                    }
                    const fill = encoded && encoded.sprice > 0 ? encoded.sprice : target;
                    const prmt = encoded ? (Number(encoded.prmt) || 0) : 0;
                    const cpn = encoded ? (Number(encoded.cpn) || 0) : 0;
                    const sgpft = fill > 0
                        ? Math.round(((fill * chPromoTakehomeMargin(d) - chPromoLp(d) - chPromoShipCost(d)) / fill) * 100)
                        : 0;
                    const sroi = chPromoLp(d) > 0
                        ? Math.round(((fill * chPromoTakehomeMargin(d) - chPromoLp(d) - chPromoShipCost(d)) / chPromoLp(d)) * 100)
                        : 0;
                    item.row.update(Object.assign({
                        ZERO_SOLD_PRC_APPLIED: true,
                        ZERO_SOLD_PRC_GROI: roi,
                        prmt_pct: String(prmt),
                        _prmt_pct_applied: prmt,
                        PEF_PRMT_PCT: prmt,
                        cpn_pct: String(cpn),
                        _cpn_pct_applied: cpn,
                        PEF_CPN_PCT: cpn,
                        sgpft: sgpft,
                        SGPFT: sgpft,
                        sroi: sroi,
                        SROI: sroi,
                    }, chPromoSpricePatch(fill)));
                    jobs.push({
                        row: item.row,
                        sku: chPromoSku(d),
                        price: fill,
                        roi: roi,
                        prmt: prmt,
                        cpn: cpn,
                    });
                }
            } finally {
                if (blocked) table.restoreRedraw();
            }
            if (!jobs.length) {
                chPromoToast('error', 'No 0 Sold rows could be priced (need LP, take-home margin, and Target ROI)');
                return;
            }

            // Color-slab channels: sequential Autopopulate with Pause/Resume
            if (CHANNEL_PROMO_ZERO_SOLD_DIL_COLOR_SLABS) {
                chPromoZeroSoldDilRun = {
                    active: true,
                    paused: false,
                    jobs: jobs,
                    index: 0,
                    ok: 0,
                    fail: 0,
                    skipped: skipped,
                    label: label,
                };
                chPromoZeroSoldDilSetRunUi('running');
                chPromoZeroSoldDilProcessNext();
                return;
            }

            let ok = 0;
            let fail = 0;
            const conc = (CHANNEL_PROMO_CHANNEL === 'ebay3') ? 12 : 8;
            await chPromoMapLimit(jobs, conc, async function(job) {
                try {
                    const extra = {
                        skip_push: true,
                        prmt_pct: job.prmt,
                        cpn_pct: job.cpn,
                    };
                    const saveRes = job.row
                        ? await Promise.resolve(saveChannelSpriceAndPromo(job.row, job.price, true, extra))
                        : await Promise.resolve(saveChannelSprice(job.sku, job.price, true, extra));
                    chPromoApplySpriceSavePatch(job.row, job.price, saveRes);
                    ok++;
                } catch (e) {
                    chPromoApplySpriceSavePatch(job.row, job.price, null);
                    fail++;
                }
            });
            if (typeof table !== 'undefined' && table) table.redraw(true);
            chPromoToast(
                fail && !ok ? 'error' : 'success',
                '0 sold Vs Dil Rule (' + label + '): S PRC from Dil→Target ROI% → ' + ok + ' row(s)'
                    + (fail ? (', ' + fail + ' failed') : '')
                    + (skipped ? (', ' + skipped + ' skipped') : '') + '.'
            );
        }
        async function saveAndApplyChPromoZeroSoldDil() {
            const run = chPromoZeroSoldDilRun;
            if (run.active && run.paused) {
                run.paused = false;
                chPromoZeroSoldDilSetRunUi('running');
                chPromoZeroSoldDilProcessNext();
                return;
            }
            if (run.active && !run.paused) return;

            const collected = collectChPromoZeroSoldDilTargets();
            const targets = collected.targets;
            const label = collected.label;
            if (!targets.length) {
                chPromoToast('error', collected.selectedCount > 0
                    ? ('Selected rows are not eligible (need ' + chPromoZeroSoldGateLabel()
                        + ', INV > 0, LP > 0)')
                    : ('No 0 Sold rows (' + chPromoZeroSoldGateLabel()
                        + ', INV > 0, LP > 0) to price'));
                return;
            }
            if (label === 'all visible') {
                const action = CHANNEL_PROMO_ZERO_SOLD_DIL_COLOR_SLABS ? 'autopopulate S PRC' : 'set S PRC';
                if (!confirm(
                    'No rows selected — save rules and ' + action + ' from Dil→Target ROI% on '
                    + targets.length + ' visible 0 Sold row(s) (' + chPromoZeroSoldGateLabel() + ')?'
                )) {
                    return;
                }
            }
            const $btn = $('#ch-promo-zero-sold-dil-apply-btn');
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving…');
            try {
                await saveChPromoZeroSoldDilRules();
                await applyChPromoZeroSoldDilToTargets(targets, label);
            } catch (xhr) {
                chPromoToast('error', 'Save failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'));
                chPromoZeroSoldDilSetRunUi('idle');
            } finally {
                if (!chPromoZeroSoldDilRun.active) {
                    $btn.prop('disabled', false).html(html);
                }
            }
        }
        function pauseChPromoZeroSoldDilRun() {
            if (!chPromoZeroSoldDilRun.active || chPromoZeroSoldDilRun.paused) return;
            chPromoZeroSoldDilRun.paused = true;
            chPromoZeroSoldDilSetRunUi('paused');
            chPromoZeroSoldDilSetProgress(
                'Pausing… will stop after the current SKU.'
            );
        }

        function chPromoNearlyEqual(a, b) {
            return Math.abs(Number(a) - Number(b)) < 0.005;
        }

        async function applyChPromoDilPrmtToTargets(targets, label, opts) {
            opts = opts || {};
            readChPromoDilPrmtRulesFromModal();
            if (!targets.length) {
                chPromoToast('error', 'No rows to apply');
                return;
            }
            const ebay1PrmtOnly = chPromoEbayStdMinusPrmtCpnEnabled();
            const ebayParentDil = chPromoIsEbayChannel();
            let applyTargets = targets.slice();
            let prmtForRow = chPromoPrmtForRow;
            let listingCount = 0;
            if ((CHANNEL_PROMO_SHOW_ZERO_SOLD_RULES || CHANNEL_PROMO_CHANNEL === 'reverb') && !ebayParentDil) {
                const soldLabel = chPromoSoldFieldLabel();
                if (opts.zeroSoldOnly) {
                    applyTargets = applyTargets.filter(function(item) {
                        return chPromoReverbSoldQty(item.row.getData()) <= 0;
                    });
                    prmtForRow = chPromoPrmtForZeroSoldRow;
                    if (!applyTargets.length) {
                        chPromoToast('error', 'No 0 Sold rows (' + soldLabel + ' = 0) to apply');
                        return;
                    }
                } else {
                    applyTargets = applyTargets.filter(function(item) {
                        return chPromoReverbSoldQty(item.row.getData()) > 0;
                    });
                    prmtForRow = function(d) {
                        return chPromoInv(d) === 0 ? 0 : chPromoPrmtForDil(chPromoDil(d));
                    };
                    if (!applyTargets.length) {
                        chPromoToast('error', 'No sold rows (' + soldLabel + ' > 0) to apply Dil vs PRMT');
                        return;
                    }
                }
            }
            if (!ebayParentDil && !opts.zeroSoldOnly) {
                if (chPromoSaleGatedApply() && !chPromoUsesAmazonDilPrmtSlabs()) {
                    const soldLabel = chPromoSoldFieldLabel();
                    applyTargets = applyTargets.filter(function(item) {
                        const d = (item.d || (item.row && item.row.getData())) || {};
                        return chPromoIsChildRow(d) && chPromoHasSaleQty(d);
                    });
                    if (!applyTargets.length) {
                        chPromoToast('error', 'No sold rows (' + soldLabel + ' > 0) to apply Dil vs PRMT');
                        return;
                    }
                } else {
                    applyTargets = applyTargets.filter(function(item) {
                        const d = (item.d || (item.row && item.row.getData())) || {};
                        return chPromoIsChildRow(d);
                    });
                    if (!applyTargets.length) {
                        chPromoToast('error', 'No SKUs to apply Dil vs PRMT');
                        return;
                    }
                }
                prmtForRow = function(d) {
                    return chPromoInv(d) === 0 ? 0 : chPromoPrmtForDil(chPromoDil(d));
                };
            }
            if (ebayParentDil) {
                chPromoInvalidateListingDilCache();
                const dataset = chPromoPromoDataset();
                const keyOf = chPromoVariationKeyFn(dataset);
                const parentDil = chPromoParentDilByKey(dataset, keyOf);
                const keys = new Set();
                applyTargets = targets.filter(function(item) {
                    const d = (item.d || (item.row && item.row.getData())) || {};
                    if (!chPromoIsChildRow(d)) return false;
                    if (chPromoEbayListingSaleQty(d) <= 0) return false;
                    const k = keyOf(d);
                    if (k) keys.add(k);
                    return true;
                });
                if (!applyTargets.length) {
                    chPromoToast('error', 'No rows with eBay sale (E L30) > 0 to apply');
                    return;
                }
                listingCount = keys.size;
                const prmtByKey = {};
                keys.forEach(function(k) {
                    const agg = parentDil[k] || { dil: 0, inv: 0 };
                    prmtByKey[k] = agg.inv <= 0 ? 0 : chPromoPrmtForDil(agg.dil);
                });
                prmtForRow = function(d) {
                    const k = keyOf(d);
                    return Object.prototype.hasOwnProperty.call(prmtByKey, k)
                        ? prmtByKey[k]
                        : 0;
                };
            }
            const jobs = [];
            let skipped = 0;
            let filled = 0;
            const $progressBtn = opts.$btn || null;
            const applyTotal = applyTargets.length;
            const blocked = typeof table !== 'undefined' && table && typeof table.blockRedraw === 'function';
            if (blocked) table.blockRedraw();
            try {
                for (let i = 0; i < applyTargets.length; i++) {
                    const item = applyTargets[i];
                    const d = item.row.getData();
                    const isParent = !!(item.isParent || chPromoIsParentRow(d));
                    if (isParent || !chPromoIsChildRow(d)) { skipped++; continue; }
                    const prmt = prmtForRow(d);
                    const sku = chPromoSku(d);
                    const prevPrmt = opts.zeroSoldOnly
                        ? chPromoZeroSoldPrmtInt(d)
                        : (Number(d.prmt_pct != null ? d.prmt_pct : d._prmt_pct_applied) || 0);
                    const prevSprice = chPromoGetSprice(d);
                    let newPrice = 0;
                    let skipSprice = true;
                    let patch = opts.zeroSoldOnly
                        ? { zero_sold_prmt: String(prmt), _zero_sold_prmt_applied: prmt }
                        : { prmt_pct: String(prmt), _prmt_pct_applied: prmt };

                    if (isParent) {
                        item.row.update(patch);
                        if (prmt > 0) filled++;
                        else skipped++;
                        if (sku && !chPromoNearlyEqual(prevPrmt, prmt)) {
                            jobs.push({
                                row: item.row,
                                sku: sku,
                                prmt: prmt,
                                price: 0,
                                skipSprice: true,
                            });
                        }
                        if ($progressBtn && (i % 20 === 0 || i === applyTotal - 1)) {
                            chPromoSetApplyBtnProgress($progressBtn, i + 1, applyTotal, 'Applying');
                        }
                        continue;
                    }

                    if (chPromoPrmtCpnComboEnabled()) {
                        // S PRC = Std × (1 − (PRMT% + CPN%)/100)
                        newPrice = chPromoTemuSpriceFromStdPrmtCpn(d, { prmt: prmt });
                        if (newPrice > 0) {
                            Object.assign(patch, chPromoSpricePatch(newPrice));
                            skipSprice = false;
                        }
                    } else if (ebay1PrmtOnly) {
                        if (chPromoInv(d) > 0) {
                            newPrice = chPromoSpriceFromStdPrmtCpnWith(d, { prmt: prmt });
                            if (newPrice > 0) {
                                Object.assign(patch, chPromoSpricePatch(newPrice));
                                skipSprice = false;
                            }
                        }
                    } else if (chPromoReverbComboEnabled()) {
                        if (chPromoKeepZeroSoldPrcSprice(d)) {
                            // 0 Sold Prc Rule owns SPRICE (Target SGROI). PRMT% stays independent.
                            skipSprice = true;
                        } else {
                            newPrice = chPromoReverbSpriceFromStdBothPrmt(d, opts.zeroSoldOnly
                                ? { zeroSold: prmt }
                                : { prmt: prmt });
                            if (newPrice > 0) {
                                Object.assign(patch, chPromoSpricePatch(newPrice));
                                skipSprice = false;
                            }
                        }
                    } else if (prmt > 0) {
                        const promo = { type: 'percent', value: prmt };
                        const base = getChPromoDiscountBase(d, '_prmt_pct_applied');
                        newPrice = applyChPromoToSpriceBase(base, promo);
                        if (base > 0 && newPrice > 0) {
                            Object.assign(patch, chPromoSpricePatch(newPrice));
                            skipSprice = false;
                        } else {
                            newPrice = 0;
                        }
                    }

                    item.row.update(patch);
                    if (prmt > 0) filled++;
                    else skipped++;
                    const prmtSame = chPromoNearlyEqual(prevPrmt, prmt);
                    const spriceSame = skipSprice || !(newPrice > 0) || chPromoNearlyEqual(prevSprice, newPrice);
                    if (!prmtSame || !spriceSame) {
                        jobs.push({
                            row: item.row,
                            sku: sku,
                            prmt: prmt,
                            price: skipSprice ? 0 : newPrice,
                            skipSprice: skipSprice,
                        });
                    }
                    if ($progressBtn && (i % 20 === 0 || i === applyTotal - 1)) {
                        chPromoSetApplyBtnProgress($progressBtn, i + 1, applyTotal, 'Applying');
                    }
                }
            } finally {
                if (blocked) table.restoreRedraw();
            }

            const conc = (CHANNEL_PROMO_CHANNEL === 'ebay3') ? 12 : 8;
            if ($progressBtn && jobs.length) {
                chPromoSetApplyBtnProgress($progressBtn, 0, jobs.length, 'Saving');
            }
            await chPromoMapLimit(jobs, conc, async function(job) {
                if (!job.sku) return;
                const extra = opts.zeroSoldOnly
                    ? { zero_sold_prmt: job.prmt }
                    : { prmt_pct: job.prmt };
                try {
                    if (job.skipSprice) {
                        await Promise.resolve(saveChannelPromoFields(job.sku, extra));
                        return;
                    }
                    await saveChannelSpriceAndPromo(job.row, job.price, true, extra);
                } catch (e) { /* keep going */ }
            }, function(done, total) {
                if ($progressBtn) chPromoSetApplyBtnProgress($progressBtn, done, total, 'Saving');
            });

            chPromoToast(
                (filled ? 'success' : 'error'),
                (opts.zeroSoldOnly ? '0 Sold Dil Color' : 'Dil vs PRMT') + ' (' + label + '): '
                    + (opts.zeroSoldOnly ? '0 Sold PRMT%' : 'PRMT %') + ' → ' + filled + ' SKU(s)'
                    + (ebayParentDil && listingCount
                        ? (' from ' + listingCount + ' listing Dil' + (listingCount === 1 ? '' : 's'))
                        : '')
                    + (skipped ? ('; skipped ' + skipped) : '')
                    + (ebay1PrmtOnly ? ' (S PRC = Std − PRMT% − CPN%)' : '')
                    + (chPromoPrmtCpnComboEnabled()
                        ? ' (S PRC = Std − (PRMT% + CPN%))'
                        : '')
                    + (chPromoReverbComboEnabled()
                        ? ' (S PRC = Std − (PRMT% + 0 Sold PRMT%))'
                        : '') + '.'
            );
        }

        async function applyChPromoCvrCpnToTargets(targets, label) {
            readChPromoCvrCpnRulesFromModal();
            if (!targets.length) {
                chPromoToast('error', 'No rows to apply');
                return;
            }
            if (chPromoSaleGatedApply()) {
                targets = targets.filter(function(item) {
                    const d = (item.d || (item.row && item.row.getData())) || {};
                    return chPromoIsChildRow(d) && chPromoHasSaleQty(d);
                });
                if (!targets.length) {
                    chPromoToast('error', 'No rows with ' + chPromoSaleGateLabel() + ' to apply');
                    return;
                }
            }
            const ebay1 = chPromoEbayStdMinusPrmtCpnEnabled();
            const jobs = [];
            let skipped = 0;
            for (let i = 0; i < targets.length; i++) {
                const item = targets[i];
                const d = item.row.getData();
                if (!chPromoIsChildRow(d)) { skipped++; continue; }
                const cvr = chPromoCvr(d);
                const cpn = chPromoInv(d) === 0 ? 0 : chPromoCpnForCvr(cvr);
                const sku = chPromoSku(d);
                if (!(cpn > 0)) {
                    if (chPromoPrmtCpnComboEnabled()) {
                        const newPrice = chPromoTemuSpriceFromStdPrmtCpn(d, { cpn: 0 });
                        if (newPrice > 0) {
                            item.row.update(Object.assign({
                                cpn_pct: String(cpn),
                                _cpn_pct_applied: 0,
                            }, chPromoSpricePatch(newPrice)));
                            jobs.push({ row: item.row, sku: sku, cpn: cpn, price: newPrice, skipSprice: false });
                        } else {
                            item.row.update({ cpn_pct: String(cpn), _cpn_pct_applied: 0 });
                            jobs.push({ row: item.row, sku: sku, cpn: cpn, price: 0, skipSprice: false });
                        }
                    } else if (ebay1) {
                        const newPrice = chPromoSpriceFromStdPrmtCpnWith(d, { cpn: 0 });
                        const patch = { cpn_pct: String(cpn), _cpn_pct_applied: 0 };
                        if (newPrice > 0) Object.assign(patch, chPromoSpricePatch(newPrice));
                        item.row.update(patch);
                        jobs.push({ row: item.row, sku: sku, cpn: cpn, price: newPrice, skipSprice: !(newPrice > 0) });
                    } else {
                        item.row.update({ cpn_pct: String(cpn), _cpn_pct_applied: 0 });
                        jobs.push({ row: item.row, sku: sku, cpn: cpn, price: 0, skipSprice: ebay1 });
                    }
                    skipped++;
                    continue;
                }
                if (ebay1) {
                    const newPrice = chPromoSpriceFromStdPrmtCpnWith(d, { cpn: cpn });
                    const patch = { cpn_pct: String(cpn), _cpn_pct_applied: cpn };
                    if (newPrice > 0) Object.assign(patch, chPromoSpricePatch(newPrice));
                    item.row.update(patch);
                    jobs.push({ row: item.row, sku: sku, cpn: cpn, price: newPrice, skipSprice: !(newPrice > 0) });
                } else if (chPromoPrmtCpnComboEnabled()) {
                    const newPrice = chPromoTemuSpriceFromStdPrmtCpn(d, { cpn: cpn });
                    if (newPrice > 0) {
                        item.row.update(Object.assign({
                            cpn_pct: String(cpn),
                            _cpn_pct_applied: cpn,
                        }, chPromoSpricePatch(newPrice)));
                        jobs.push({ row: item.row, sku: sku, cpn: cpn, price: newPrice, skipSprice: false });
                    } else {
                        item.row.update({ cpn_pct: String(cpn), _cpn_pct_applied: cpn });
                        jobs.push({ row: item.row, sku: sku, cpn: cpn, price: 0, skipSprice: false });
                    }
                } else {
                    const promo = { type: 'percent', value: cpn };
                    const base = getChPromoDiscountBase(d, '_cpn_pct_applied');
                    const newPrice = applyChPromoToSpriceBase(base, promo);
                    if (!(base > 0) || !(newPrice > 0)) {
                        item.row.update({ cpn_pct: String(cpn), _cpn_pct_applied: cpn });
                        jobs.push({ row: item.row, sku: sku, cpn: cpn, price: 0, skipSprice: false });
                    } else {
                        item.row.update(Object.assign({
                            cpn_pct: String(cpn),
                            _cpn_pct_applied: cpn,
                        }, chPromoSpricePatch(newPrice)));
                        jobs.push({ row: item.row, sku: sku, cpn: cpn, price: newPrice, skipSprice: false });
                    }
                }
            }
            if (typeof table !== 'undefined' && table) table.redraw(true);

            await chPromoMapLimit(jobs, 8, async function(job) {
                if (!job.sku) return;
                try {
                    if (job.skipSprice) {
                        await Promise.resolve(saveChannelPromoFields(job.sku, { cpn_pct: job.cpn }));
                        return;
                    }
                    await saveChannelSpriceAndPromo(job.row, job.price, true, { cpn_pct: job.cpn });
                } catch (e) { /* keep going */ }
            });

            const ok = jobs.filter(function(j) { return Number(j.cpn) > 0; }).length;
            chPromoToast(
                (ok ? 'success' : 'error'),
                'CVR vs CPN (' + label + '): CPN % → ' + ok + ' row(s)'
                    + (skipped ? ('; skipped ' + skipped) : '')
                    + (ebay1 ? ' (saved locally — use Push CPN% to create eBay coupons)' : '')
                    + '.'
            );
        }

        async function pushEbay1CodedCouponsForTargets(targets) {
            const jobs = [];
            targets.forEach(function(t) {
                const d = t.row.getData();
                if (!chPromoIsChildRow(d)) return;
                const sku = chPromoSku(d);
                if (!sku) return;
                const cpn = Math.max(0, Number(d.cpn_pct != null ? d.cpn_pct : d._cpn_pct_applied) || 0);
                jobs.push({ row: t.row, sku: sku, cpn: cpn });
            });
            let ok = 0;
            let fail = 0;
            await chPromoMapLimit(jobs, 3, async function(job) {
                const api = await syncEbay1CodedCoupon(job.sku, job.cpn);
                if (api.ok) {
                    ok++;
                    if (api.coupon_code) {
                        job.row.update({ PEF_COUPON_CODE: api.coupon_code, coupon_code: api.coupon_code });
                    }
                } else {
                    fail++;
                }
            });
            chPromoToast(
                fail && !ok ? 'error' : 'success',
                'eBay public coupon: ' + ok + ' ok' + (fail ? (' / ' + fail + ' fail') : '')
            );
            if (typeof table !== 'undefined' && table) table.redraw(true);
        }

        function chPromoLmpDiffPct(d) {
            const price = chPromoPrice(d);
            const lmp = Number(d.lmp_price) || 0;
            if (!(price > 0) || !(lmp > 0) || !(price > lmp)) return null;
            return chPromoRound2(((price - lmp) / price) * 100);
        }
        /** DSC % display = PRMT % + CPN % (same row). */
        function chPromoDscSumPct(d) {
            const prmt = Math.max(0, Number(d.prmt_pct != null ? d.prmt_pct : d._prmt_pct_applied) || 0);
            const cpn = Math.max(0, Number(d.cpn_pct != null ? d.cpn_pct : d._cpn_pct_applied) || 0);
            return chPromoRound2(Math.min(99.99, prmt + cpn));
        }
        function clearChPromoApprDiscount(row, opts) {
            opts = opts || {};
            const d = row.getData();
            const prev = Number(d._dsc_applied) || 0;
            const patch = {
                appr: false,
                _appr_lmp: null,
                dsc: '',
                _dsc_applied: 0,
            };
            if (prev > 0 && prev < 100 && chPromoGetSprice(d) > 0) {
                Object.assign(patch, chPromoSpricePatch(chPromoRound2(chPromoGetSprice(d) / (1 - (prev / 100)))));
            }
            row.update(patch);
            if (opts.save) {
                const sprice = patch.SPRICE != null ? Number(patch.SPRICE) : chPromoGetSprice(row.getData());
                saveChannelSpriceAndPromo(row, sprice, true, { dsc: 0, appr: false });
            }
            if (opts.redraw && typeof table !== 'undefined' && table) table.redraw(true);
        }
        function applyChPromoApprDiscount(row) {
            const d = row.getData();
            const pct = chPromoLmpDiffPct(d);
            const lmp = Number(d.lmp_price);
            if (!(pct > 0) || !(lmp > 0)) {
                row.update({ appr: false, _appr_lmp: null });
                chPromoToast('error', 'Appr needs Price > LMP');
                if (typeof table !== 'undefined' && table) table.redraw(true);
                return false;
            }
            const base = getChPromoDiscountBase(d, '_dsc_applied');
            if (!(base > 0)) {
                row.update({ appr: false, _appr_lmp: null });
                chPromoToast('error', 'No S PRC/Price to discount');
                if (typeof table !== 'undefined' && table) table.redraw(true);
                return false;
            }
            if (pct >= 100) {
                row.update({ appr: false, _appr_lmp: null });
                chPromoToast('error', 'Appr DSC % out of range');
                if (typeof table !== 'undefined' && table) table.redraw(true);
                return false;
            }
            const newPrice = applyChPromoToSpriceBase(base, { type: 'percent', value: pct });
            if (!(newPrice > 0)) {
                row.update({ appr: false, _appr_lmp: null });
                chPromoToast('error', 'No S PRC/Price to discount');
                if (typeof table !== 'undefined' && table) table.redraw(true);
                return false;
            }
            row.update(Object.assign({
                appr: true,
                _appr_lmp: chPromoRound2(lmp),
                dsc: String(pct),
                _dsc_applied: pct,
            }, chPromoSpricePatch(newPrice)));
            saveChannelSpriceAndPromo(row, newPrice, true, { dsc: pct, appr: true });
            if (typeof table !== 'undefined' && table) table.redraw(true);
            return true;
        }

        async function applyChPromoFromCell(cell, kind) {
            const fieldMeta = {
                prmt: { field: 'prmt_pct', appliedKey: '_prmt_pct_applied', label: 'PRMT %', allowZero: true },
                cpn: { field: 'cpn_pct', appliedKey: '_cpn_pct_applied', label: 'CPN %', allowZero: true },
                dsc: { field: 'dsc', appliedKey: '_dsc_applied', label: 'DSC %', allowZero: false },
            }[kind];
            if (!fieldMeta) return;
            const editedRow = cell.getRow();
            const raw = cell.getValue();
            const promo = fieldMeta.allowZero
                ? parseChPromoPercentAllowZero(raw)
                : parseChPromoPercentAmount(raw);
            if (!promo) {
                if (String(raw == null ? '' : raw).trim() !== '') {
                    chPromoToast('error', 'Enter ' + fieldMeta.label + ' (e.g. 10)');
                }
                return;
            }
            let targets = [{ row: editedRow, d: editedRow.getData() }];
            const selected = collectChPromoSelectedRows();
            const editedSku = chPromoSku(editedRow.getData());
            if (selected.length > 1 && selected.some(function(t) { return chPromoSku(t.d) === editedSku; })) {
                targets = selected;
            }
            const displayVal = String(promo.value);
            let ok = 0;
            let skipped = 0;
            for (let i = 0; i < targets.length; i++) {
                const item = targets[i];
                const d = item.row.getData();
                if (!chPromoIsChildRow(d)) { skipped++; continue; }
                if (!(promo.value > 0)) {
                    const patch = {};
                    patch[fieldMeta.field] = '0';
                    patch[fieldMeta.appliedKey] = 0;
                    if (kind === 'dsc') {
                        patch.appr = false;
                        patch._appr_lmp = null;
                    }
                    // Cleared PRMT/CPN on Temu → S PRC = Std − remaining %
                    if ((kind === 'prmt' || kind === 'cpn') && chPromoPrmtCpnComboEnabled()) {
                        const recalc = chPromoTemuSpriceFromStdPrmtCpn(d, kind === 'prmt' ? { prmt: 0 } : { cpn: 0 });
                        if (recalc > 0) Object.assign(patch, chPromoSpricePatch(recalc));
                    } else if (kind === 'prmt' && chPromoReverbComboEnabled() && !chPromoKeepZeroSoldPrcSprice(d)) {
                        const recalc = chPromoReverbSpriceFromStdBothPrmt(d, { prmt: 0 });
                        if (recalc > 0) Object.assign(patch, chPromoSpricePatch(recalc));
                    } else if ((kind === 'prmt' || kind === 'cpn') && chPromoEbayStdMinusPrmtCpnEnabled()) {
                        const recalc = chPromoSpriceFromStdPrmtCpnWith(
                            d,
                            kind === 'prmt' ? { prmt: 0 } : { cpn: 0 }
                        );
                        if (recalc > 0) Object.assign(patch, chPromoSpricePatch(recalc));
                    }
                    item.row.update(patch);
                    const extra = {};
                    if (kind === 'prmt') extra.prmt_pct = 0;
                    if (kind === 'cpn') extra.cpn_pct = 0;
                    if (kind === 'dsc') { extra.dsc = 0; extra.appr = false; }
                    const savePrice = ((kind === 'prmt' || kind === 'cpn') && patch.SPRICE != null && (
                        chPromoEbayStdMinusPrmtCpnEnabled()
                        || chPromoPrmtCpnComboEnabled()
                        || chPromoReverbComboEnabled()
                    ))
                        ? Number(patch.SPRICE)
                        : chPromoGetSprice(d);
                    await saveChannelSpriceAndPromo(item.row, savePrice, true, extra);
                    skipped++;
                    continue;
                }
                // eBay: S PRC = Std × (1 − (PRMT% + CPN%)/100)
                // Temu: S PRC = Std × (1 − (PRMT% + CPN%)/100)
                let base;
                let newPrice;
                if (chPromoEbayStdMinusPrmtCpnEnabled() && (kind === 'prmt' || kind === 'cpn')) {
                    base = chPromoStdBase(d);
                    newPrice = chPromoSpriceFromStdPrmtCpnWith(
                        d,
                        kind === 'prmt' ? { prmt: promo.value } : { cpn: promo.value }
                    );
                } else if (chPromoPrmtCpnComboEnabled() && (kind === 'prmt' || kind === 'cpn')) {
                    base = chPromoStdBase(d);
                    newPrice = chPromoTemuSpriceFromStdPrmtCpn(d, kind === 'prmt'
                        ? { prmt: promo.value }
                        : { cpn: promo.value });
                } else if (chPromoReverbComboEnabled() && kind === 'prmt' && chPromoKeepZeroSoldPrcSprice(d)) {
                    // Keep Target-SGROI SPRICE; only store PRMT%.
                    base = 0;
                    newPrice = null;
                } else if (chPromoReverbComboEnabled() && kind === 'prmt') {
                    base = chPromoStdBase(d);
                    newPrice = chPromoReverbSpriceFromStdBothPrmt(d, { prmt: promo.value });
                } else {
                    base = getChPromoDiscountBase(d, fieldMeta.appliedKey);
                    newPrice = applyChPromoToSpriceBase(base, promo);
                }
                const extra = {};
                if (kind === 'prmt') extra.prmt_pct = promo.value;
                if (kind === 'cpn') extra.cpn_pct = promo.value;
                if (kind === 'dsc') { extra.dsc = promo.value; extra.appr = false; }
                if (!(base > 0) || !(newPrice > 0)) {
                    // Still persist the % on refresh even if S PRC cannot be discounted
                    const patchOnly = {};
                    patchOnly[fieldMeta.field] = displayVal;
                    patchOnly[fieldMeta.appliedKey] = promo.value;
                    if (kind === 'dsc') {
                        patchOnly.appr = false;
                        patchOnly._appr_lmp = null;
                    }
                    item.row.update(patchOnly);
                    await saveChannelSpriceAndPromo(item.row, 0, true, extra);
                    ok++;
                    continue;
                }
                const patch = Object.assign({}, chPromoSpricePatch(newPrice));
                patch[fieldMeta.field] = displayVal;
                patch[fieldMeta.appliedKey] = promo.value;
                if (kind === 'dsc') {
                    patch.appr = false;
                    patch._appr_lmp = null;
                }
                item.row.update(patch);
                await saveChannelSpriceAndPromo(item.row, newPrice, true, extra);
                ok++;
            }
            chPromoToast(
                ok ? 'success' : 'error',
                fieldMeta.label + ' → ' + ok + ' row(s)' + (skipped ? ('; skipped ' + skipped) : '')
            );
            if (typeof table !== 'undefined' && table) table.redraw(true);
        }

        function computeChannelPushPrcPlan(d) {
            const stdRaw = Number(d.STANDARD_PRICE) || Number(d.standard_price) || 0;
            if (!(stdRaw > 0)) return null;
            const std = chPromoRound2(stdRaw);
            const prmt = Math.max(0, Number(d.prmt_pct != null ? d.prmt_pct : d._prmt_pct_applied) || 0);
            const cpn = Math.max(0, Number(d.cpn_pct != null ? d.cpn_pct : d._cpn_pct_applied) || 0);
            const zeroSold = chPromoReverbComboEnabled() ? chPromoZeroSoldPrmtInt(d) : 0;
            const temuCombo = chPromoPrmtCpnComboEnabled();
            const reverbCombo = chPromoReverbComboEnabled();
            const totalDisc = Math.min(99.99, temuCombo ? (prmt + cpn) : (reverbCombo ? (prmt + zeroSold) : prmt));
            let sale = null;
            if (totalDisc > 0 && totalDisc < 100) {
                sale = chPromoRound2(std * (1 - (totalDisc / 100)));
                if (!(sale >= 0.01) || sale >= std) sale = null;
            }
            const saleBase = sale != null ? sale : std;
            const max = chPromoRound2(std * 1.10);
            const min = chPromoRound2(Math.max(0.01, saleBase * 0.95));
            const business = chPromoRound2(Math.max(0.01, saleBase * 0.95));
            // Listing push = Std Prc. Sale = PRMT% event. Coupon = CPN% campaign.
            return {
                std: std,
                sale: sale,
                max: max,
                min: min,
                business: business,
                prmt: prmt,
                zeroSold: zeroSold,
                totalDisc: totalDisc,
                cpn: cpn,
                effective: std,
            };
        }

        function chPromoPlanSaleSprice(plan) {
            if (!plan) return 0;
            if (plan.sale != null && plan.sale > 0) return plan.sale;
            return plan.std > 0 ? plan.std : 0;
        }

        function chPromoPushPrcStepsText(plan) {
            if (!plan || !(plan.std > 0)) return '';
            let text = '1) Push Std Prc $' + plan.std.toFixed(2) + ' as listing price';
            if (chPromoPushPrcHasSaleCoupon()) {
                text += '\n2) Coupon campaign from CPN% ' + plan.cpn + '%'
                    + (plan.cpn <= 0 ? ' (remove if any)' : '');
            }
            return text;
        }

        function applyChannelPushPrcToSpriceRow(row, plan, saveRes) {
            const listing = plan.std;
            const updates = {
                PUSH_PRC_VALUE: listing,
                prmt_pct: String(plan.prmt),
                cpn_pct: String(plan.cpn),
                _prmt_pct_applied: plan.prmt,
                _cpn_pct_applied: plan.cpn,
            };
            if (saveRes && saveRes.sgpft_percent !== undefined) updates.SGPFT = saveRes.sgpft_percent;
            if (saveRes && saveRes.spft_percent !== undefined) updates['Spft%'] = saveRes.spft_percent;
            if (saveRes && saveRes.sroi_percent !== undefined) updates.SROI = saveRes.sroi_percent;
            if (saveRes && saveRes.sgroi_percent !== undefined) updates.SGROI = saveRes.sgroi_percent;
            row.update(updates);
            try { row.reformat(); } catch (e) { /* ignore */ }
        }

        function setChPromoPushPrcProgress(opts) {
            opts = opts || {};
            const $box = $('#ch-promo-push-prc-progress');
            if (!$box.length) return;
            const total = Number(opts.total) || 0;
            const done = Number(opts.done) || 0;
            const ok = Number(opts.ok) || 0;
            const fail = Number(opts.fail) || 0;
            const active = !!opts.active;
            const pct = (opts.pct != null)
                ? Math.min(100, Number(opts.pct) || 0)
                : (total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0);
            const finished = !active && total > 0 && (done >= total || pct >= 100);
            const cancelable = opts.cancelable != null ? !!opts.cancelable : false;
            const title = opts.title
                || (finished ? (fail && !ok ? 'Push failed' : 'Pushed') : 'Pushing');

            if (active || finished) $box.addClass('active');
            else $box.removeClass('active');

            $box.toggleClass('done', finished || (!active && pct >= 100));
            $box.toggleClass('has-fail', fail > 0);

            $('#ch-promo-push-prc-progress-title').text(title);
            $('#ch-promo-push-prc-progress-pct').text(pct + '%');
            $('#ch-promo-push-prc-progress-bar').css('width', pct + '%');
            $('#ch-promo-push-prc-cancel-btn').toggle(!!cancelable && !!active);

            let msg = opts.msg || '';
            if (!msg && total) {
                msg = done + '/' + total + ' · ' + ok + ' ok'
                    + (fail ? (' · ' + fail + ' failed') : '');
            }
            $('#ch-promo-push-prc-progress-msg').text(msg || 'Ready');

            if (finished) {
                clearTimeout(setChPromoPushPrcProgress._hideTimer);
                setChPromoPushPrcProgress._hideTimer = setTimeout(function() {
                    if (!$box.hasClass('done')) return;
                    $box.removeClass('active done has-fail');
                    $('#ch-promo-push-prc-progress-bar').css('width', '0%');
                    $('#ch-promo-push-prc-progress-pct').text('0%');
                    $('#ch-promo-push-prc-progress-msg').text('Ready');
                    $('#ch-promo-push-prc-progress-title').text('Pushing');
                    $('#ch-promo-push-prc-cancel-btn').hide();
                }, 8000);
            }
        }

        function pushChannelPriceAjax(sku, price) {
            if (!chPromoCfg.pushPriceUrl) {
                return $.Deferred().resolve({ skipped: true, message: 'Push not configured' }).promise();
            }
            return $.ajax({
                url: chPromoCfg.pushPriceUrl,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: { sku: sku, price: price, _token: chPromoCsrf() },
            });
        }

        function planToChannelPushPrcQueueItem(d, plan) {
            return {
                sku: chPromoSku(d),
                std: plan.std,
                sale: plan.sale,
                max: plan.max,
                min: plan.min,
                business: plan.business,
                effective: plan.effective,
                prmt: plan.prmt,
                cpn: plan.cpn,
                cvr_disc: 0,
            };
        }

        function applyChannelPushPrcTaskStatusesToTable(tasks) {
            if (typeof table === 'undefined' || !table || !Array.isArray(tasks)) return;
            const bySku = {};
            tasks.forEach(function(t) {
                if (t && t.sku) bySku[String(t.sku).toUpperCase()] = t;
            });
            table.getRows().forEach(function(row) {
                const d = row.getData();
                if (!chPromoIsChildRow(d)) return;
                const sku = chPromoSku(d).toUpperCase();
                const t = bySku[sku];
                if (!t) return;
                const st = String(t.status || '');
                if (st === 'ok') {
                    row.update({
                        PUSH_PRC_STATUS: 'pushed',
                        PUSH_PRC_VALUE: t.std != null ? t.std : (t.effective != null ? t.effective : d.PUSH_PRC_VALUE),
                        push_prc: 'pushed',
                    });
                } else if (st === 'failed') {
                    row.update({ PUSH_PRC_STATUS: 'error', push_prc: 'error' });
                } else if (st === 'pushing' || st === 'pending' || st === 'queued') {
                    row.update({ PUSH_PRC_STATUS: 'processing', push_prc: 'processing' });
                }
                chPromoRefreshPushCell(row, 'push_prc', '.ch-promo-push-prc-btn', 'PUSH_PRC_STATUS', 'Pushing Std → coupon…');
            });
            try { table.redraw(true); } catch (e) { /* ignore */ }
            table.getRows().forEach(function(row) {
                const d = row.getData() || {};
                if (String(d.PUSH_PRC_STATUS || '') === 'processing') {
                    chPromoRefreshPushCell(row, 'push_prc', '.ch-promo-push-prc-btn', 'PUSH_PRC_STATUS', 'Pushing Std → coupon…');
                }
            });
        }

        function stopChannelPushPrcPoll() {
            if (chPromoPushPrcPollTimer) {
                clearInterval(chPromoPushPrcPollTimer);
                chPromoPushPrcPollTimer = null;
            }
        }

        function pollChannelPushPrcStatus() {
            if (!chPromoPushQueueEnabled) return;
            $.ajax({
                url: CH_PROMO_PUSH_QUEUE_URL + '/status',
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                timeout: 20000,
            }).done(function(resp) {
                if (!resp) return;
                const active = !!resp.active;
                const applied = chPromoApplyQueuedPushStatus('prc', chPromoPushPrcWatching, resp, 'Pushing');
                chPromoPushPrcWatching = applied.watching;
                applyChannelPushPrcTaskStatusesToTable(resp.tasks || []);

                if (!active) {
                    stopChannelPushPrcPoll();
                    const toastKey = applied.jobStatus + '|' + applied.ok + '|' + applied.fail + '|' + applied.total;
                    if (applied.shouldToast && toastKey !== chPromoPushPrcLastToastKey) {
                        chPromoPushPrcLastToastKey = toastKey;
                        chPromoToast(
                            applied.fail && !applied.ok ? 'error' : 'success',
                            resp.message || ('Push Prc: ' + applied.ok + ' ok' + (applied.fail ? (', ' + applied.fail + ' failed') : ''))
                        );
                    }
                }
            }).fail(function() {
                // Keep polling — worker may still be fine
            });
        }

        function startChannelPushPrcPoll() {
            stopChannelPushPrcPoll();
            chPromoPushPrcPollTimer = setInterval(pollChannelPushPrcStatus, 1000);
            pollChannelPushPrcStatus();
        }

        /** Queue SKUs for background Push Prc (append-safe while a job is running). */
        function queueChannelPushPrcItems(items) {
            if (!items || !items.length) {
                chPromoToast('error', 'Nothing to queue');
                return Promise.resolve(null);
            }
            chPromoPushPrcWatching = true;
            clearTimeout(setChPromoPushPrcProgress._hideTimer);
            setChPromoPushPrcProgress({
                active: true,
                done: 0,
                total: items.length,
                ok: 0,
                fail: 0,
                pct: 5,
                cancelable: true,
                title: 'Pushing',
                msg: 'Queuing ' + items.length + ' SKU(s)…',
            });

            return $.ajax({
                url: CH_PROMO_PUSH_QUEUE_URL,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: {
                    _token: chPromoCsrf(),
                    items: items,
                },
                timeout: 60000,
            }).done(function(resp) {
                chPromoToast(
                    'success',
                    (resp && resp.message)
                        || ('Queued ' + items.length + ' Push Prc job(s) — safe to refresh')
                );
                if (resp) {
                    setChPromoPushPrcProgress({
                        active: !!resp.active,
                        done: Number(resp.done_count) || 0,
                        total: Number(resp.total) || items.length,
                        ok: Number(resp.ok_count) || 0,
                        fail: Number(resp.fail_count) || 0,
                        pct: Number(resp.pct) || 0,
                        cancelable: !!resp.active,
                        title: 'Pushing',
                        msg: resp.message || '',
                    });
                }
                startChannelPushPrcPoll();
            }).fail(function(xhr) {
                const msg = (xhr.responseJSON && xhr.responseJSON.message)
                    || 'Could not queue Push Prc';
                chPromoToast('error', msg);
                setChPromoPushPrcProgress({ active: false, done: 0, total: 0, msg: msg });
            });
        }

        function cancelChannelPushPrcJob() {
            if (!confirm('Cancel remaining Push Prc jobs?')) return;
            $.ajax({
                url: CH_PROMO_PUSH_QUEUE_URL + '/cancel',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: { _token: chPromoCsrf() },
            }).done(function(resp) {
                chPromoToast('success', (resp && resp.message) || 'Push Prc cancelled');
                pollChannelPushPrcStatus();
            }).fail(function(xhr) {
                chPromoToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Cancel failed');
            });
        }

        /** Fallback for channels without background queue. */
        function runChannelPushPrcSequential(ready) {
            chPromoPushPrcCancel = false;
            clearTimeout(setChPromoPushPrcProgress._hideTimer);
            let ok = 0;
            let fail = 0;
            let i = 0;
            const total = ready.length;
            let warnedNoPush = false;
            setChPromoPushPrcProgress({
                active: true, done: 0, total: total, ok: 0, fail: 0,
                cancelable: true, title: 'Pushing',
                msg: 'Starting…',
            });

            function finish() {
                setChPromoPushPrcProgress({
                    active: false, done: total, total: total, ok: ok, fail: fail, pct: 100,
                    title: fail && !ok ? 'Push failed' : 'Pushed',
                    msg: ok + ' ok' + (fail ? (' · ' + fail + ' failed') : '')
                        + (chPromoPushPrcCancel ? ' (cancelled)' : ''),
                });
                if (typeof table !== 'undefined' && table) {
                    try { table.redraw(true); } catch (e) { /* ignore */ }
                }
                let toastMsg = 'Push Prc: ' + ok + ' ok' + (fail ? (', ' + fail + ' failed') : '');
                if (!chPromoCfg.pushPriceUrl) toastMsg += ' — Push not configured';
                chPromoToast(fail && !ok ? 'error' : 'success', toastMsg);
            }

            async function next() {
                if (chPromoPushPrcCancel || i >= ready.length) {
                    finish();
                    return;
                }
                const item = ready[i++];
                const d = item.row.getData();
                const sku = chPromoSku(d);
                const plan = item.plan || computeChannelPushPrcPlan(d);
                const listing = plan && plan.std > 0 ? plan.std : 0;
                const steps = chPromoPushPrcHasSaleCoupon() ? 2 : 1;
                function skuStepPct(stepDone) {
                    const per = 100 / total;
                    return Math.min(99, Math.round(((i - 1) + (stepDone / steps)) * per));
                }
                setChPromoPushPrcProgress({
                    active: true, done: i - 1, total: total, ok: ok, fail: fail,
                    pct: skuStepPct(0),
                    cancelable: true, title: 'Pushing',
                    msg: sku + (steps > 1 ? ' · 1/' + steps + ' listing price' : ' · pushing listing'),
                });
                if (!sku || !plan || !(listing > 0)) {
                    fail++;
                    item.row.update({ PUSH_PRC_STATUS: 'error' });
                    next();
                    return;
                }
                item.row.update({ PUSH_PRC_STATUS: 'processing' });
                applyChannelPushPrcToSpriceRow(item.row, plan, null);

                try {
                    await Promise.resolve(saveChannelPromoFields(sku, {
                        prmt_pct: plan.prmt,
                        cpn_pct: plan.cpn,
                        record_push_prc: 1,
                        push_prc_value: listing,
                    }));
                    if (!chPromoCfg.pushPriceUrl) {
                        if (!warnedNoPush) {
                            warnedNoPush = true;
                            chPromoToast('info', 'Push not configured');
                        }
                    } else {
                        await Promise.resolve(pushChannelPriceAjax(sku, listing));
                    }
                    if (chPromoPushPrcHasSaleCoupon()) {
                        setChPromoPushPrcProgress({
                            active: true, done: i - 1, total: total, ok: ok, fail: fail,
                            pct: skuStepPct(1),
                            cancelable: true, title: 'Pushing',
                            msg: sku + ' · 2/2 coupon',
                        });
                        const stepErr = [];
                        const cpnRes = await syncEbay1CodedCoupon(sku, plan.cpn);
                        if (!cpnRes.ok) stepErr.push('Coupon: ' + (cpnRes.message || 'failed'));
                        if (stepErr.length) throw new Error(stepErr.join(' | '));
                        item.row.update({
                            PUSH_PRC_STATUS: 'pushed',
                            PUSH_PRC_VALUE: listing,
                            PUSH_CPN_STATUS: 'pushed',
                            PEF_COUPON_PCT: plan.cpn > 0 ? Math.round(plan.cpn) : 0,
                            PEF_COUPON_CODE: cpnRes.coupon_code || item.row.getData().PEF_COUPON_CODE,
                            coupon_code: cpnRes.coupon_code || item.row.getData().coupon_code,
                            PEF_COUPON_PROMOTION_ID: cpnRes.promotion_id || item.row.getData().PEF_COUPON_PROMOTION_ID,
                        });
                    } else {
                        item.row.update({ PUSH_PRC_STATUS: 'pushed', PUSH_PRC_VALUE: listing });
                    }
                    ok++;
                } catch (e) {
                    item.row.update({ PUSH_PRC_STATUS: 'error' });
                    fail++;
                    const msg = (e && e.message)
                        || (e && e.responseJSON && e.responseJSON.message)
                        || 'Push failed';
                    if (total === 1) chPromoToast('error', sku + ': ' + msg);
                }
                setChPromoPushPrcProgress({
                    active: true, done: i, total: total, ok: ok, fail: fail,
                    pct: Math.min(100, Math.round((i / total) * 100)),
                    cancelable: true, title: 'Pushing',
                    msg: sku,
                });
                next();
            }
            next();
        }

        function pushChannelStdPrcWithPromos($btn, row) {
            const selected = collectChPromoSelectedRows();
            const clickedKey = chPromoSkuKey(chPromoSku(row.getData()));
            const clickedSelected = selected.some(function(t) {
                return chPromoSkuKey(chPromoSku(t.d)) === clickedKey;
            });
            if (selected.length > 1 && clickedSelected) {
                bulkPushChannelPrcSelected();
                return;
            }

            const d = row.getData();
            const sku = chPromoSku(d);
            const plan = computeChannelPushPrcPlan(d);
            if (!sku || !plan || !(plan.std > 0)) {
                chPromoToast('error', 'Std Prc required — Push Prc sends listing = Std, then coupon (CPN%)');
                return;
            }
            if (!confirm(
                (chPromoPushQueueEnabled
                    ? ('Queue Push Prc for ' + sku + ' in background?\n\n')
                    : ('Push Prc for ' + sku + '?\n\n'))
                + chPromoPushPrcStepsText(plan)
                + '\nMax $' + plan.max.toFixed(2)
                + ' · Min $' + plan.min.toFixed(2)
                + ' · Biz $' + plan.business.toFixed(2)
                + (chPromoPushQueueEnabled
                    ? '\n\nYou can refresh or queue more SKUs while it runs.'
                    : (!chPromoCfg.pushPriceUrl ? '\n\n(Push URL not configured — will save promo only)' : ''))
            )) {
                chPromoRefreshPushCell(row, 'push_prc', '.ch-promo-push-prc-btn', 'PUSH_PRC_STATUS', 'Pushing Std → coupon…');
                return;
            }

            row.update({ PUSH_PRC_STATUS: 'processing', push_prc: 'processing' });
            chPromoRefreshPushCell(row, 'push_prc', '.ch-promo-push-prc-btn', 'PUSH_PRC_STATUS', 'Pushing Std → coupon…');
            setChPromoPushPrcProgress({
                active: true, done: 0, total: 1, ok: 0, fail: 0, pct: 8,
                cancelable: true, title: 'Pushing',
                msg: sku + ' · starting…',
            });

            if (chPromoPushQueueEnabled) {
                applyChannelPushPrcToSpriceRow(row, plan, null);
                queueChannelPushPrcItems([planToChannelPushPrcQueueItem(d, plan)]);
                return;
            }
            runChannelPushPrcSequential([{ row: row, d: d, plan: plan }]);
        }

        function bulkPushChannelPrcSelected() {
            if (typeof table === 'undefined' || !table) {
                chPromoToast('error', 'Load data first');
                return;
            }
            const targets = collectChPromoSelectedRows();
            if (!targets.length) {
                chPromoToast('error', 'Select one or more SKUs first');
                return;
            }
            const ready = [];
            targets.forEach(function(t) {
                const d = t.row.getData();
                const plan = computeChannelPushPrcPlan(d);
                if (plan && plan.std > 0) {
                    ready.push({ row: t.row, d: d, plan: plan });
                }
            });
            const skipped = targets.length - ready.length;
            if (!ready.length) {
                chPromoToast('error', 'Selected SKUs need Std Prc (Push Prc: Std → coupon CPN%)');
                return;
            }
            if (!confirm(
                (chPromoPushQueueEnabled
                    ? ('Queue Push Prc for ' + ready.length + ' selected SKU(s) in background?')
                    : ('Push Prc for ' + ready.length + ' selected SKU(s)?'))
                + (skipped ? ('\n(' + skipped + ' skipped — no Std Prc)') : '')
                + '\n\n1) Push Std Prc as listing price'
                + (chPromoPushPrcHasSaleCoupon()
                    ? '\n2) Coupon campaign from CPN%'
                    : '')
                + (chPromoPushQueueEnabled
                    ? '\n\nSafe to refresh — progress continues. You can select more and queue again.'
                    : (!chPromoCfg.pushPriceUrl ? '\n\n(Push URL not configured — will save promo only)' : ''))
            )) return;

            if (chPromoPushQueueEnabled) {
                const items = ready.map(function(r) {
                    r.row.update({ PUSH_PRC_STATUS: 'processing', push_prc: 'processing' });
                    chPromoRefreshPushCell(r.row, 'push_prc', '.ch-promo-push-prc-btn', 'PUSH_PRC_STATUS', 'Pushing Std → coupon…');
                    applyChannelPushPrcToSpriceRow(r.row, r.plan, null);
                    return planToChannelPushPrcQueueItem(r.d, r.plan);
                });
                if (table) table.redraw(true);
                queueChannelPushPrcItems(items);
                return;
            }

            runChannelPushPrcSequential(ready);
        }

        function clearAndAutopopulateChannelSprice() {
            if (typeof table === 'undefined' || !table) {
                chPromoToast('error', 'Load data first');
                return;
            }
            let targets = collectChPromoSelectedRows();
            let scopeLabel = 'selected';
            if (!targets.length) {
                targets = collectChPromoVisibleRows();
                scopeLabel = 'all visible';
            }
            let skippedInv = 0;
            const ready = targets.filter(function(t) {
                if (chPromoInv(t.d) === 0) {
                    skippedInv++;
                    return false;
                }
                const plan = computeChannelPushPrcPlan(t.d);
                return plan && plan.std > 0;
            });
            if (!ready.length) {
                chPromoToast(
                    'error',
                    skippedInv
                        ? 'No rows to refill (skipped ' + skippedInv + ' with INV = 0)'
                        : 'No rows with Std Prc to refill'
                );
                return;
            }
            const temuCombo = chPromoPrmtCpnComboEnabled();
            const reverbCombo = chPromoReverbComboEnabled();
            if (!confirm(
                'Clear S PRC and refill for ' + ready.length + ' ' + scopeLabel + ' SKU(s)?'
                + (skippedInv ? ('\n(Skip ' + skippedInv + ' with INV = 0)') : '')
                + '\n\nFormula (no marketplace push):\n'
                + (temuCombo || chPromoEbayStdMinusPrmtCpnEnabled()
                    ? 'S PRC = Std × (1 − (PRMT% + CPN%)/100)\n'
                    : (reverbCombo
                        ? 'S PRC = Std × (1 − (PRMT% + 0 Sold PRMT%)/100)\n'
                        : 'S PRC = Std × (1 − PRMT%/100)\n'))
                + 'If no discount → S PRC = Std'
            )) return;

            const $btn = $('#ch-promo-sprice-recalc-btn');
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>…');

            ready.forEach(function(t) {
                t.row.update(Object.assign({
                    SGPFT: 0,
                    'Spft%': 0,
                    SROI: 0,
                    SGROI: 0,
                    has_custom_sprice: false,
                    SPRICE_STATUS: null,
                }, chPromoSpricePatch(0)));
            });
            if (table) table.redraw(true);

            let ok = 0;
            let fail = 0;
            let i = 0;
            function next() {
                if (i >= ready.length) {
                    $btn.prop('disabled', false).html(html);
                    if (table) table.redraw(true);
                    chPromoToast(
                        fail && !ok ? 'error' : 'success',
                        'sprice ?: '
                            + ok + ' filled'
                            + (fail ? (', ' + fail + ' failed') : '')
                            + (skippedInv ? (', ' + skippedInv + ' skipped INV=0') : '')
                    );
                    return;
                }
                const item = ready[i++];
                const rowData = item.row.getData();
                const plan = computeChannelPushPrcPlan(rowData);
                $btn.html('<i class="fas fa-spinner fa-spin"></i> ' + i + '/' + ready.length);
                const fill = chPromoPrmtCpnComboEnabled()
                    ? (chPromoTemuSpriceFromStdPrmtCpn(rowData) || 0)
                    : (chPromoReverbComboEnabled()
                        ? (chPromoReverbSpriceFromStdBothPrmt(rowData) || 0)
                        : (chPromoEbayStdMinusPrmtCpnEnabled()
                            ? (chPromoSpriceFromStdTPromo(rowData) || 0)
                            : chPromoPlanSaleSprice(plan)));
                if (!plan || !(fill > 0)) {
                    fail++;
                    next();
                    return;
                }
                const sku = chPromoSku(item.d);
                saveChannelSprice(sku, fill, true, { row: item.row })
                    .done(function(saveRes) {
                        const refillPatch = {
                            prmt_pct: String(plan.prmt),
                            cpn_pct: String(plan.cpn),
                            _prmt_pct_applied: plan.prmt,
                            _cpn_pct_applied: plan.cpn,
                        };
                        if (chPromoReverbComboEnabled()) {
                            refillPatch.zero_sold_prmt = plan.zeroSold != null ? String(plan.zeroSold) : '';
                            refillPatch._zero_sold_prmt_applied = plan.zeroSold || 0;
                        }
                        item.row.update(Object.assign(refillPatch, chPromoSpricePatch(fill)));
                        if (saveRes && (saveRes.sgpft_percent !== undefined || saveRes.sgprft_percent !== undefined || saveRes.sroi_percent !== undefined)) {
                            item.row.update({
                                SGPFT: saveRes.sgpft_percent,
                                'Spft%': saveRes.spft_percent,
                                SPFT: saveRes.spft_percent,
                                SNPFT: saveRes.spft_percent,
                                SROI: saveRes.sroi_percent,
                                SGROI: saveRes.sgroi_percent,
                                SNROI: saveRes.snroi_percent,
                                sroi_percent: saveRes.sroi_percent,
                                sgprft_percent: saveRes.sgprft_percent != null ? saveRes.sgprft_percent : saveRes.sgpft_percent,
                                spft_percent: saveRes.spft_percent,
                            });
                        }
                        if (typeof applyTemuSpriceRelatedToRow === 'function') {
                            applyTemuSpriceRelatedToRow(item.row, fill, saveRes);
                        } else {
                            try { item.row.reformat(); } catch (e) { /* ignore */ }
                        }
                        const promoSave = { prmt_pct: plan.prmt, cpn_pct: plan.cpn };
                        if (chPromoReverbComboEnabled()) promoSave.zero_sold_prmt = plan.zeroSold || 0;
                        saveChannelPromoFields(sku, promoSave).always(function() {
                            ok++;
                            next();
                        });
                    })
                    .fail(function() {
                        item.row.update(chPromoSpricePatch(fill));
                        if (typeof applyTemuSpriceRelatedToRow === 'function') {
                            applyTemuSpriceRelatedToRow(item.row, fill, null);
                        }
                        fail++;
                        next();
                    });
            }
            next();
        }

        function postChannelEndPromo(url, confirmText, $trigger) {
            if (!confirm(confirmText)) return;
            const $btn = $trigger && $trigger.length ? $trigger : $();
            const html = $btn.html();
            $btn.prop('disabled', true);
            $.ajax({
                url: url,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: { _token: chPromoCsrf() },
            }).done(function(res) {
                chPromoToast((res && res.success) ? 'success' : 'error', (res && res.message) || 'Done');
            }).fail(function(xhr) {
                const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Request failed';
                chPromoToast('error', msg);
            }).always(function() {
                if ($btn.length) $btn.prop('disabled', false).html(html);
            });
        }

        function endAllChannelSales() {
            postChannelEndPromo(
                '/channel-end-sales/' + encodeURIComponent(CHANNEL_PROMO_CHANNEL),
                'End ALL active markdown sale events on ' + (chPromoCfg.label || CHANNEL_PROMO_CHANNEL)
                    + '?\n\nThis sets each sale endDate to now. Listings return to list price.\nNo S PRC or marketplace price push.',
                $('#ch-promo-prmt-menu-btn')
            );
        }

        function endAllChannelCoupons() {
            postChannelEndPromo(
                '/channel-end-coupons/' + encodeURIComponent(CHANNEL_PROMO_CHANNEL),
                'End ALL active coded coupons on ' + (chPromoCfg.label || CHANNEL_PROMO_CHANNEL)
                    + '?\n\nThis pauses every running coupon. No S PRC or marketplace price push.',
                $('#ch-promo-cpn-menu-btn')
            );
        }

        function chPromoEbayStdMinusPrmtCpnEnabled() {
            return true;
        }
        /** Dil→PRMT / CVR→CPN Apply: gate to sold > 0 (eBay E L30, AliExpress AL30). Off for Shein. */
        function chPromoSaleGatedApply() {
            if (CHANNEL_PROMO_CHANNEL === 'shein') {
                return false;
            }
            return true;
        }

        /** True when this channel must not copy Amazon Std onto S PRC / Price with no promo. */
        function chPromoKeepLivePriceWhenNoPromo() {
            return CHANNEL_PROMO_CHANNEL === 'aliexpress';
        }

        /**
         * S PRC = Std × (1 − (PRMT% + CPN%)/100).
         * If both % are 0: eBay-style channels use Std; AliExpress keeps the live listing Price
         * (Std is Amazon's shared list price — do not overwrite AE Price when no promo/coupon exists).
         */
        function chPromoSpriceFromStdTPromo(d) {
            // AliExpress 0-sold: discount Std (not live Price) so SROI ≈ Target ROI%.
            let price = 0;
            if (typeof chPromoAeZeroSoldStdSprice === 'function') {
                const fromStd = chPromoAeZeroSoldStdSprice(d);
                if (fromStd > 0) price = fromStd;
            }
            if (!(price > 0)) {
                const t = Math.min(99.99, Math.max(0, chPromoTPromoPct(d)));
                if (t > 0) {
                    const std = chPromoStdBase(d);
                    if (!(std > 0)) return 0;
                    price = chPromoRound2(std * (1 - t / 100));
                } else if (chPromoKeepLivePriceWhenNoPromo()) {
                    const live = chPromoPrice(d);
                    if (live > 0) price = chPromoRound2(live);
                    else {
                        const existing = chPromoGetSprice(d);
                        price = existing > 0 ? chPromoRound2(existing) : 0;
                    }
                } else {
                    const std = chPromoStdBase(d);
                    if (!(std > 0)) return 0;
                    price = chPromoRound2(std);
                }
            }
            if (!(price >= 0.01)) return 0;
            return chPromoCapSpriceToLmp(d, price);
        }

        /** Recalc S PRC from current Std / PRMT% / cvr%. AliExpress with no promo keeps live Price. */
        function applyChannelSpriceFromStdChange(row, opts) {
            opts = opts || {};
            if (!chPromoEbayStdMinusPrmtCpnEnabled() || !row) return null;
            const d = row.getData();
            if (!chPromoIsChildRow(d)) return null;
            const fill = chPromoSpriceFromStdTPromo(d);
            const sku = chPromoSku(d);
            if (!sku || !(fill > 0)) return null;
            const current = chPromoRound2(chPromoGetSprice(d));
            const hadValue = current > 0;
            if (hadValue && current === fill && opts.persist === false) return null;
            row.update(chPromoSpricePatch(fill));
            try { row.reformat(); } catch (e) { /* ignore */ }
            if (opts.persist === false) return { sku: sku, price: fill, row: row };
            const live = chPromoLivePrice(d);
            const alreadyLive = live > 0 && chPromoNearlyEqual(fill, live);
            if (hadValue && current === fill && alreadyLive) return { sku: sku, price: fill, row: row };
            const extra = {
                skip_push: opts.skip_push === true || alreadyLive,
                queue_push: opts.skip_push !== true && !alreadyLive,
                row: row,
            };
            saveChannelSprice(sku, fill, true, extra).done(function(saveRes) {
                chPromoApplySpriceSavePatch(row, fill, saveRes);
            }).fail(function() {
                chPromoApplySpriceSavePatch(row, fill, null);
            });
            return { sku: sku, price: fill, row: row };
        }

        function chPromoSpriceFromStdPrmtCpnWith(d, override) {
            override = override || {};
            const tmp = Object.assign({}, d);
            if (override.prmt != null) {
                tmp.prmt_pct = override.prmt;
                tmp._prmt_pct_applied = override.prmt;
            }
            if (override.cpn != null) {
                tmp.cpn_pct = override.cpn;
                tmp._cpn_pct_applied = override.cpn;
            }
            return chPromoSpriceFromStdTPromo(tmp);
        }

        let chPromoEbaySpriceAutoBusy = false;
        /** Fill / correct S PRC from Std − PRMT% − CPN%. AliExpress with both % 0 keeps live Price. */
        function autopopulateEbaySpriceFromStdPrmtCpn(opts) {
            opts = opts || {};
            if (!chPromoEbayStdMinusPrmtCpnEnabled()) return;
            if (typeof table === 'undefined' || !table) return;
            if (chPromoEbaySpriceAutoBusy) return;
            const persist = opts.persist !== false;
            const silent = opts.silent !== false;
            const overwrite = opts.overwrite !== false;
            const jobs = [];
            const blocked = typeof table.blockRedraw === 'function';
            if (blocked) table.blockRedraw();
            const queuedKeys = new Set();
            const livePushOn = window._chPushSpriceLiveAllowed !== false;
            function considerRow(row, d) {
                if (!chPromoIsChildRow(d)) return;
                if (chPromoIsEndedListing(d)) return;
                const sku = chPromoSku(d);
                const key = chPromoSkuKey(sku);
                if (!sku || !key || queuedKeys.has(key)) return;
                const fill = chPromoSpriceFromStdTPromo(d);
                if (!(fill > 0)) return;
                const current = chPromoGetSprice(d);
                const live = chPromoLivePrice(d);
                const prmt = chPromoEbayPrmtUsed(d);
                const cpn = chPromoEbayCpnUsed(d);
                const needsFill = !(current > 0) || (overwrite && !chPromoNearlyEqual(current, fill));
                const needsPush = livePushOn
                    && live > 0
                    && !chPromoNearlyEqual(fill, live);
                if (!needsFill && !needsPush) return;
                queuedKeys.add(key);
                const patch = Object.assign(chPromoSpricePatch(fill), {
                    prmt_pct: String(prmt),
                    _prmt_pct_applied: prmt,
                    cpn_pct: String(cpn),
                    _cpn_pct_applied: cpn,
                });
                if (!livePushOn) patch['eBay Price'] = fill;
                if (row && typeof row.update === 'function') {
                    row.update(patch);
                } else if (d) {
                    Object.assign(d, patch);
                }
                chPromoPatchDatasetSprice(sku, patch);
                jobs.push({
                    row: row || null,
                    sku: sku,
                    price: fill,
                    prmt: prmt,
                    cpn: cpn,
                    skip_push: !needsPush,
                });
            }
            try {
                chPromoEachTableRow(function(row, d) { considerRow(row, d); });
                const extra = chPromoPromoDataset();
                if (extra.length) {
                    const rowBySku = {};
                    chPromoEachTableRow(function(row, d) {
                        const k = chPromoSkuKey(chPromoSku(d));
                        if (k) rowBySku[k] = row;
                    });
                    extra.forEach(function(d) {
                        considerRow(rowBySku[chPromoSkuKey(chPromoSku(d))] || null, d);
                    });
                }
            } finally {
                if (blocked) table.restoreRedraw();
            }
            if (!jobs.length) return;
            try { table.redraw(true); } catch (e) { /* ignore */ }
            if (typeof updateSummary === 'function') {
                try { updateSummary(); } catch (e) { /* ignore */ }
            }
            if (!persist) {
                if (!silent) chPromoToast('success', 'S PRC = Std − PRMT% − CPN% → ' + jobs.length + ' SKU(s)');
                return;
            }
            chPromoEbaySpriceAutoBusy = true;
            const pushCount = jobs.filter(function(j) { return !j.skip_push; }).length;
            if (livePushOn && pushCount && typeof enqueueChannelPushSprice === 'function') {
                enqueueChannelPushSprice(
                    jobs.filter(function(j) { return !j.skip_push; })
                        .map(function(j) { return { sku: j.sku, price: j.price }; }),
                    { silent: true }
                );
            }
            const conc = pushCount ? 3 : ((CHANNEL_PROMO_CHANNEL === 'ebay3') ? 12 : 8);
            if (pushCount && silent) {
                chPromoToast('success', 'S PRC queued: ' + pushCount + ' SKU(s) — page close OK');
            }
            chPromoMapLimit(jobs, conc, async function(job) {
                try {
                    const extra = {
                        skip_push: true,
                        queue_push: false,
                        row: job.row,
                        prmt_pct: job.prmt,
                        cpn_pct: job.cpn,
                    };
                    let saveRes;
                    if (job.row) {
                        saveRes = await Promise.resolve(saveChannelSpriceAndPromo(job.row, job.price, true, extra));
                    } else {
                        await Promise.resolve(saveChannelPromoFields(job.sku, {
                            prmt_pct: job.prmt,
                            cpn_pct: job.cpn,
                        })).catch(function() { /* still save S PRC */ });
                        saveRes = await Promise.resolve(saveChannelSprice(job.sku, job.price, true, extra));
                    }
                    chPromoApplySpriceSavePatch(job.row, job.price, saveRes, job.sku);
                } catch (e) {
                    chPromoApplySpriceSavePatch(job.row, job.price, null, job.sku);
                }
            }).then(function() {
                chPromoEbaySpriceAutoBusy = false;
                if (!silent) {
                    chPromoToast('success', 'S PRC = Std − PRMT% − CPN% → ' + jobs.length + ' SKU(s)');
                } else if (pushCount) {
                    chPromoToast('success', 'S PRC queued for ' + pushCount + ' SKU(s) — page close OK');
                } else if (jobs.length) {
                    chPromoToast(
                        'success',
                        window._chPushSpriceLiveAllowed === false
                            ? ('S PRC saved for ' + jobs.length + ' SKU(s) — local Price matched (eBay not revised)')
                            : ('S PRC saved for ' + jobs.length + ' SKU(s)')
                    );
                }
                try { if (typeof table !== 'undefined' && table) table.redraw(true); } catch (e) { /* ignore */ }
                if (typeof updateSummary === 'function') updateSummary();
            }).catch(function() {
                chPromoEbaySpriceAutoBusy = false;
            });
        }

        let chPromoEbaySpriceRulesReady = {
            dil: false,
            cvr: !!CHANNEL_PROMO_HIDE_CVR_CPN,
        };
        function chPromoEbaySpriceSlabsReady() {
            return !!chPromoEbaySpriceRulesReady.dil
                && (!!CHANNEL_PROMO_HIDE_CVR_CPN || !!chPromoEbaySpriceRulesReady.cvr);
        }
        function chPromoMarkEbaySpriceRuleReady(kind) {
            if (kind === 'dil') chPromoEbaySpriceRulesReady.dil = true;
            if (kind === 'cvr') chPromoEbaySpriceRulesReady.cvr = true;
            chPromoTryQueueAfterSlabs();
        }
        setTimeout(function() {
            if (chPromoEbaySpriceRulesReady.dil && chPromoEbaySpriceRulesReady.cvr) return;
            chPromoEbaySpriceRulesReady.dil = true;
            chPromoEbaySpriceRulesReady.cvr = true;
            chPromoTryQueueAfterSlabs();
        }, 4000);
        /** Calc + store S PRC after Dil/CVR slabs load (Amazon-style). Live push stays off on local. */
        function chPromoPageReloadPushAllowed() {
            return chPromoPageReloadPushEnabled !== false;
        }
        function syncChPromoReloadPushSwitchUi() {
            const on = chPromoPageReloadPushAllowed();
            const $wrap = $('#ch-promo-reload-push-wrap');
            const $sw = $('#ch-promo-reload-push-switch');
            $wrap.toggleClass('is-off', !on);
            $('#ch-promo-reload-push-label').text(on ? 'On' : 'Off');
            if ($sw.length && $sw.prop('checked') !== on) $sw.prop('checked', on);
        }
        function saveChPromoPageReloadPush(enabled) {
            chPromoPageReloadPushEnabled = !!enabled;
            syncChPromoReloadPushSwitchUi();
            return $.ajax({
                url: CH_PROMO_RULES_BASE + '/page-reload-push',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: { _token: chPromoCsrf(), enabled: enabled ? 1 : 0 },
            });
        }
        function chPromoTryQueueAfterSlabs() {
            if (!chPromoEbayStdMinusPrmtCpnEnabled()) return;
            if (!chPromoPageReloadPushAllowed()) {
                window._chPushSpriceSlabsQueued = true;
                return;
            }
            if (!chPromoEbaySpriceSlabsReady()) return;
            if (window._chPushSpriceSlabsQueued) return;
            if (typeof table === 'undefined' || !table) {
                setTimeout(chPromoTryQueueAfterSlabs, 400);
                return;
            }
            const n = (typeof table.getDataCount === 'function') ? table.getDataCount() : 0;
            const extraN = (typeof window !== 'undefined' && Array.isArray(window.allTableData))
                ? window.allTableData.length
                : ((typeof allTableData !== 'undefined' && Array.isArray(allTableData))
                    ? allTableData.length
                    : 0);
            if (!(n > 0) && !(extraN > 0)) {
                setTimeout(chPromoTryQueueAfterSlabs, 400);
                return;
            }
            window._chPushSpriceSlabsQueued = true;
            window._chPushSpricePageChecked = true;
            if (typeof chPromoSyncEbayPrmtColumnFromSlabs === 'function') {
                chPromoSyncEbayPrmtColumnFromSlabs();
            }
            setTimeout(function() {
                autopopulateEbaySpriceFromStdPrmtCpn({ persist: true, silent: true });
            }, 150);
        }
        function bindEbaySpriceAutofill() {
            if (!chPromoEbayStdMinusPrmtCpnEnabled()) return;
            if (typeof table === 'undefined' || !table || !table.on) {
                setTimeout(bindEbaySpriceAutofill, 400);
                return;
            }
            if (table._chPromoSpriceAutofillBound) return;
            table._chPromoSpriceAutofillBound = true;
            function runPageLoadSpriceQueue() {
                // Wait for Dil vs PRMT / CVR vs CPN slabs — otherwise fill = Std and the
                // blue-triangle SKUs (Std − slabs) are never queued.
                if (!chPromoEbaySpriceSlabsReady()) {
                    chPromoTryQueueAfterSlabs();
                    return;
                }
                chPromoTryQueueAfterSlabs();
            }
            table.on('dataLoaded', function() {
                chPromoSyncEbayPrmtColumnFromSlabs();
                runPageLoadSpriceQueue();
            });
            try {
                if ((typeof table.getDataCount === 'function' ? table.getDataCount() : 0) > 0) {
                    chPromoSyncEbayPrmtColumnFromSlabs();
                    runPageLoadSpriceQueue();
                }
            } catch (e) { /* wait for dataLoaded */ }
        }

        function fillSpriceFromTPromo() {
            if (CHANNEL_PROMO_CHANNEL === 'ebay1' || CHANNEL_PROMO_CHANNEL === 'ebay2' || CHANNEL_PROMO_CHANNEL === 'ebay3') return;
            if (typeof table === 'undefined' || !table) {
                chPromoToast('error', 'Load data first');
                return;
            }
            let targets = collectChPromoSelectedRows();
            let scopeLabel = 'selected';
            if (!targets.length) {
                targets = collectChPromoVisibleRows();
                scopeLabel = 'all visible';
            }
            let skippedInv = 0;
            let skippedStd = 0;
            const ready = targets.filter(function(t) {
                if (chPromoInv(t.d) === 0) {
                    skippedInv++;
                    return false;
                }
                if (!(chPromoStdBase(t.d) > 0)) {
                    skippedStd++;
                    return false;
                }
                return true;
            });
            if (!ready.length) {
                chPromoToast(
                    'error',
                    skippedInv || skippedStd
                        ? ('No rows to fill (skipped '
                            + (skippedInv ? (skippedInv + ' INV=0') : '')
                            + (skippedInv && skippedStd ? ', ' : '')
                            + (skippedStd ? (skippedStd + ' no Std') : '')
                            + ')')
                        : 'No SKUs to fill'
                );
                return;
            }
            if (!confirm(
                'Autofill S PRC from Std − T Promo for ' + ready.length + ' ' + scopeLabel + ' SKU(s)?'
                + ((skippedInv || skippedStd)
                    ? ('\n(Skip '
                        + (skippedInv ? (skippedInv + ' INV=0') : '')
                        + (skippedInv && skippedStd ? ', ' : '')
                        + (skippedStd ? (skippedStd + ' no Std') : '')
                        + ')')
                    : '')
                + '\n\nS PRC = Std × (1 − T Promo/100)'
                + '\nT Promo = PRMT% + CPN% + CVR Up/Dn'
                + '\nIf T Promo is 0 → S PRC = Std'
                + '\nS PRC > LMP shows a red triangle. No marketplace push.'
            )) return;

            const $btn = $('#ch-promo-sprice-vs-tpromo-btn');
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>…');

            let ok = 0;
            let fail = 0;
            let aboveLmp = 0;
            let i = 0;
            function next() {
                if (i >= ready.length) {
                    $btn.prop('disabled', false).html(html);
                    if (table) table.redraw(true);
                    chPromoToast(
                        fail && !ok ? 'error' : 'success',
                        'Sprice vs T promo: ' + ok + ' filled'
                            + (aboveLmp ? (', ' + aboveLmp + ' above LMP') : '')
                            + (fail ? (', ' + fail + ' failed') : '')
                            + (skippedInv ? (', ' + skippedInv + ' skipped INV=0') : '')
                    );
                    return;
                }
                const item = ready[i++];
                const rowData = item.row.getData();
                const fill = chPromoSpriceFromStdTPromo(rowData);
                $btn.html('<i class="fas fa-spinner fa-spin"></i> ' + i + '/' + ready.length);
                if (!(fill > 0)) {
                    fail++;
                    next();
                    return;
                }
                const sku = chPromoSku(item.d);
                const lmp = Number(rowData.lmp_price) || 0;
                if (lmp > 0 && fill > lmp) aboveLmp++;
                saveChannelSprice(sku, fill, true)
                    .done(function(saveRes) {
                        item.row.update(chPromoSpricePatch(fill));
                        if (saveRes && (saveRes.sgpft_percent !== undefined || saveRes.sroi_percent !== undefined)) {
                            item.row.update({
                                SGPFT: saveRes.sgpft_percent,
                                'Spft%': saveRes.spft_percent,
                                SPFT: saveRes.spft_percent,
                                SNPFT: saveRes.spft_percent,
                                SROI: saveRes.sroi_percent,
                                SGROI: saveRes.sgroi_percent,
                                SNROI: saveRes.snroi_percent,
                                sroi_percent: saveRes.sroi_percent,
                                sgprft_percent: saveRes.sgprft_percent != null ? saveRes.sgprft_percent : saveRes.sgpft_percent,
                                spft_percent: saveRes.spft_percent,
                            });
                        }
                        try { item.row.reformat(); } catch (e) { /* ignore */ }
                        ok++;
                        next();
                    })
                    .fail(function() {
                        item.row.update(chPromoSpricePatch(fill));
                        try { item.row.reformat(); } catch (e) { /* ignore */ }
                        fail++;
                        next();
                    });
            }
            next();
        }

        function clearSpriceFromTPromo() {
            if (CHANNEL_PROMO_CHANNEL === 'ebay1' || CHANNEL_PROMO_CHANNEL === 'ebay2' || CHANNEL_PROMO_CHANNEL === 'ebay3') return;
            if (typeof table === 'undefined' || !table) {
                chPromoToast('error', 'Load data first');
                return;
            }
            let targets = collectChPromoSelectedRows();
            let scopeLabel = 'selected';
            if (!targets.length) {
                targets = collectChPromoVisibleRows();
                scopeLabel = 'all visible';
            }
            const ready = targets.filter(function(t) {
                return chPromoIsChildRow(t.d) && chPromoGetSprice(t.d) > 0;
            });
            if (!ready.length) {
                chPromoToast('info', 'No S PRC values to clear');
                return;
            }
            if (!confirm(
                'Clear S PRC for ' + ready.length + ' ' + scopeLabel + ' SKU(s)?\n\n'
                + 'This undoes Sprice vs T promo (Std − T Promo).\n'
                + 'PRMT% / CPN% stay. No marketplace push.'
            )) return;

            const $btn = $('#ch-promo-sprice-vs-tpromo-del-btn');
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            let ok = 0;
            let fail = 0;
            let i = 0;
            function next() {
                if (i >= ready.length) {
                    $btn.prop('disabled', false).html(html);
                    if (table) table.redraw(true);
                    chPromoToast(
                        fail && !ok ? 'error' : 'success',
                        'Sprice vs T promo delete: ' + ok + ' cleared'
                            + (fail ? (', ' + fail + ' failed') : '')
                    );
                    return;
                }
                const item = ready[i++];
                const sku = chPromoSku(item.d);
                $btn.html('<i class="fas fa-spinner fa-spin"></i> ' + i + '/' + ready.length);
                saveChannelSprice(sku, 0, true)
                    .done(function() {
                        item.row.update(Object.assign({
                            SGPFT: null,
                            'Spft%': null,
                            SPFT: null,
                            SNPFT: null,
                            SROI: null,
                            SGROI: null,
                            SNROI: null,
                            has_custom_sprice: false,
                            SPRICE_STATUS: null,
                        }, chPromoSpricePatch(0)));
                        try { item.row.reformat(); } catch (e) { /* ignore */ }
                        ok++;
                        next();
                    })
                    .fail(function() {
                        fail++;
                        next();
                    });
            }
            next();
        }

        function chPromoStdPrcRound2(n) {
            const v = Number(n);
            return isFinite(v) ? Math.round(v * 100) / 100 : 0;
        }
        function chPromoStdPrcCurrent(d) {
            return chPromoStdPrcRound2(d && (d.STANDARD_PRICE != null ? d.STANDARD_PRICE : d.standard_price));
        }
        function chPromoStdPrcLastPushed(d) {
            return chPromoStdPrcRound2(d && d.PUSH_STD_PRC_VALUE);
        }
        function chPromoStdPrcIsChild(d) {
            if (!d || d.is_parent_summary || d.is_parent_row) return false;
            if (String(d.Parent || '').toUpperCase().startsWith('PARENT')) return false;
            return !!chPromoSku(d);
        }
        function chPromoStdPrcNeedsPush(d) {
            const std = chPromoStdPrcCurrent(d);
            if (!(std > 0)) return false;
            if (String(d.PUSH_STD_PRC_STATUS || '') === 'error') return true;
            const last = chPromoStdPrcLastPushed(d);
            if (!(last > 0)) return true;
            return last.toFixed(2) !== std.toFixed(2);
        }
        function chPromoStdPrcToast(type, msg) {
            if (typeof showToast !== 'function') {
                console.log(type, msg);
                return;
            }
            if (CHANNEL_PROMO_CHANNEL === 'ebay1') showToast(type, msg);
            else showToast(msg, type);
        }
        function chPromoPushStdPrcCollectTargets() {
            if (typeof collectChPromoSelectedRows === 'function') {
                const selected = collectChPromoSelectedRows();
                if (selected.length) return selected;
                if (typeof collectChPromoVisibleRows === 'function') {
                    return collectChPromoVisibleRows();
                }
            }
            if (typeof table === 'undefined' || !table) return [];
            return (table.getRows('active') || []).filter(function(row) {
                const d = row.getData();
                return chPromoStdPrcIsChild(d);
            }).map(function(row) {
                return { row: row, d: row.getData() };
            });
        }
        function chPromoRefreshPushStdPrcCell(row) {
            chPromoRefreshPushCell(row, 'push_std_prc', '.ebay-push-std-prc-btn', 'PUSH_STD_PRC_STATUS', 'Pushing Std Prc to eBay…');
        }
        function chPromoPaintPushStdPrcSpinner(btn) {
            chPromoPaintPushSpinner(btn, 'Pushing Std Prc to eBay…');
        }
        async function chPromoPushStdPrcOne(row, opts) {
            opts = opts || {};
            const silent = !!opts.silent;
            const force = !!opts.force;
            const d = row.getData() || {};
            const sku = chPromoSku(d);
            const std = chPromoStdPrcCurrent(d);
            if (!sku || !(std > 0)) {
                if (!silent) chPromoStdPrcToast('error', 'Std Prc required');
                chPromoRefreshPushStdPrcCell(row);
                return { ok: false, skipped: true };
            }
            if (!force && !chPromoStdPrcNeedsPush(d)) {
                if (!silent) chPromoStdPrcToast('info', 'Std Prc unchanged since last push for ' + sku);
                chPromoRefreshPushStdPrcCell(row);
                return { ok: true, skipped: true };
            }
            row.update({ PUSH_STD_PRC_STATUS: 'processing', push_std_prc: 'processing' });
            chPromoRefreshPushStdPrcCell(row);
            if (!silent) {
                clearTimeout(setChPromoPushPrcProgress._hideTimer);
                setChPromoPushPrcProgress({
                    active: true, done: 0, total: 1, ok: 0, fail: 0, pct: 20,
                    title: 'Pushing',
                    msg: sku + ' · Std $' + std.toFixed(2),
                });
            }
            try {
                const ajax = (typeof pushChannelPriceAjax === 'function')
                    ? pushChannelPriceAjax(sku, std)
                    : $.ajax({
                        url: chPromoCfg.pushPriceUrl || '/push-ebay-price-tabulator',
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': chPromoCsrf() },
                        data: { sku: sku, price: std, _token: chPromoCsrf() }
                    });
                const response = await Promise.resolve(ajax);
                if (response && response.errors && response.errors.length) {
                    throw new Error(response.errors[0].message || 'API error');
                }
                if (response && response.success === false) {
                    throw new Error(response.message || 'API error');
                }
                row.update({
                    PUSH_STD_PRC_STATUS: 'pushed',
                    PUSH_STD_PRC_VALUE: std,
                    'eBay Price': std,
                    push_std_prc: 'pushed'
                });
                chPromoRefreshPushStdPrcCell(row);
                try {
                    await Promise.resolve(saveChannelPromoFields(sku, {
                        record_push_std_prc: 1,
                        push_std_prc_value: std
                    }));
                } catch (saveErr) {
                    console.warn('Push Std Prc listing ok, but last-push record failed', sku, saveErr);
                }
                if (!silent) {
                    setChPromoPushPrcProgress({
                        active: false, done: 1, total: 1, ok: 1, fail: 0, pct: 100,
                        title: 'Pushed',
                        msg: sku + ' · Std $' + std.toFixed(2),
                    });
                    chPromoStdPrcToast('success', 'Std Prc $' + std.toFixed(2) + ' pushed to eBay for ' + sku);
                }
                return { ok: true };
            } catch (e) {
                row.update({ PUSH_STD_PRC_STATUS: 'error', push_std_prc: 'error' });
                chPromoRefreshPushStdPrcCell(row);
                if (!silent) {
                    const msg = (e && e.responseJSON && (e.responseJSON.message || (e.responseJSON.errors && e.responseJSON.errors[0] && e.responseJSON.errors[0].message)))
                        || (e && e.message)
                        || 'Failed to push Std Prc';
                    setChPromoPushPrcProgress({
                        active: false, done: 1, total: 1, ok: 0, fail: 1, pct: 100,
                        title: 'Push failed',
                        msg: sku + ' · ' + msg,
                    });
                    chPromoStdPrcToast('error', msg + ' (' + sku + ')');
                }
                return { ok: false };
            }
        }
        let chPromoPushStdPrcBusy = false;
        async function chPromoBulkPushStdPrcChanged() {
            if (chPromoPushStdPrcBusy) {
                chPromoStdPrcToast('info', 'Push Std Prc already running');
                return;
            }
            const all = chPromoPushStdPrcCollectTargets();
            const targets = all.filter(function(t) { return chPromoStdPrcNeedsPush(t.d); });
            const skipped = all.length - targets.length;
            if (!targets.length) {
                chPromoStdPrcToast('info', skipped
                    ? ('No Std Prc changes since last push (' + skipped + ' unchanged)')
                    : 'No SKUs to push');
                all.forEach(function(t) { chPromoRefreshPushStdPrcCell(t.row); });
                return;
            }
            if (!confirm(
                'Push Std Prc to eBay for ' + targets.length + ' SKU(s) changed since last push'
                + (skipped ? (' (' + skipped + ' unchanged skipped)') : '') + '?'
            )) {
                all.forEach(function(t) { chPromoRefreshPushStdPrcCell(t.row); });
                return;
            }
            chPromoPushStdPrcBusy = true;
            targets.forEach(function(t) {
                t.row.update({ PUSH_STD_PRC_STATUS: 'processing', push_std_prc: 'processing' });
                chPromoRefreshPushStdPrcCell(t.row);
            });
            let ok = 0, fail = 0;
            clearTimeout(setChPromoPushPrcProgress._hideTimer);
            setChPromoPushPrcProgress({
                active: true, done: 0, total: targets.length, ok: 0, fail: 0, pct: 5,
                title: 'Pushing',
                msg: 'Starting ' + targets.length + ' Std Prc…',
            });
            try {
                for (let i = 0; i < targets.length; i++) {
                    const sku = chPromoSku(targets[i].d);
                    setChPromoPushPrcProgress({
                        active: true, done: i, total: targets.length, ok: ok, fail: fail,
                        title: 'Pushing',
                        msg: sku + ' · Std Prc',
                    });
                    const res = await chPromoPushStdPrcOne(targets[i].row, { silent: true });
                    if (res && res.ok && !res.skipped) ok++;
                    else if (!(res && res.skipped)) fail++;
                }
            } finally {
                chPromoPushStdPrcBusy = false;
                setChPromoPushPrcProgress({
                    active: false, done: targets.length, total: targets.length, ok: ok, fail: fail, pct: 100,
                    title: fail && !ok ? 'Push failed' : 'Pushed',
                    msg: ok + ' ok' + (fail ? (' · ' + fail + ' failed') : ''),
                });
            }
            chPromoStdPrcToast(fail ? 'error' : 'success',
                'Push Std Prc: ' + ok + ' pushed'
                + (fail ? (', ' + fail + ' failed') : '')
                + (skipped ? (', ' + skipped + ' unchanged skipped') : ''));
        }

        /** Push Std Prc column — same as eBay 1. Place after STANDARD_PRICE. */
        function channelPromoPushStdPrcColumn() {
            return {
                title: 'Push Std Prc',
                field: 'push_std_prc',
                width: 72,
                hozAlign: 'center',
                vertAlign: 'middle',
                headerSort: false,
                headerTooltip: 'Push Std Prc — send Std to the live eBay listing price. Only SKUs whose Std changed since the last push are sent. Click this header to bulk selected (or visible) SKUs.',
                titleFormatter: function() {
                    return chPromoHeaderWithDelete(
                        'Push Std Prc',
                        'ebay-push-std-prc-header-btn',
                        'ebay-push-std-prc-header-del',
                        'Bulk Push Std Prc for selected SKUs whose Std changed since last push',
                        'Hide the Push Std Prc column'
                    );
                },
                headerClick: function(e) {
                    if (e.target.closest('.ebay-push-std-prc-header-del')) {
                        e.stopPropagation();
                        e.preventDefault();
                        hideChannelPushStdPrcColumn();
                        return false;
                    }
                    if (e.target.closest('.ebay-push-std-prc-header-btn')) {
                        e.stopPropagation();
                        e.preventDefault();
                        chPromoBulkPushStdPrcChanged();
                        return false;
                    }
                },
                formatter: function(cell) {
                    const d = cell.getRow().getData() || {};
                    if (!chPromoStdPrcIsChild(d)) return '';
                    const sku = chPromoSku(d);
                    const std = chPromoStdPrcCurrent(d);
                    if (!(std > 0)) {
                        return '<span style="color:#adb5bd;" title="Std Prc required">—</span>';
                    }
                    const status = String(d.PUSH_STD_PRC_STATUS || '');
                    const last = chPromoStdPrcLastPushed(d);
                    const needs = chPromoStdPrcNeedsPush(d);
                    let icon = '<i class="fas fa-upload"></i>';
                    let color = '#FF9900';
                    let tip = 'Push Std $' + std.toFixed(2) + ' to eBay Price';
                    if (status === 'processing') {
                        icon = '<i class="fas fa-spinner fa-spin" style="font-size:14px;"></i>';
                        color = '#ffc107';
                        tip = 'Pushing Std Prc to eBay…';
                    } else if (status === 'error') {
                        icon = '<i class="fa-solid fa-xmark"></i>';
                        color = '#dc3545';
                        tip = 'Last Push Std Prc failed — click to retry';
                    } else if (!needs) {
                        icon = '<i class="fa-solid fa-check-double"></i>';
                        color = '#28a745';
                        tip = 'Already pushed $' + last.toFixed(2)
                            + ' — click to push Std to eBay Price again';
                    } else if (last > 0) {
                        tip = 'Std changed $' + last.toFixed(2) + ' → $' + std.toFixed(2)
                            + ' — click to push to eBay Price';
                    }
                    const canDel = status === 'pushed' || last > 0;
                    return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:2px;">'
                        + '<button type="button" class="btn btn-sm p-0 ebay-push-std-prc-btn" '
                        + 'data-sku="' + chPromoEscAttr(sku) + '" '
                        + 'data-price="' + std.toFixed(2) + '" '
                        + 'title="' + chPromoEscAttr(tip) + '" '
                        + 'style="border:none;background:none;cursor:pointer;color:' + color
                        + ';padding:0;line-height:1;vertical-align:middle;">'
                        + icon + '</button>'
                        + (canDel && status !== 'processing'
                            ? chPromoDeleteIconBtn('ebay-push-std-prc-del', sku, 'Clear this Push Std Prc checkmark')
                            : '')
                        + '</span>';
                },
                cellClick: function(e, cell) {
                    const del = e.target.closest('.ebay-push-std-prc-del');
                    if (del) {
                        e.stopPropagation();
                        e.preventDefault();
                        deleteChannelStdPrcStatusOne(cell.getRow());
                        return false;
                    }
                    const btn = e.target.closest('.ebay-push-std-prc-btn');
                    if (!btn) return;
                    e.stopPropagation();
                    e.preventDefault();
                    if (btn.disabled) return false;
                    const d = cell.getRow().getData() || {};
                    if (String(d.PUSH_STD_PRC_STATUS || '') === 'processing') return false;
                    const selected = collectChPromoSelectedRows();
                    const clickedKey = chPromoSkuKey(chPromoSku(d));
                    if (selected.length > 1 && selected.some(function(t) {
                        return chPromoSkuKey(chPromoSku(t.d)) === clickedKey;
                    })) {
                        chPromoPaintPushStdPrcSpinner(btn);
                        chPromoBulkPushStdPrcChanged();
                        return false;
                    }
                    chPromoPaintPushStdPrcSpinner(btn);
                    chPromoPushStdPrcOne(cell.getRow(), { force: true });
                    return false;
                },
            };
        }

        function channelPromoB2bDiscountColumn() {
            return {
                title: 'B2B disc',
                field: 'b2b_discount',
                width: 78,
                hozAlign: 'center',
                vertAlign: 'middle',
                headerSort: true,
                headerTooltip: 'Auto from Dil% (0–0% → 12, 0.1–2% → 11, … 22%+ → 0). INV = 0 → 0. Same rule as the B2B discount modal.',
                formatter: function(cell) {
                    const d = cell.getRow().getData() || {};
                    if (d.is_parent_summary || !chPromoIsChildRow(d)) return '';
                    const val = chPromoB2bDiscForRow(d);
                    return '<span class="ch-pef-promo-cell has-val" style="color:#198754;" title="Auto B2B discount from Dil%">'
                        + val + '</span>';
                },
                sorter: function(a, b, aRow, bRow) {
                    const av = chPromoB2bDiscForRow(aRow.getData() || {});
                    const bv = chPromoB2bDiscForRow(bRow.getData() || {});
                    return av - bv;
                },
            };
        }
        function channelPromoPricingColumns() {
            return [
                ...(CHANNEL_PROMO_CHANNEL === 'shopify_b2b' ? [channelPromoB2bDiscountColumn()] : []),
                {
                    title: 'PRMT %',
                    field: 'prmt_pct',
                    width: 72,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: true,
                    sorter: function(a, b, aRow, bRow) {
                        const valOf = function(row) {
                            const d = (row && row.getData) ? (row.getData() || {}) : {};
                            if (CHANNEL_PROMO_CHANNEL === 'aliexpress'
                                && typeof chPromoHasSaleQty === 'function'
                                && !chPromoHasSaleQty(d)
                                && !(typeof chPromoKeepZeroSoldPrcSprice === 'function' && chPromoKeepZeroSoldPrcSprice(d))) {
                                return 0;
                            }
                            if (chPromoUsesLiveDilPrmtSlabs()) {
                                const slab = chPromoEbaySlabPrmt(d);
                                if (slab != null) return Number(slab) || 0;
                            }
                            return Number(d.prmt_pct != null && d.prmt_pct !== ''
                                ? d.prmt_pct : d._prmt_pct_applied) || 0;
                        };
                        return valOf(aRow) - valOf(bRow);
                    },
                    editable: function(cell) {
                        return chPromoIsChildRow(cell.getRow().getData());
                    },
                    editor: 'input',
                    headerTooltip: chPromoUsesAmazonDilPrmtSlabs()
                        ? '% less on S PRC. Amazon Dil vs PRMT: 0–10% → 10 … > 100% → 0. INV=0 → 0. Dot = PDT daily history.'
                        : '% less on S PRC. eBay: listing Dil slab from Dil vs PRMT (same as the modal). Dot = PDT daily history.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent_summary) return '';
                        const sku = chPromoSku(d);
                        let val = cell.getValue();
                        let tip = '';
                        if (CHANNEL_PROMO_CHANNEL === 'aliexpress'
                            && typeof chPromoHasSaleQty === 'function'
                            && !chPromoHasSaleQty(d)) {
                            if (typeof chPromoKeepZeroSoldPrcSprice === 'function' && chPromoKeepZeroSoldPrcSprice(d)) {
                                tip = '0 sold vs ROI (AL30 = 0)';
                            } else {
                                val = 0;
                                tip = 'Dil vs PRMT skipped — AL30 = 0 (use 0 sold vs ROI)';
                            }
                        } else if (chPromoUsesLiveDilPrmtSlabs()) {
                            const slab = chPromoEbaySlabPrmt(d);
                            if (slab != null) {
                                val = slab;
                                const dil = chPromoDil(d);
                                const key = chPromoDilSlabKey(dil);
                                tip = 'Dil ' + (isFinite(dil) ? (Math.round(dil * 10) / 10) : 0)
                                    + '% → ' + key + ' slab → PRMT ' + slab;
                            }
                        }
                        const dot = chPromoHistoryDotHtml(sku, 'prmt', val);
                        return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:3px;"'
                            + (tip ? (' title="' + chPromoEscAttr(tip) + '"') : '') + '>'
                            + dot + fmtChPromoCell(val, '%') + '</span>';
                    },
                    cellClick: function(e) {
                        const el = e.target.closest('.view-sku-chart') || e.target.closest('.ch-pef-hist-dot');
                        if (!el) return;
                        e.stopPropagation();
                        e.preventDefault();
                        if (typeof openEbaySkuHistoryChart === 'function') {
                            openEbaySkuHistoryChart(el.getAttribute('data-sku'), el.getAttribute('data-metric') || 'prmt');
                        }
                        return false;
                    },
                    cellEdited: function(cell) {
                        applyChPromoFromCell(cell, 'prmt');
                    },
                },
                ...(CHANNEL_PROMO_SHOW_ZERO_SOLD_RULES && !chPromoZeroSoldUsesAmazonPrice() ? [{
                    title: '0 Sold PRMT%',
                    field: 'zero_sold_prmt',
                    width: 64,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: true,
                    sorter: 'number',
                    headerTooltip: '0 Sold Dil color PRMT% (' + chPromoSoldFieldLabel() + ' = 0). Red <25%, Green 25–50%, Pink 50%+.',
                    editable: function(cell) {
                        const d = cell.getRow().getData() || {};
                        return chPromoIsChildRow(d) && chPromoIsZeroSoldRow(d) && chPromoInv(d) > 0;
                    },
                    editor: 'input',
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent_summary || !chPromoIsZeroSoldRow(d)) return '';
                        if (chPromoInv(d) === 0) {
                            return '<span style="color:#6c757d;">0</span>';
                        }
                        const band = chPromoDilColorBand(chPromoDil(d));
                        const hex = chPromoDilColorHex(band);
                        let val = cell.getValue();
                        if (val === null || val === undefined || val === '') {
                            val = (d._zero_sold_prmt_applied != null && d._zero_sold_prmt_applied !== '')
                                ? d._zero_sold_prmt_applied
                                : '';
                        }
                        const n = Number(val);
                        const shown = isFinite(n) ? String(n) : '';
                        const label = band === 'red' ? 'Red' : (band === 'green' ? 'Green' : 'Pink');
                        return '<span class="ch-pef-promo-cell has-val" style="color:' + hex
                            + ';font-weight:600;" title="0 Sold · ' + label + ' Dil color">'
                            + (shown === '' ? '—' : shown) + '</span>';
                    },
                    cellEdited: function(cell) {
                        const row = cell.getRow();
                        const d = row.getData() || {};
                        if (!chPromoIsZeroSoldRow(d)) return;
                        const promo = parseChPromoPercentAllowZero(cell.getValue());
                        if (!promo) {
                            if (String(cell.getValue() == null ? '' : cell.getValue()).trim() !== '') {
                                chPromoToast('error', 'Enter 0 Sold PRMT% (e.g. 10)');
                            }
                            return;
                        }
                        const zeroSold = promo.value;
                        const newPrice = chPromoKeepZeroSoldPrcSprice(d)
                            ? 0
                            : chPromoReverbSpriceFromStdBothPrmt(d, { zeroSold: zeroSold });
                        const patch = {
                            zero_sold_prmt: String(zeroSold),
                            _zero_sold_prmt_applied: zeroSold,
                        };
                        if (newPrice > 0) Object.assign(patch, chPromoSpricePatch(newPrice));
                        row.update(patch);
                        saveChannelSpriceAndPromo(row, newPrice || 0, true, { zero_sold_prmt: zeroSold });
                    },
                }] : []),
                ...(CHANNEL_PROMO_SHOW_GT_SOLD_RULES ? [{
                    title: '>0 Sold %',
                    field: 'gt_sold_pct',
                    width: 72,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: true,
                    headerTooltip: '>0 Sold Dil color (' + chPromoSoldFieldLabel()
                        + ' > 0). Red <25% → Amazon Price. Green / Pink → Increase or Decrease from Std.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent_summary || !chPromoIsGtSoldRow(d)) return '';
                        if (chPromoInv(d) === 0) {
                            return '<span style="color:#6c757d;">0</span>';
                        }
                        const rule = chPromoGtSoldRuleForRow(d);
                        if (!rule) return '';
                        const band = chPromoDilColorBand(chPromoDil(d));
                        const hex = chPromoDilColorHex(band);
                        const label = band === 'red' ? 'Red' : (band === 'green' ? 'Green' : 'Pink');
                        if (chPromoGtSoldIsAmazonRule(rule)) {
                            return '<span class="ch-pef-promo-cell has-val" style="color:' + hex
                                + ';font-weight:600;" title=">0 Sold · Red Dil <25% → Amazon Price">A Price</span>';
                        }
                        const pct = Number(rule.pct) || 0;
                        const sign = rule.dir === 'decrease' ? '−' : '+';
                        return '<span class="ch-pef-promo-cell has-val" style="color:' + hex
                            + ';font-weight:600;" title=">0 Sold · ' + label + ' Dil · '
                            + (rule.dir === 'decrease' ? 'Decrease' : 'Increase') + ' ' + pct
                            + '% from Std Prc">'
                            + sign + pct + '%</span>';
                    },
                }] : []),
                ...(CHANNEL_PROMO_CHANNEL === 'ebay2op'
                    ? [channelPromoPushPrmtColumn()]
                    : []),
                ...(CHANNEL_PROMO_HIDE_CVR_CPN ? [] : [{
                    title: 'cvr %',
                    field: 'cpn_pct',
                    width: 70,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: true,
                    sorter: 'number',
                    editable: function(cell) {
                        return chPromoIsChildRow(cell.getRow().getData());
                    },
                    editor: 'input',
                    headerTooltip: '% less on S PRC. Also filled by CVR vs CPN. Dot = PDT daily history.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent_summary) return '';
                        const sku = chPromoSku(d);
                        const val = cell.getValue();
                        const dot = chPromoHistoryDotHtml(sku, 'cpn', val);
                        return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:3px;">'
                            + dot + fmtChPromoCell(val, '%') + '</span>';
                    },
                    cellClick: function(e) {
                        const el = e.target.closest('.view-sku-chart') || e.target.closest('.ch-pef-hist-dot');
                        if (!el) return;
                        e.stopPropagation();
                        e.preventDefault();
                        if (typeof openEbaySkuHistoryChart === 'function') {
                            openEbaySkuHistoryChart(el.getAttribute('data-sku'), el.getAttribute('data-metric') || 'cpn');
                        }
                        return false;
                    },
                    cellEdited: function(cell) {
                        applyChPromoFromCell(cell, 'cpn');
                    },
                }]),
                ...(chPromoCvrUpDnEnabled() && typeof cvrUpDnColumn === 'function' ? [cvrUpDnColumn()] : []),
                ...(chPromoCvrUpDnEnabled() && typeof tDiscountsColumn === 'function'
                    ? [tDiscountsColumn(function(d) { return chPromoTPromoPct(d); })]
                    : []),
                ...(!CHANNEL_PROMO_HIDE_CVR_CPN && CHANNEL_PROMO_CHANNEL === 'ebay2op'
                    ? [channelPromoPushCpnColumn()]
                    : []),
                ...(CHANNEL_PROMO_CHANNEL === 'temu' ? [{
                    title: 'Push CPN',
                    field: 'push_cpn',
                    width: 72,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: true,
                    headerTooltip: CHANNEL_PROMO_CHANNEL === 'temu'
                        ? 'Push CPN — queue this CPN% (bulk from header). Worker saves coupon % and pushes S PRC to Temu.'
                        : 'Push CPN — create the public coded coupon for this CPN% if needed, then add this SKU. If CPN% changed, removes from the old coupon first. Click header to bulk selected (or visible) SKUs.',
                    titleFormatter: function() {
                        return chPromoHeaderWithDelete(
                            'Push CPN',
                            'ch-promo-push-cpn-header-btn',
                            'ch-promo-push-cpn-header-del',
                            'Bulk Push CPN for selected SKUs whose CPN% changed',
                            'Hide the Push CPN column'
                        );
                    },
                    headerClick: function(e) {
                        if (e.target.closest('.ch-promo-push-cpn-header-del')) {
                            e.stopPropagation();
                            e.preventDefault();
                            hideChannelPushCpnColumn();
                            return false;
                        }
                        if (e.target.closest('.ch-promo-push-cpn-header-btn')) {
                            e.stopPropagation();
                            e.preventDefault();
                            bulkPushChannelCpn();
                            return false;
                        }
                    },
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (!chPromoIsChildRow(d)) return '';
                        const sku = chPromoSku(d);
                        const cpn = chPromoCpnInt(d);
                        const last = chPromoLastCouponPct(d);
                        const status = String(d.PUSH_CPN_STATUS || '');
                        const code = d.PEF_COUPON_CODE || d.coupon_code || '';
                        if (cpn > 0 && (cpn < 5 || cpn > 80)) {
                            return '<span style="color:#adb5bd;" title="CPN% must be 5–80">—</span>';
                        }
                        if (cpn === 0 && last < 5 && status !== 'error') {
                            return '<span style="color:#adb5bd;" title="Set CPN% then click to push">—</span>';
                        }
                        let icon = '<i class="fas fa-upload"></i>';
                        let color = '#FF9900';
                        let tip = cpn > 0
                            ? (CHANNEL_PROMO_CHANNEL === 'temu'
                                ? ('Queue ' + cpn + '% CPN and push S PRC for this SKU')
                                : ('Push ' + cpn + '% public coupon (SAVE' + String(cpn).padStart(2, '0') + 'PCT) for this SKU'))
                            : (CHANNEL_PROMO_CHANNEL === 'temu' ? 'Clear pushed CPN% for this SKU' : 'Remove this SKU from coupons');
                        if (status === 'processing') {
                            icon = '<i class="fas fa-spinner fa-spin"></i>';
                            color = '#ffc107';
                            tip = 'Pushing coupon…';
                        } else if (status === 'error') {
                            icon = '<i class="fa-solid fa-xmark"></i>';
                            color = '#dc3545';
                            tip = 'Last Push CPN failed — click to retry';
                        } else if (!chPromoCpnNeedsPush(d)) {
                            icon = '<i class="fa-solid fa-check-double"></i>';
                            color = '#28a745';
                            tip = 'On ' + last + '% coupon'
                                + (code ? (' (' + code + ')') : '')
                                + ' — click to push again';
                        } else if (last >= 5 && cpn > 0 && last !== cpn) {
                            tip = 'CPN% changed ' + last + '% → ' + cpn
                                + '% — click to remove from old coupon and push to ' + cpn + '%';
                        } else if (cpn === 0 && last >= 5) {
                            icon = '<i class="fa-solid fa-xmark"></i>';
                            color = '#dc3545';
                            tip = 'CPN% is 0 — click to remove from ' + last + '% coupon';
                        }
                        const onCpn = last >= 5 || status === 'pushed';
                        return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:2px;">'
                            + '<button type="button" class="btn btn-sm p-0 ch-promo-push-cpn-col-btn" '
                            + 'data-sku="' + chPromoEscAttr(sku) + '" '
                            + 'title="' + chPromoEscAttr(tip) + '" '
                            + 'style="border:none;background:none;cursor:pointer;color:' + color
                            + ';padding:0;line-height:1;vertical-align:middle;">'
                            + icon + '</button>'
                            + (onCpn && status !== 'processing'
                                ? chPromoDeleteIconBtn('ch-promo-push-cpn-del', sku, 'Remove this SKU from coupons')
                                : '')
                            + '</span>';
                    },
                    cellClick: function(e, cell) {
                        const del = e.target.closest('.ch-promo-push-cpn-del');
                        if (del) {
                            e.stopPropagation();
                            e.preventDefault();
                            deleteChannelCpnOne(cell.getRow());
                            return false;
                        }
                        const btn = e.target.closest('.ch-promo-push-cpn-col-btn');
                        if (!btn) return;
                        e.stopPropagation();
                        e.preventDefault();
                        if (btn.disabled) return false;
                        const d = cell.getRow().getData() || {};
                        if (String(d.PUSH_CPN_STATUS || '') === 'processing') return false;
                        const selected = collectChPromoSelectedRows();
                        const clickedKey = chPromoSkuKey(chPromoSku(d));
                        if (selected.length > 1 && selected.some(function(t) {
                            return chPromoSkuKey(chPromoSku(t.d)) === clickedKey;
                        })) {
                            bulkPushChannelCpn();
                            return false;
                        }
                        pushChannelCpnOne(cell.getRow());
                        return false;
                    },
                }] : []),
            ];
        }

        function initChannelPromoPricingUi() {
            syncChPromoReloadPushSwitchUi();
            $('#ch-promo-reload-push-switch').off('change.chpromoReload').on('change.chpromoReload', function() {
                const on = !!this.checked;
                const prev = chPromoPageReloadPushAllowed();
                saveChPromoPageReloadPush(on)
                    .done(function() {
                        chPromoToast(
                            'success',
                            on
                                ? 'Reload push on — this page will auto-push S PRC on the next reload. Cron is unchanged.'
                                : 'Reload push off — this page will not auto-push on reload. Daily cron still pushes.'
                        );
                    })
                    .fail(function(xhr) {
                        chPromoPageReloadPushEnabled = prev;
                        syncChPromoReloadPushSwitchUi();
                        chPromoToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Could not save reload-push switch');
                    });
            });
            if (typeof loadChPromoDilPrmtRules === 'function') loadChPromoDilPrmtRules();
            if (CHANNEL_PROMO_SHOW_ZERO_SOLD_DIL_RULE && typeof loadChPromoZeroSoldDilRules === 'function') {
                Promise.resolve(loadChPromoZeroSoldDilRules()).then(function() {
                    if (typeof table !== 'undefined' && table) {
                        try { table.redraw(true); } catch (e) { /* ignore */ }
                    }
                }).catch(function() { /* defaults */ });
            }
            if (!CHANNEL_PROMO_HIDE_CVR_CPN && typeof loadChPromoCvrCpnRules === 'function') {
                Promise.resolve(loadChPromoCvrCpnRules()).catch(function() { /* defaults */ });
            }

            $('#ch-promo-push-prc-cancel-btn').off('click.chpromo').on('click.chpromo', function(e) {
                e.preventDefault();
                if (chPromoPushPrmtPollTimer) {
                    cancelChannelPushPrmtJob();
                    return;
                }
                if (chPromoPushCpnPollTimer) {
                    cancelChannelPushCpnJob();
                    return;
                }
                if (chPromoPushQueueEnabled) {
                    cancelChannelPushPrcJob();
                    return;
                }
                if (!confirm('Cancel remaining Push Prc?')) return;
                chPromoPushPrcCancel = true;
                chPromoToast('success', 'Push Prc cancel requested');
            });

            // Resume background Push Prc progress after refresh (eBay queue)
            if (chPromoPushQueueEnabled) {
                pollChannelPushPrcStatus();
                $.ajax({
                    url: CH_PROMO_PUSH_QUEUE_URL + '/status',
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    timeout: 15000,
                }).done(function(resp) {
                    if (resp && resp.active) startChannelPushPrcPoll();
                });
            }

            if (chPromoPushPrmtQueueEnabled) {
                pollChannelPushPrmtStatus();
                $.ajax({
                    url: CH_PROMO_PUSH_PRMT_QUEUE_URL + '/status',
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    timeout: 15000,
                }).done(function(resp) {
                    if (resp && resp.active) startChannelPushPrmtPoll();
                    else if (resp && resp.tasks && resp.tasks.length) {
                        applyChannelPushPrmtTaskStatusesToTable(resp.tasks);
                    }
                });
                (function bindPrmtStatusReplay() {
                    if (typeof table === 'undefined' || !table || !table.on) {
                        setTimeout(bindPrmtStatusReplay, 400);
                        return;
                    }
                    if (table._chPromoPrmtReplayBound) return;
                    table._chPromoPrmtReplayBound = true;
                    table.on('dataLoaded', function() {
                        if (chPromoPushPrmtLastTasks && chPromoPushPrmtLastTasks.length) {
                            applyChannelPushPrmtTaskStatusesToTable(chPromoPushPrmtLastTasks);
                        } else {
                            pollChannelPushPrmtStatus();
                        }
                    });
                    table.on('renderComplete', function() {
                        if (!chPromoPushPrmtLastTasks || !chPromoPushPrmtLastTasks.length) return;
                        chPromoEachTableRow(function(row, d) {
                            if (String(d.PUSH_SALE_STATUS || '') === 'processing') {
                                chPromoRefreshPushCell(row, 'push_prmt', '.ch-promo-push-prmt-btn', 'PUSH_SALE_STATUS', 'Pushing PRMT%…');
                            }
                        });
                    });
                })();
            }

            if (chPromoPushCpnQueueEnabled) {
                pollChannelPushCpnStatus();
                $.ajax({
                    url: CH_PROMO_PUSH_CPN_QUEUE_URL + '/status',
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    timeout: 15000,
                }).done(function(resp) {
                    if (resp && resp.active) startChannelPushCpnPoll();
                });
            }

            $('#ch-promo-dil-vs-prmt-btn').off('click.chpromo').on('click.chpromo', function(e) {
                e.preventDefault();
                const modalEl = document.getElementById('chPromoDilVsPrmtModal');
                if (!modalEl) return;
                if (chPromoIsEbayChannel()) {
                    const help = document.getElementById('ch-promo-dil-prmt-help');
                    if (help) {
                        help.innerHTML = 'Map Dil% slabs to PRMT% (<strong>0–0</strong>, <strong>0.1–2</strong>, '
                            + '<strong>2–4</strong> … <strong>24–26</strong>). On eBay, Dil is <strong>listing-wise</strong> '
                            + '(Σ OV L30 ÷ Σ INV by variation item id). Changing <strong>any</strong> slab number '
                            + 'immediately fills the <strong>PRMT %</strong> column. <strong>Apply</strong> saves rules '
                            + 'and writes PRMT% on selected or visible SKUs with <strong>eBay sale (E L30) &gt; 0</strong>. '
                            + 'Does <strong>not</strong> create an eBay sale event. '
                            + 'If the listing’s total INV is 0, SKU PRMT% is <strong>0</strong>.';
                    }
                } else if (chPromoUsesAmazonDilPrmtSlabs()) {
                    const help = document.getElementById('ch-promo-dil-prmt-help');
                    if (help) {
                        help.innerHTML = 'Same Dil vs PRMT map as <strong>Amazon</strong>: '
                            + '<strong>0–10% → 10</strong>, <strong>10–20% → 9</strong> … '
                            + '<strong>90–100% → 1</strong>, <strong>&gt; 100% → 0</strong>. '
                            + 'Dil is <strong>SKU-wise</strong> (OV L30 ÷ INV). '
                            + 'Changing any slab fills the <strong>PRMT %</strong> column. '
                            + '<strong>Save and Apply</strong> writes PRMT% on selected rows, or all visible SKUs. '
                            + 'If <strong>INV = 0</strong>, PRMT% is <strong>0</strong>.';
                    }
                } else if (CHANNEL_PROMO_CHANNEL === 'temu' || CHANNEL_PROMO_CHANNEL === 'temu2') {
                    const help = document.getElementById('ch-promo-dil-prmt-help');
                    if (help) {
                        help.innerHTML = 'Map Dil% slabs to PRMT%. On Temu, Dil is <strong>SKU-wise</strong> '
                            + '(OV L30 ÷ INV). <strong>Apply</strong> writes <strong>PRMT %</strong> '
                            + 'on each selected or visible SKU — parent rows are not changed. '
                            + 'If INV is 0, PRMT% is <strong>0</strong>.';
                    }
                } else if (CHANNEL_PROMO_CHANNEL === 'aliexpress' || CHANNEL_PROMO_CHANNEL === 'shein') {
                    const help = document.getElementById('ch-promo-dil-prmt-help');
                    if (help) {
                        help.innerHTML = 'Map Dil% slabs to PRMT%. Dil is <strong>SKU-wise</strong> '
                            + '(OV L30 ÷ INV). Changing any slab number fills the <strong>PRMT %</strong> column. '
                            + '<strong>Save and Apply</strong> saves rules and writes PRMT% on <strong>selected rows</strong> '
                            + 'if checked, otherwise <strong>all visible SKUs</strong>'
                            + (CHANNEL_PROMO_CHANNEL === 'aliexpress'
                                ? ' with <strong>AL30 &gt; 0</strong> only. If <strong>AL30 = 0</strong>, this rule is skipped (use <strong>0 sold vs ROI</strong>).'
                                : ' (no AL30 filter — row checkboxes not required).')
                            + ' If INV is 0, PRMT% is <strong>0</strong>.';
                    }
                } else if (CHANNEL_PROMO_SHOW_ZERO_SOLD_RULES) {
                    const help = document.getElementById('ch-promo-dil-prmt-help');
                    if (help) {
                        help.innerHTML = 'Map Dil% slabs to PRMT% for <strong>sold</strong> SKUs ('
                            + chPromoSoldFieldLabel() + ' &gt; 0). '
                            + 'Dil is SKU-wise (OV L30 ÷ INV). <strong>0 Sold</strong> uses the separate '
                            + '<strong>0 Sold</strong> button'
                            + (chPromoZeroSoldUsesAmazonPrice()
                                ? ' (Apply Amazon Price to S PRC)'
                                : ' (Dil Color Red / Green / Pink)')
                            + '. '
                            + '<strong>Apply</strong> writes <strong>PRMT %</strong> on each selected or visible sold SKU. '
                            + 'If INV is 0, PRMT% is <strong>0</strong>.';
                    }
                } else if (CHANNEL_PROMO_CHANNEL === 'reverb') {
                    const help = document.getElementById('ch-promo-dil-prmt-help');
                    if (help) {
                        help.innerHTML = 'Map Dil% slabs to PRMT% (<strong>0–0</strong>, <strong>0.1–2</strong>, '
                            + '<strong>2–4</strong> … <strong>24–26</strong>) — same rules as /ebay-tabulator-view. '
                            + 'Dil is SKU-wise (OV L30 ÷ INV). <strong>Apply</strong> writes <strong>PRMT %</strong> '
                            + 'only on selected or visible SKUs with <strong>RV L30 &gt; 0</strong>. '
                            + 'S PRC = Std × (1 − (PRMT% + cvr%)/100), then auto-pushes when it differs from Price. '
                            + 'If INV is 0, PRMT% is <strong>0</strong>.';
                    }
                } else if (CHANNEL_PROMO_CHANNEL === 'shopify_b2c') {
                    const help = document.getElementById('ch-promo-dil-prmt-help');
                    if (help) {
                        help.innerHTML = 'Map Dil% slabs to PRMT% (<strong>0–0</strong>, <strong>0.1–2</strong>, '
                            + '<strong>2–4</strong> … <strong>24–26</strong>) — same rules as /ebay-tabulator-view. '
                            + 'Dil is SKU-wise (OV L30 ÷ INV). <strong>Apply</strong> writes <strong>PRMT %</strong> '
                            + 'only on selected or visible SKUs with <strong>B2C L30 &gt; 0</strong>. '
                            + 'S PRC = Std × (1 − (PRMT% + cvr%)/100), then auto-pushes when it differs from Price. '
                            + 'If INV is 0, PRMT% is <strong>0</strong>.';
                    }
                } else if (CHANNEL_PROMO_CHANNEL === 'shopify_b2b') {
                    const help = document.getElementById('ch-promo-dil-prmt-help');
                    if (help) {
                        help.innerHTML = '<strong>B2B disc</strong> auto-fills from Dil% '
                            + '(<strong>0–0% → 12</strong>, <strong>0.1–2% → 11</strong>, then −1 each 2% slab, '
                            + '<strong>22%+ → 0</strong>). <strong>Save Rule</strong> stores PRMT% for this channel. '
                            + '<strong>Apply</strong> fills <strong>PRMT %</strong> from each row’s Dil% / discounts S PRC. '
                            + 'If <strong>INV = 0</strong>, B2B disc and PRMT% are forced to <strong>0</strong>.';
                    }
                }
                renderChPromoDilPrmtModalTable();
                loadChPromoDilPrmtRules();
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });

            $('#ch-promo-push-prmt-btn').off('click.chpromo').on('click.chpromo', function(e) {
                e.preventDefault();
                if (typeof table === 'undefined' || !table) {
                    chPromoToast('error', 'Load data first');
                    return;
                }
                const selected = collectChPromoSelectedRows();
                let targets = selected;
                let scopeLabel = 'selected';
                if (!targets.length) {
                    targets = collectChPromoVisibleRows();
                    scopeLabel = 'all visible';
                }
                if (!targets.length) {
                    chPromoToast('error', 'No rows to push');
                    return;
                }
                if (!confirm(
                    'Apply Dil→PRMT and save for ' + targets.length + ' ' + scopeLabel + ' SKU(s) on '
                    + (chPromoCfg.label || CHANNEL_PROMO_CHANNEL) + '?'
                )) return;

                const $btn = $('#ch-promo-prmt-menu-btn');
                const html = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>…');
                Promise.resolve(applyChPromoDilPrmtToTargets(targets, scopeLabel)).finally(function() {
                    $btn.prop('disabled', false).html(html);
                });
            });

            $('#ch-promo-dil-prmt-apply-btn').off('click.chpromo').on('click.chpromo', saveAndApplyChPromoDilPrmt);
            $(document).off('input.chPromoDilCascade change.chPromoDilCascade', '#ch-promo-dil-prmt-tbody .ch-promo-dil-prmt-input')
                .on('input.chPromoDilCascade change.chPromoDilCascade', '#ch-promo-dil-prmt-tbody .ch-promo-dil-prmt-input', function() {
                    chPromoOnDilPrmtNumberChanged(this);
                });

            if (CHANNEL_PROMO_SHOW_ZERO_SOLD_RULES) {
                $('#ch-promo-zero-sold-rules-btn').off('click.chpromo').on('click.chpromo', function(e) {
                    e.preventDefault();
                    const modalEl = document.getElementById('chPromoZeroSoldPrmtModal');
                    if (!modalEl) return;
                    if (!chPromoZeroSoldUsesAmazonPrice()) {
                        renderChPromoZeroSoldPrmtModalTable();
                        loadChPromoDilPrmtRules();
                    } else {
                        $('#ch-promo-zero-sold-prmt-status').text('Apply Amazon Price to S PRC.');
                    }
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                });

                $('#ch-promo-zero-sold-prmt-apply-btn').off('click.chpromo').on('click.chpromo', async function() {
                    if (chPromoZeroSoldUsesAmazonPrice()) {
                        await saveAndApplyChPromoZeroSoldAmazon({ push: false });
                        return;
                    }
                    const selected = collectChPromoSelectedRows();
                    let targets = selected;
                    let label = 'selected';
                    if (!targets.length) {
                        targets = collectChPromoVisibleRows();
                        label = 'all visible';
                        if (!targets.length) {
                            chPromoToast('error', 'No rows to apply');
                            return;
                        }
                        if (!confirm('No rows selected — save 0 Sold Dil color rules and apply PRMT% to visible 0 Sold row(s)?')) {
                            return;
                        }
                    }
                    const $btn = $('#ch-promo-zero-sold-prmt-apply-btn');
                    const html = $btn.html();
                    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying…');
                    try {
                        await saveChPromoDilPrmtRules();
                        await applyChPromoDilPrmtToTargets(targets, label, { zeroSoldOnly: true });
                    } catch (xhr) {
                        chPromoToast('error', 'Save failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'));
                    } finally {
                        $btn.prop('disabled', false).html(html);
                    }
                });

                $('#ch-promo-zero-sold-amz-push-btn').off('click.chpromo').on('click.chpromo', function() {
                    saveAndApplyChPromoZeroSoldAmazon({ push: true });
                });

                $('#ch-promo-push-zero-sold-btn').off('click.chpromo').on('click.chpromo', function(e) {
                    e.preventDefault();
                    if (typeof table === 'undefined' || !table) {
                        chPromoToast('error', 'Load data first');
                        return;
                    }
                    if (chPromoZeroSoldUsesAmazonPrice()) {
                        saveAndApplyChPromoZeroSoldAmazon({ push: true });
                        return;
                    }
                    const selected = collectChPromoSelectedRows();
                    let targets = selected;
                    let scopeLabel = 'selected';
                    if (!targets.length) {
                        targets = collectChPromoVisibleRows();
                        scopeLabel = 'all visible';
                    }
                    if (!targets.length) {
                        chPromoToast('error', 'No rows to push');
                        return;
                    }
                    if (!confirm(
                        'Apply 0 Sold Dil color → PRMT and save for ' + scopeLabel + ' 0 Sold SKU(s) on '
                        + (chPromoCfg.label || CHANNEL_PROMO_CHANNEL) + '?'
                    )) return;

                    const $btn = $('#ch-promo-zero-sold-menu-btn');
                    const html = $btn.html();
                    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>…');
                    Promise.resolve(applyChPromoDilPrmtToTargets(targets, scopeLabel, { zeroSoldOnly: true })).finally(function() {
                        $btn.prop('disabled', false).html(html);
                    });
                });
            }

            if (CHANNEL_PROMO_SHOW_GT_SOLD_RULES) {
                loadChPromoGtSoldPrcRules();
                $('#ch-promo-gt-sold-rule-btn').off('click.chpromo').on('click.chpromo', function(e) {
                    e.preventDefault();
                    const modalEl = document.getElementById('chPromoGtSoldPrcModal');
                    if (!modalEl) return;
                    renderChPromoGtSoldPrcModalTable();
                    loadChPromoGtSoldPrcRules();
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                });
                $('#ch-promo-gt-sold-prc-save-btn').off('click.chpromo').on('click.chpromo', async function() {
                    const $btn = $(this);
                    const html = $btn.html();
                    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving…');
                    try {
                        await saveChPromoGtSoldPrcRules();
                        chPromoToast('success', '>0 Sold rules saved');
                    } catch (xhr) {
                        chPromoToast('error', 'Save failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'));
                    } finally {
                        $btn.prop('disabled', false).html(html);
                    }
                });
                $('#ch-promo-gt-sold-prc-apply-btn').off('click.chpromo').on('click.chpromo', function() {
                    saveAndApplyChPromoGtSoldPrc({ push: false });
                });
                $('#ch-promo-gt-sold-prc-push-btn').off('click.chpromo').on('click.chpromo', function() {
                    saveAndApplyChPromoGtSoldPrc({ push: true });
                });
                $(document).off('input.chpromoGtSold change.chpromoGtSold', '#ch-promo-gt-sold-prc-table .ch-promo-gt-sold-pct-input, #ch-promo-gt-sold-prc-table .ch-promo-gt-sold-dir-select')
                    .on('input.chpromoGtSold change.chpromoGtSold', '#ch-promo-gt-sold-prc-table .ch-promo-gt-sold-pct-input, #ch-promo-gt-sold-prc-table .ch-promo-gt-sold-dir-select', function() {
                        chPromoRefreshGtSoldRulePreviews();
                    });
            }

            if (!CHANNEL_PROMO_HIDE_CVR_CPN) $('#ch-promo-cvr-vs-cpn-btn').off('click.chpromo').on('click.chpromo', function(e) {
                e.preventDefault();
                const modalEl = document.getElementById('chPromoCvrVsCpnModal');
                if (!modalEl) return;
                if (CHANNEL_PROMO_CHANNEL === 'reverb') {
                    const help = document.getElementById('ch-promo-cvr-cpn-help');
                    if (help) {
                        help.innerHTML = 'Map CVR% slabs to <strong>cvr %</strong> (same as /ebay-tabulator-view). '
                            + '<strong>Apply</strong> writes cvr% only on selected or visible SKUs with '
                            + '<strong>RV L30 &gt; 0</strong>. '
                            + 'S PRC = Std × (1 − (PRMT% + cvr%)/100), then auto-pushes when it differs from Price.';
                    }
                } else if (chPromoIsEbayChannel()) {
                    const help = document.getElementById('ch-promo-cvr-cpn-help');
                    if (help) {
                        help.innerHTML = 'Map CVR% slabs to <strong>CPN %</strong>. '
                            + (CH_PEF_CVR_CPN_SKIP_ZERO ? 'There is no <strong>0%</strong> CVR slab. ' : '')
                            + 'Change a slab to autofill rows below by <strong>−1</strong> each (min 0). '
                            + '<strong>Save Rule</strong> stores the slabs. '
                            + '<strong>Apply</strong> writes CPN% only on selected or visible SKUs with '
                            + '<strong>eBay sale (E L30) &gt; 0</strong> (database only — no eBay coupon).';
                    }
                } else if (CHANNEL_PROMO_CHANNEL === 'shopify_b2c') {
                    const help = document.getElementById('ch-promo-cvr-cpn-help');
                    if (help) {
                        help.innerHTML = 'Map CVR% slabs to <strong>cvr %</strong> (same as /ebay-tabulator-view). '
                            + '<strong>Apply</strong> writes cvr% only on selected or visible SKUs with '
                            + '<strong>B2C L30 &gt; 0</strong>. '
                            + 'S PRC = Std × (1 − (PRMT% + cvr%)/100), then auto-pushes when it differs from Price.';
                    }
                }
                renderChPromoCvrCpnModalTable();
                loadChPromoCvrCpnRules();
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });

            if (!CHANNEL_PROMO_HIDE_CVR_CPN) $('#ch-promo-push-cpn-btn').off('click.chpromo').on('click.chpromo', function(e) {
                e.preventDefault();
                if (typeof table === 'undefined' || !table) {
                    chPromoToast('error', 'Load data first');
                    return;
                }
                const selected = collectChPromoSelectedRows();
                let targets = selected;
                let scopeLabel = 'selected';
                if (!targets.length) {
                    targets = collectChPromoVisibleRows();
                    scopeLabel = 'all visible';
                }
                if (!targets.length) {
                    chPromoToast('error', 'No rows to push');
                    return;
                }
                if (!confirm(
                    'Apply CVR→CPN and save for ' + targets.length + ' ' + scopeLabel + ' SKU(s) on '
                    + (chPromoCfg.label || CHANNEL_PROMO_CHANNEL) + '?'
                    + (CHANNEL_PROMO_CHANNEL === 'ebay1'
                        ? '\n\neBay1: pushes a PUBLIC coded coupon (code SAVE{nn}PCT), starts now, 30 days.\n'
                            + 'Same CPN% reuses the existing campaign and adds the SKU.'
                        : (CHANNEL_PROMO_CHANNEL === 'reverb'
                            ? '\n\nS PRC = Std × (1 − (PRMT% + CPN%)/100). No Reverb marketplace coupon push.'
                            : ''))
                )) return;

                const $btn = $('#ch-promo-cpn-menu-btn');
                const html = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>…');
                Promise.resolve(applyChPromoCvrCpnToTargets(targets, scopeLabel))
                    .then(function() {
                        if (CHANNEL_PROMO_CHANNEL === 'ebay1') {
                            return pushEbay1CodedCouponsForTargets(targets);
                        }
                        if (chPromoPushCpnQueueEnabled) {
                            const eligible = targets.filter(function(t) {
                                return chPromoIsChildRow(t.d) && chPromoCpnNeedsPush(t.d);
                            });
                            if (!eligible.length) return;
                            return queueChannelPushCpnRows(eligible);
                        }
                    })
                    .finally(function() {
                        $btn.prop('disabled', false).html(html);
                    });
            });

            if (!CHANNEL_PROMO_HIDE_CVR_CPN) {
                $('#ch-promo-cvr-cpn-save-btn').off('click.chpromo').on('click.chpromo', saveChPromoCvrCpnRulesOnly);
                $('#ch-promo-cvr-cpn-apply-btn').off('click.chpromo').on('click.chpromo', saveAndApplyChPromoCvrCpn);
                $(document).off('input.chPromoCvrCascade change.chPromoCvrCascade', '#ch-promo-cvr-cpn-tbody .ch-promo-cvr-cpn-input')
                    .on('input.chPromoCvrCascade change.chPromoCvrCascade', '#ch-promo-cvr-cpn-tbody .ch-promo-cvr-cpn-input', function() {
                        cascadeChPromoCvrCpnFromInput(this);
                    });
            }

            $(document).off('change.chpromo', '.ch-pef-appr-cb').on('change.chpromo', '.ch-pef-appr-cb', function() {
                if (typeof table === 'undefined' || !table) return;
                const sku = String($(this).attr('data-sku') || '');
                if (!sku) return;
                const row = table.getRows().find(function(r) {
                    return chPromoSku(r.getData()) === sku;
                });
                if (!row) return;
                if ($(this).is(':checked')) {
                    applyChPromoApprDiscount(row);
                } else {
                    clearChPromoApprDiscount(row, { save: true, redraw: true });
                }
            });

            $(document).off('click.chpromo', '.ch-promo-push-prc-btn').on('click.chpromo', '.ch-promo-push-prc-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof table === 'undefined' || !table) return;
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                const sku = String($btn.attr('data-sku') || '');
                const row = table.getRows().find(function(r) {
                    return chPromoSkuKey(chPromoSku(r.getData())) === chPromoSkuKey(sku);
                });
                if (!row) {
                    chPromoToast('error', 'Row not found');
                    return;
                }
                // Multi-select → bulk (same as Amazon Push Prc column)
                pushChannelStdPrcWithPromos($btn, row);
            });

            $(document).off('click.chpromo', '.ch-promo-push-prc-header-btn').on('click.chpromo', '.ch-promo-push-prc-header-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                bulkPushChannelPrcSelected();
            });

            $('#ch-promo-sprice-recalc-btn').off('click.chpromo').on('click.chpromo', function(e) {
                e.preventDefault();
                clearAndAutopopulateChannelSprice();
            });
            if (CHANNEL_PROMO_CHANNEL !== 'ebay1' && CHANNEL_PROMO_CHANNEL !== 'ebay2' && CHANNEL_PROMO_CHANNEL !== 'ebay3') {
                $('#ch-promo-sprice-vs-tpromo-btn').off('click.chpromo').on('click.chpromo', function(e) {
                    e.preventDefault();
                    fillSpriceFromTPromo();
                });
                $('#ch-promo-sprice-vs-tpromo-del-btn').off('click.chpromo').on('click.chpromo', function(e) {
                    e.preventDefault();
                    clearSpriceFromTPromo();
                });
            }
            $('#ch-promo-end-sales-btn').off('click.chpromo').on('click.chpromo', function(e) {
                e.preventDefault();
                endAllChannelSales();
            });
            $('#ch-promo-end-coupons-btn').off('click.chpromo').on('click.chpromo', function(e) {
                e.preventDefault();
                endAllChannelCoupons();
            });
            if (CHANNEL_PROMO_SHOW_ZERO_SOLD_DIL_RULE) {
                $('#ch-promo-zero-sold-vs-dil-btn').off('click.chpromo').on('click.chpromo', function(e) {
                    e.preventDefault();
                    const modalEl = document.getElementById('chPromoZeroSoldVsDilModal');
                    if (!modalEl) return;
                    renderChPromoZeroSoldDilModalTable();
                    loadChPromoZeroSoldDilRules();
                    chPromoZeroSoldDilSetRunUi('idle');
                    chPromoZeroSoldDilSetProgress('', false);
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                });
                $('#ch-promo-zero-sold-dil-apply-btn').off('click.chpromo').on('click.chpromo', saveAndApplyChPromoZeroSoldDil);
                $('#ch-promo-zero-sold-dil-save-btn').off('click.chpromo').on('click.chpromo', async function(e) {
                    e.preventDefault();
                    const $btn = $(this);
                    const html = $btn.html();
                    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving…');
                    try {
                        await saveChPromoZeroSoldDilRules();
                        if (typeof table !== 'undefined' && table) {
                            try { table.redraw(true); } catch (e) { /* ignore */ }
                        }
                        const collected = collectChPromoZeroSoldDilTargets();
                        if (collected.targets.length) {
                            $btn.prop('disabled', false).html(html);
                            await applyChPromoZeroSoldDilToTargets(collected.targets, collected.label);
                            return;
                        }
                        chPromoToast('success', '0 sold vs ROI rules saved.');
                    } catch (xhr) {
                        chPromoToast('error', 'Save failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'));
                    } finally {
                        $btn.prop('disabled', false).html(html);
                    }
                });
                $('#ch-promo-zero-sold-dil-pause-btn').off('click.chpromo').on('click.chpromo', function(e) {
                    e.preventDefault();
                    pauseChPromoZeroSoldDilRun();
                });
            }
            bindEbaySpriceAutofill();
            if (chPromoPrmtCpnComboEnabled() || chPromoEbayStdMinusPrmtCpnEnabled()) {
                $('#ch-promo-sprice-recalc-btn').attr(
                    'title',
                    (CHANNEL_PROMO_CHANNEL === 'shopify_b2c' || CHANNEL_PROMO_CHANNEL === 'reverb')
                        ? 'Clear S PRC, then refill: S PRC = Std × (1 − (PRMT% + cvr%)/100). Auto-pushes when it differs from live Price. Skips INV = 0.'
                        : 'Clear S PRC, then refill: S PRC = Std × (1 − (PRMT% + CPN%)/100). If both % are 0, S PRC = Std. No marketplace push. Skips INV = 0.'
                );
            } else if (chPromoReverbComboEnabled()) {
                $('#ch-promo-sprice-recalc-btn').attr(
                    'title',
                    '> 0 Sprice Vs Dil Rule: S PRC = Std × (1 − (PRMT% + 0 Sold PRMT%)/100) for RV L30 > 0 (first slab 0.1–20%). If both % are 0, S PRC = Std. No marketplace push. Skips INV = 0.'
                );
            }
        }

        // Export + auto-init
        window.channelPromoPricingColumns = channelPromoPricingColumns;
        window.channelPromoAnalyticsColumns = function() {
            const hide = {
                push_prmt: 1,
                push_std_prc: 1,
                push_prc: 1,
                dsc_pct: 1,
                dsc: 1,
                appr: 1,
            };
            if (CHANNEL_PROMO_CHANNEL !== 'temu') hide.push_cpn = 1;
            return (channelPromoPricingColumns() || []).filter(function(c) {
                return c && !hide[c.field];
            });
        };
        window.channelPromoPushPrmtColumn = channelPromoPushPrmtColumn;
        window.channelPromoPushCpnColumn = channelPromoPushCpnColumn;
        window.channelPromoPushStdPrcColumn = channelPromoPushStdPrcColumn;
        window.chPromoHeaderWithDelete = chPromoHeaderWithDelete;
        window.chPromoDeleteIconBtn = chPromoDeleteIconBtn;
        window.deleteChannelSaleEventOne = deleteChannelSaleEventOne;
        window.bulkDeleteChannelSaleEvent = bulkDeleteChannelSaleEvent;
        window.deleteChannelCpnOne = deleteChannelCpnOne;
        window.bulkDeleteChannelCpn = bulkDeleteChannelCpn;
        window.deleteChannelStdPrcStatusOne = deleteChannelStdPrcStatusOne;
        window.bulkDeleteChannelStdPrcStatus = bulkDeleteChannelStdPrcStatus;
        window.hideChannelPushStdPrcColumn = hideChannelPushStdPrcColumn;
        window.hideChannelPushCpnColumn = hideChannelPushCpnColumn;
        window.chPromoTemuSpriceFromStdPrmtCpn = chPromoTemuSpriceFromStdPrmtCpn;
        window.chPromoIsEndedListing = chPromoIsEndedListing;
        window.chPromoSpriceFromStdTPromo = chPromoSpriceFromStdTPromo;
        window.chPromoLiveSprice = chPromoLiveSprice;
        window.applyChannelSpriceFromStdChange = applyChannelSpriceFromStdChange;
        window.chPromoSpriceFromStdPrmtCpnWith = chPromoSpriceFromStdPrmtCpnWith;
        window.chPromoEbayStdMinusPrmtCpnEnabled = chPromoEbayStdMinusPrmtCpnEnabled;
        window.autopopulateEbaySpriceFromStdPrmtCpn = autopopulateEbaySpriceFromStdPrmtCpn;
        window.chPromoPrmtCpnComboEnabled = chPromoPrmtCpnComboEnabled;
        window.chPromoPlanSaleSprice = chPromoPlanSaleSprice;
        window.chPromoPaintPushStdPrcSpinner = chPromoPaintPushStdPrcSpinner;
        window.chPromoRefreshPushStdPrcCell = chPromoRefreshPushStdPrcCell;
        window.computeChannelPushPrcPlan = computeChannelPushPrcPlan;
        window.chPromoPrmtForRow = chPromoPrmtForRow;
        window.chPromoEbaySlabPrmt = chPromoEbaySlabPrmt;
        window.chPromoSyncEbayPrmtColumnFromSlabs = chPromoSyncEbayPrmtColumnFromSlabs;
        window.chPromoDilColorBand = chPromoDilColorBand;
        window.chPromoListingDil = chPromoListingDil;
        window.chPromoInvalidateListingDilCache = chPromoInvalidateListingDilCache;
        window.saveAndApplyChPromoZeroSoldAmazon = saveAndApplyChPromoZeroSoldAmazon;
        window.initChannelPromoPricingUi = initChannelPromoPricingUi;
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() { initChannelPromoPricingUi(); });
        } else {
            initChannelPromoPricingUi();
        }

        @if(!empty($channelPromoShowCvrUpDn))
        @include('partials.cvr-up-dn', ['cvrUpDnPart' => 'script', 'cvrUpDnChannel' => $channelPromoChannel])
        @endif
@endif
