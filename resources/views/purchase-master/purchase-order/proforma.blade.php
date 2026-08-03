<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Proforma Invoice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-size: 15px;
            color: #222;
            background: #f6f8fa;
            padding: 30px 0;
        }

        .invoice-box {
            background: #fff;
            border: 1px solid #e3e6ea;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
            padding: 25px 25px;
            border-radius: 14px;
            max-width: 100%;
            width: 100%;
            margin: 0 auto;
        }

        .invoice-box .table {
            width: 100%;
            table-layout: auto;
        }

        .heading {
            text-align: center;
            margin-bottom: 28px;
            font-weight: 700;
            font-size: 28px;
            letter-spacing: 2px;
            color: #1a237e;
        }

        .invoice-header {
            border-bottom: 1.5px solid #e3e6ea;
            padding-bottom: 18px;
            margin-bottom: 24px;
        }

        .invoice-header h6 {
            font-weight: 600;
            color: #3949ab;
        }

        .invoice-header p {
            margin-bottom: 2px;
        }

        .table {
            margin-bottom: 0;
        }

        .table th,
        .table td {
            vertical-align: middle;
            text-align: center;
        }

        .table thead th {
            background: #e8eaf6;
            color: #1a237e;
            font-weight: 700;
            border-bottom: 2px solid #c5cae9;
        }

        .table tfoot td {
            background: #f5f5f5;
            font-weight: 600;
        }

        .note-section {
            background: #f1f8e9;
            padding: 15px 15px;
            border-radius: 8px;
            margin-top: 18px;
        }

        .note-section h6 {
            color: #388e3c;
            font-weight: 700;
        }

        .terms-section {
            font-size: 14px;
            line-height: 1.7;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 16px 20px;
            margin-top: 28px;
        }

        .totals-box {
            background: #f3e5f5;
            border-radius: 8px;
            padding: 18px 22px;
            margin-top: 18px;
            color: #6a1b9a;
            font-weight: 600;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            color: #888;
            font-size: 13px;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                margin: 0;
                padding: 0;
                zoom: 70%;
            }
            button,
            .btn,
            [onclick*="add"],
            [onclick*="edit"],
            svg {
                display: none !important;
            }

            [type="button"],
            .no-print,
            .col-edit,
            .po-edit-btn,
            .po-line-actions,
            .po-supplier-sku-edit {
                display: none !important;
            }

            @page {
                size: A4;
                margin: 0;
            }

            body {
                background: #fff !important;
                padding: 0;
            }

            .invoice-box {
                box-shadow: none;
                border: none;
                max-width: 100%;
                width: 100%;
            }
        }
        .wrap-text {
            word-wrap: break-word;
            white-space: normal;
            font-size: 12px;
        }

        .col-5core-sku,
        .col-supplier-sku,
        .col-short-name {
            min-width: 140px;
            white-space: nowrap;
            font-size: 12px;
        }

        .col-short-name {
            min-width: 160px;
            max-width: 220px;
            white-space: normal;
            text-align: left;
        }

        .col-barcode {
            width: 90px;
            min-width: 80px;
            font-size: 10px;
        }

        .po-barcode-img {
            width: 70px;
            height: 70px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 2px;
        }

        .po-barcode-code {
            margin-top: 3px;
            font-size: 9px;
            line-height: 1.2;
            word-break: break-all;
            text-align: center;
        }

        .col-edit {
            width: 70px;
            min-width: 70px;
        }

        .po-edit-btn {
            border: 1px solid #3949ab;
            color: #3949ab;
            background: #fff;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 12px;
            cursor: pointer;
        }

        .po-edit-btn:hover {
            background: #3949ab;
            color: #fff;
        }

        .po-line-input,
        .po-supplier-sku-input {
            width: 100%;
            min-width: 70px;
            font-size: 12px;
            padding: 4px 6px;
            border: 1px solid #3949ab;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .po-line-input.po-line-tech {
            min-width: 140px;
            min-height: 64px;
            resize: vertical;
        }
        .po-line-actions {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: stretch;
        }
        .po-line-actions .btn {
            font-size: 11px;
            padding: 3px 8px;
        }
        .po-supplier-sku-input-legacy {
            width: 100%;
            min-width: 110px;
            font-size: 12px;
            padding: 4px 6px;
            border: 1px solid #3949ab;
            border-radius: 4px;
        }

        .po-supplier-sku-actions {
            display: flex;
            gap: 4px;
            justify-content: center;
            margin-top: 4px;
        }

        .po-supplier-sku-actions .btn {
            font-size: 11px;
            padding: 2px 8px;
        }

        .col-tech {
            min-width: 180px;
            word-wrap: break-word;
            white-space: normal;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <div class="invoice-box" id="invoice-box">
        <div class="d-flex justify-content-end">
            <button type="button" class="btn btn-success" onclick="printAsPdfStyle()">Download PDF</button>
        </div>
        <div class="row mb-4 align-items-center">
            <div class="col-md-6">
                <div class="heading mb-0 text-start" style="font-size: 1.5rem;">
                    Proforma Invoice / Contract
                </div>
                <div class="mt-2">
                    <img src="{{ asset('assets/5core.png') }}" alt="Company Logo" style="height: 60px;">
                </div>
            </div>
            <div class="col-md-6 text-end">
                <div>
                    <span class="fw-bold text-secondary">PO Number:</span>
                    <span class="ms-1">{{ $order->po_number }}</span>
                </div>
                <div>
                    <span class="fw-bold text-secondary">PO Date:</span>
                    <span class="ms-1">{{ $order->po_date ?? \Carbon\Carbon::now()->format('d-m-Y') }}</span>
                </div>
            </div>
        </div>

        {{-- Invoice Header --}}
        <div class="row invoice-header">
            <div class="col-md-6">
                <h6>From:</h6>
                <p>
                    {{ $from['name'] ?? '5 CORE INC' }}<br>
                    {!! $from['address'] ?? '1221 W.SANDUSKY AVE,<br>BELLEFONTAINE OH43311, USA' !!}<br>
                    {{-- Email: {{ $from['email'] ?? 'president@5core.com' }}<br>
                    Phone: {{ $from['phone'] ?? '+1(714)249-0848' }} --}}
                </p>
            </div>
            <div class="col-md-6 text-end">
                <h6>To:</h6>
                <p>
                    {{ $supplier->name ?? 'John Doe' }}<br>
                    {{ $supplier->company ?? 'ABC Imports Ltd.' }}<br>
                    {{ $supplier->country ?? 'China' }}<br>
                    Email: {{ $supplier->email ?? 'john@abcimports.com' }}
                </p>
            </div>
        </div>
        @php
            $grandTotals = []; 
        @endphp
        {{-- SKU Table --}}
        <table class="table table-bordered table-responsive" style="padding:0%;">
            <thead>
                <tr>
                    <th>Photo</th>
                    <th class="col-barcode">Barcode</th>
                    <th class="col-5core-sku">5 Core SKU</th>
                    <th class="col-supplier-sku">Supplier SKU</th>
                    <th class="col-short-name">Short Name</th>
                    <th class="col-tech">Tech</th>
                    <th>NW (KG)</th>
                    <th>GW (KG)</th>
                    <th>CBM</th>
                    <th>QTY</th>
                    <th>Price ($)</th>
                    <th>Price (¥)</th>
                    <th>Total ($)</th>
                    <th>Total (¥)</th>
                    <th class="col-edit no-print">Edit</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $subtotal = 0;
                    $cbmTotal = 0;
                    $usdToCny = $usdToCny ?? null;
                    $subtotalUsd = 0.0;
                    $subtotalRmb = 0.0;
                    $hasUsdTotal = false;
                    $hasRmbTotal = false;
                @endphp
                @foreach ($items as $i => $item)
                    @php
                        $lineTotal = $item->qty * $item->price;
                        $subtotal += $lineTotal;
                        $cbmTotal += ($item->qty ?? 0) * (float)($item->cbm ?? 0);

                        $curr = strtoupper($item->currency ?? 'USD');
                        $currencySymbol = ($curr === 'RMB') ? '¥' : '$';
                        $price = (float) ($item->price ?? 0);
                        $qty = (float) ($item->qty ?? 0);

                        // Stored currency is the source of truth.
                        // Always show both $ and ¥ when the FX rate is available:
                        // - USD entry → $ stored, ¥ converted for display
                        // - RMB entry → ¥ stored, $ converted for display
                        if ($curr === 'RMB') {
                            $priceRmb = $price;
                            $priceUsd = ($usdToCny && $usdToCny > 0) ? round($price / $usdToCny, 2) : null;
                        } else {
                            $priceUsd = $price;
                            $priceRmb = ($usdToCny && $usdToCny > 0) ? round($price * $usdToCny, 2) : null;
                        }

                        $totalUsd = $priceUsd !== null ? round($priceUsd * $qty, 2) : null;
                        $totalRmb = $priceRmb !== null ? round($priceRmb * $qty, 2) : null;
                        if ($totalUsd !== null) {
                            $subtotalUsd += $totalUsd;
                            $hasUsdTotal = true;
                        }
                        if ($totalRmb !== null) {
                            $subtotalRmb += $totalRmb;
                            $hasRmbTotal = true;
                        }

                        // Grand total per currency
                        if (!isset($grandTotals[$curr])) $grandTotals[$curr] = 0;
                        $grandTotals[$curr] += $lineTotal;

                    @endphp
                    <tr>
                        <td>
                            @if(!empty($item->photo_url))
                                <img src="{{ $item->photo_url }}" width="50px" height="50px" alt="{{ $item->sku ?? '' }}" />
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="col-barcode">
                            @if(!empty($item->barcode_url))
                                <img src="{{ $item->barcode_url }}" alt="Barcode {{ $item->barcode_code ?? $item->sku ?? '' }}" class="po-barcode-img" />
                                @if(!empty($item->barcode_code))
                                    <div class="po-barcode-code">{{ $item->barcode_code }}</div>
                                @endif
                            @elseif(!empty($item->barcode_code))
                                <span class="po-barcode-code">{{ $item->barcode_code }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="col-5core-sku">{{ $item->sku ?? '' }}</td>
                        <td class="col-supplier-sku po-editable" data-field="supplier_sku" data-item-index="{{ $i }}">
                            <span class="po-field-text">{{ $item->supplier_sku ?? '' }}</span>
                        </td>
                        <td class="col-short-name po-editable" data-field="short_name">
                            <span class="po-field-text">{{ $item->short_name ?? '' }}</span>
                        </td>
                        <td class="wrap-text col-tech po-editable" data-field="tech" data-raw="{{ base64_encode((string) ($item->tech ?? '')) }}">
                            <span class="po-field-text">{!! nl2br(e($item->tech ?? '')) !!}</span>
                        </td>
                        <td class="col-nw po-editable" data-field="nw">
                            <span class="po-field-text">{{ $item->nw ?? '' }}</span>
                        </td>
                        <td class="col-gw po-editable" data-field="gw">
                            <span class="po-field-text">{{ $item->gw ?? '' }}</span>
                        </td>
                        <td class="col-cbm po-editable" data-field="cbm">
                            <span class="po-field-text">{{ $item->cbm }}</span>
                        </td>
                        <td class="col-qty po-editable" data-field="qty">
                            <span class="po-field-text">{{ $item->qty }}</span>
                        </td>
                        <td class="col-price-usd po-editable"
                            data-field="price_usd"
                            data-currency-source="{{ $curr }}"
                            data-raw="{{ $curr === 'USD' ? $price : ($priceUsd !== null ? $priceUsd : '') }}">
                            <span class="po-field-text">{{ $priceUsd !== null ? rtrim(rtrim(number_format($priceUsd, 2, '.', ''), '0'), '.') . '$' : '—' }}</span>
                        </td>
                        <td class="col-price-rmb po-editable"
                            data-field="price_rmb"
                            data-currency-source="{{ $curr }}"
                            data-raw="{{ $curr === 'RMB' ? $price : ($priceRmb !== null ? $priceRmb : '') }}">
                            <span class="po-field-text">{{ $priceRmb !== null ? rtrim(rtrim(number_format($priceRmb, 2, '.', ''), '0'), '.') . '¥' : '—' }}</span>
                        </td>
                        <td class="col-total-usd">{{ $totalUsd !== null ? number_format($totalUsd, 2) . '$' : '—' }}</td>
                        <td class="col-total-rmb">{{ $totalRmb !== null ? number_format($totalRmb, 2) . '¥' : '—' }}</td>
                        <td class="col-edit no-print">
                            <button type="button"
                                    class="po-edit-btn"
                                    data-item-index="{{ $i }}"
                                    data-currency="{{ strtoupper($item->currency ?? 'USD') }}"
                                    title="Edit line item">
                                Edit
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="12" class="text-end">Grand Total</td>
                    <td>{{ $hasUsdTotal ? number_format($subtotalUsd, 2) . '$' : '—' }}</td>
                    <td>{{ $hasRmbTotal ? number_format($subtotalRmb, 2) . '¥' : '—' }}</td>
                    <td class="col-edit no-print"></td>
                </tr>
            </tfoot>
        </table>

        <div class="row mt-4">
            <div class="col-md-6">
                <div class="note-section">
                    <h6>Important Points
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                            class="bi bi-plus" viewBox="0 0 16 16" onclick="addNote()"
                            style="cursor: pointer; color: #6a1b9a; border-radius: 50%; padding: 2px; background: #f3e5f5; height: 25px; width: 25px; display: inline-block; vertical-align: middle;
                            margin-left: 8px;">
                            <path
                                d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z" />
                        </svg>
                    </h6>
                    <ul class="mb-0">
                        <li>Delivery: 25 days within advance payment.</li>
                        <li>Product quality as per approved samples.</li>
                    </ul>
                </div>

                <script>
                    function addNote() {
                        const ul = document.querySelector('.note-section ul');
                        const point = prompt('Enter a new important point:');
                        if (point && point.trim() !== '') {
                            const li = document.createElement('li');
                            li.textContent = point;
                            ul.appendChild(li);
                        }
                    }
                </script>
            </div>
            <div class="col-md-6">
                <div class="totals-box">
                    @foreach($grandTotals as $curr => $total)
                        @php
                            $currencySymbol = $curr === 'RMB' ? '¥' : '$';
                        @endphp
                        <div>Subtotal: <span class="float-end">{{ $currencySymbol }}{{ number_format($total, 2) }}</span></div>
                        <div>Advance: <span class="float-end">{{ $currencySymbol }}{{ number_format($order->advance_amount ?? 0, 2) }}</span></div>
                        <div>Balance Due: <span class="float-end">{{ $currencySymbol }}{{ number_format(round($total - ($order->advance_amount ?? 0), 0), 0) }}</span></div>
                    @endforeach
                    <div class="mt-2 pt-2" style="border-top: 1px solid rgba(106,27,154,0.3);">CBM Total: <span class="float-end">{{ number_format($cbmTotal, 2) }}</span></div>
                </div>
            </div>

        </div>

        @php
            $terms = [
                'Shipping Port' => ['Tianjin', 'Guangzhou', 'Ningbo'],
                'Quality' => [
                    '• We want to have repeat order if all quality and packaging is 100% okay.',
                ],
                'Time' => [
                    '• Delivery within 25 days of deposit',
                ],
                'Packaging' => [
                    '• No printing any Chinese letters. Only "made in China" on outer box.',
                    '• Customized packing - 2 color logo on product, customized color gift box, customized manual book / inner box 3ply & outer box 5ply.',
                    '• Inner Box - Print logo, www.5CORE.com, certification, logo, barcode, SKU on GIFT boxes + inner box.',
                    '• Need to put a sticker/print with Barcode and sku on top of polymailer bag/brown inner box.',
                    '• Master carton should weigh within (15 KG to 22 kg maximum) and within size of 18x18x18 to max 25x25x25 inch.',
                    '• Master carton must contain 5 Core Logo, SKU, QTY, GW (Lbs), Size (in Inch), Box No. xx.',
                    '• Provide extra color and brown gift boxes for repackaging damaged items.',
                    '• Add color stickers on each gift and outer carton for color variants.',
                    '• Apply cello tape on corners of inner/outer box for secure packaging.',
                    '• Use standard pallet size for small loose items that are very heavy.',
                ],
                'Payment Terms' => [
                    '• 20% deposit, balance before shipping.',
                    '• 20% deposit, balance before Release of BL.',
                    '• 10% deposit, balance before Release of BL.',
                    '• 30% deposit, balance before Release of BL.',
                    '• Each item includes 2% additional free goods for damages.',
                ],
                'Replacements' => [
                    '• High-quality (8 pics) HD pictures + 1 video + description + specifications with client logo for marketing.',
                ],
                'Others' => [
                    '• User Manual /Assembly book required in English and Spanish with 5CORE logo printed on it.',
                ],
            ];
        @endphp

        <form id="termsForm" style="background: #e6e9e94d; padding: 15px 15px; margin-top:20px;border-radius: 8px;">
            <h5 class="fw-bold text-primary mt-3">Terms & Conditions:</h5>
            @foreach ($terms as $heading => $points)
                <div class="mb-1">
                    <h6>{{ $heading }}</h6>
                    @if ($heading === 'Shipping Port')
                        <select name="Shipping Port" class="form-select form-select-sm mb-2" required>
                            @foreach ($points as $port)
                                <option value="{{ $port }}">{{ $port }}</option>
                            @endforeach
                        </select>
                    @else
                        <ul class="list-unstyled">
                            @foreach ($points as $key => $point)
                                <li class="mb-0">
                                    <label>
                                        <input type="checkbox" name="terms[{{ $heading }}][]"
                                            value="{{ $point }}" checked>
                                        {{ $point }}
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach

            <div class="mb-3">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addCustomPoint()">+ Add Custom Point</button>
            </div>

            <div id="customPoints"></div>

        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        function addCustomPoint() {
            const container = document.getElementById('customPoints');
            const newDiv = document.createElement('div');
            newDiv.className = "mb-0";
            newDiv.innerHTML = `
                <input type="text" name="custom_terms[]" class="form-control form-control-sm" placeholder="Enter custom point" required>
            `;
            container.appendChild(newDiv);
        }
        
        window.onbeforeprint = () => {
            // Remove all unchecked checkboxes
            const allCheckboxes = document.querySelectorAll('input[type="checkbox"]');
            allCheckboxes.forEach(checkbox => {
                if (!checkbox.checked) {
                    const li = checkbox.closest('li');
                    if (li) li.remove();
                } else {
                    checkbox.style.display = 'none'; // Hide checkbox for clean print
                }
            });

            // Remove empty custom input points
            const customInputs = document.querySelectorAll('input[name="custom_terms[]"]');
            customInputs.forEach(input => {
                if (!input.value.trim()) {
                    input.remove();
                } else {
                    const textNode = document.createElement('p');
                    textNode.textContent = input.value.trim();
                    input.parentNode.replaceChild(textNode, input);
                }
            });

            // Convert Shipping Port dropdown to plain text
            const portSelect = document.querySelector('select[name="Shipping Port"]');
            if (portSelect) {
                const selectedOption = portSelect.options[portSelect.selectedIndex];
                const selectedText = selectedOption ? selectedOption.textContent.trim() : 'N/A';

                // Create a text element only for printing
                const printSpan = document.createElement('p');
                printSpan.textContent = `Shipping Port: ${selectedText}`;
                printSpan.classList.add('print-only');
                printSpan.style.margin = '0';

                portSelect.style.display = 'none'; // hide original select
                portSelect.parentNode.appendChild(printSpan);
            }


            // Remove all buttons inside the form
            document.querySelectorAll('form#termsForm button').forEach(btn => btn.remove());

            // ✅ Remove empty heading blocks
            document.querySelectorAll('#termsForm .mb-1').forEach(section => {
                const listItems = section.querySelectorAll('li');
                const hasTextInputs = section.querySelectorAll('input[type="text"], select, textarea').length;
                const hasRemainingContent = listItems.length > 0 || hasTextInputs > 0;

                if (!hasRemainingContent) {
                    section.remove();
                }
            });

        };

        function printAsPdfStyle() {
            window.print();
        }

        (function () {
            const saveUrl = @json(!empty($order->id) ? route('purchase-order.update-item-supplier-sku', $order->id) : '');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const usdToCny = @json($usdToCny ?? null);
            let editingRow = null;

            function escapeHtml(str) {
                return String(str || '')
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }

            function fieldValue(cell) {
                if (!cell) return '';
                if (cell.hasAttribute('data-raw')) {
                    const raw = cell.getAttribute('data-raw') || '';
                    if (!raw) return '';
                    try {
                        const bin = atob(raw);
                        if (typeof TextDecoder !== 'undefined') {
                            const bytes = Uint8Array.from(bin, function (c) { return c.charCodeAt(0); });
                            return new TextDecoder('utf-8').decode(bytes);
                        }
                        return decodeURIComponent(escape(bin));
                    } catch (e) {
                        // Legacy: previously used JSON-encoded attribute values.
                        if (raw.startsWith('"') && raw.endsWith('"')) {
                            try { return JSON.parse(raw); } catch (e2) {}
                        }
                        return raw;
                    }
                }
                const textEl = cell.querySelector('.po-field-text');
                return (textEl?.textContent || '').trim();
            }

            function cancelRowEdit(row, snapshot) {
                if (!row || !snapshot) return;
                Object.keys(snapshot).forEach((field) => {
                    const cell = row.querySelector('.po-editable[data-field="' + field + '"]');
                    if (!cell) return;
                    cell.innerHTML = snapshot[field];
                });
                const editCell = row.querySelector('.col-edit');
                if (editCell && snapshot.__edit) {
                    editCell.innerHTML = snapshot.__edit;
                    bindEditButton(editCell.querySelector('.po-edit-btn'));
                }
                editingRow = null;
            }

            function startEdit(btn) {
                if (!saveUrl) {
                    alert('Cannot save: purchase order id missing.');
                    return;
                }
                const row = btn.closest('tr');
                if (!row || row.querySelector('.po-line-input')) return;
                if (editingRow && editingRow !== row) {
                    alert('Finish editing the other row first.');
                    return;
                }

                const index = btn.getAttribute('data-item-index');
                const currency = (btn.getAttribute('data-currency') || 'USD').toUpperCase();
                const snapshot = { __edit: row.querySelector('.col-edit')?.innerHTML || '' };

                const fields = [
                    { field: 'supplier_sku', type: 'text' },
                    { field: 'short_name', type: 'text', max: 40 },
                    { field: 'tech', type: 'textarea' },
                    { field: 'nw', type: 'number' },
                    { field: 'gw', type: 'number' },
                    { field: 'cbm', type: 'number' },
                    { field: 'qty', type: 'number' },
                    { field: 'price_usd', type: 'number' },
                    { field: 'price_rmb', type: 'number' },
                ];

                fields.forEach((cfg) => {
                    const cell = row.querySelector('.po-editable[data-field="' + cfg.field + '"]');
                    if (!cell) return;
                    snapshot[cfg.field] = cell.innerHTML;
                    const val = fieldValue(cell);
                    if (cfg.type === 'textarea') {
                        cell.innerHTML = `<textarea class="po-line-input po-line-tech" data-field="${cfg.field}">${escapeHtml(val)}</textarea>`;
                    } else {
                        const maxAttr = cfg.max ? ` maxlength="${cfg.max}"` : '';
                        const step = cfg.type === 'number' ? ' step="any"' : '';
                        // Source currency field is editable; the converted side is readonly.
                        let readonly = '';
                        if (cfg.field === 'price_usd' && currency === 'RMB') readonly = ' readonly';
                        if (cfg.field === 'price_rmb' && currency === 'USD') readonly = ' readonly';
                        cell.innerHTML = `<input type="${cfg.type}" class="po-line-input" data-field="${cfg.field}" value="${escapeHtml(val)}"${maxAttr}${step}${readonly}>`;
                    }
                });

                const priceUsdInput = row.querySelector('.po-line-input[data-field="price_usd"]');
                const priceRmbInput = row.querySelector('.po-line-input[data-field="price_rmb"]');
                let priceSource = currency; // 'USD' | 'RMB'

                function syncPricesFromSource() {
                    if (!usdToCny || !priceUsdInput || !priceRmbInput) return;
                    if (priceSource === 'RMB') {
                        const rmb = parseFloat(priceRmbInput.value);
                        priceRmbInput.readOnly = false;
                        priceUsdInput.readOnly = true;
                        priceUsdInput.value = (isFinite(rmb) && rmb > 0)
                            ? (rmb / usdToCny).toFixed(2)
                            : '';
                    } else {
                        const usd = parseFloat(priceUsdInput.value);
                        priceUsdInput.readOnly = false;
                        priceRmbInput.readOnly = true;
                        priceRmbInput.value = (isFinite(usd) && usd > 0)
                            ? (usd * usdToCny).toFixed(2)
                            : '';
                    }
                }

                if (priceUsdInput) {
                    priceUsdInput.addEventListener('focus', function () { priceSource = 'USD'; });
                    priceUsdInput.addEventListener('input', function () {
                        priceSource = 'USD';
                        syncPricesFromSource();
                    });
                }
                if (priceRmbInput) {
                    priceRmbInput.addEventListener('focus', function () {
                        // Allow switching source to RMB by focusing/editing ¥
                        priceSource = 'RMB';
                        priceRmbInput.readOnly = false;
                        if (priceUsdInput) priceUsdInput.readOnly = true;
                    });
                    priceRmbInput.addEventListener('input', function () {
                        priceSource = 'RMB';
                        syncPricesFromSource();
                    });
                }
                syncPricesFromSource();

                const editCell = row.querySelector('.col-edit');
                editCell.innerHTML = `
                    <div class="po-line-actions">
                        <button type="button" class="btn btn-sm btn-primary po-save-line">Save</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary po-cancel-line">Cancel</button>
                    </div>
                `;
                editingRow = row;

                editCell.querySelector('.po-cancel-line').addEventListener('click', () => {
                    cancelRowEdit(row, snapshot);
                });

                editCell.querySelector('.po-save-line').addEventListener('click', () => {
                    const saveBtn = editCell.querySelector('.po-save-line');
                    saveBtn.disabled = true;
                    saveBtn.textContent = 'Saving…';

                    const rmbVal = priceRmbInput ? priceRmbInput.value.trim() : '';
                    const usdVal = priceUsdInput ? priceUsdInput.value.trim() : '';
                    // Save by whichever column the user edited (source). Both still display after reload.
                    const payload = {
                        item_index: parseInt(index, 10),
                        currency: priceSource === 'RMB' ? 'RMB' : 'USD',
                        price_rmb: priceSource === 'RMB' ? rmbVal : '',
                        price_usd: priceSource === 'RMB' ? '' : usdVal,
                    };
                    fields.forEach((cfg) => {
                        if (cfg.field === 'price_usd' || cfg.field === 'price_rmb') return;
                        const input = row.querySelector('.po-line-input[data-field="' + cfg.field + '"]');
                        payload[cfg.field] = input ? input.value.trim() : '';
                    });

                    fetch(saveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify(payload),
                    })
                    .then(r => r.json())
                    .then(resp => {
                        if (!resp || resp.success === false) {
                            alert(resp?.message || 'Failed to save line item');
                            saveBtn.disabled = false;
                            saveBtn.textContent = 'Save';
                            return;
                        }
                        // Reload so totals / dual prices / short name stay consistent.
                        window.location.reload();
                    })
                    .catch(() => {
                        alert('Failed to save line item');
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Save';
                    });
                });

                row.querySelector('.po-line-input')?.focus();
            }

            function bindEditButton(btn) {
                if (!btn || btn.dataset.bound === '1') return;
                btn.dataset.bound = '1';
                btn.addEventListener('click', () => startEdit(btn));
            }

            document.querySelectorAll('.po-edit-btn').forEach(bindEditButton);
        })();

    </script>
</body>

</html>
