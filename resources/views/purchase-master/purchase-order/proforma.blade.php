<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
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
            max-width: 900px;
            margin: auto;
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
            .no-print {
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
            }
        }
        .wrap-text {
            max-width: 150px;     
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
                    <th>5 Core + Supplier SKU</th>
                    <th>Barcode</th>
                    <th>Tech</th>
                    <th>NW + GW (KG)</th>
                    <th>CBM</th>
                    <th>QTY</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @php $subtotal = 0; @endphp
                @foreach ($items as $i => $item)
                    @php
                        $lineTotal = $item->qty * $item->price;
                        $subtotal += $lineTotal;

                        // Currency symbol
                        $currencySymbol = '$'; // default
                        $curr = strtoupper($item->currency ?? 'USD');
                        if ($curr === 'RMB') $currencySymbol = '¥';
                        elseif ($curr === 'USD') $currencySymbol = '$';

                        // Grand total per currency
                        if (!isset($grandTotals[$curr])) $grandTotals[$curr] = 0;
                        $grandTotals[$curr] += $lineTotal;

                    @endphp
                    <tr>
                        <td><img src="/storage/{{ $item->photo }}" width="50px" height="50px" /></td>
                        <td>{{ $item->sku ?? '' }} + {{ $item->supplier_sku }}</td>
                        <td><img src="/storage/{{ $item->barcode }}" width="50px" height="50px" /></td>
                        <td class="wrap-text">{{ $item->tech }}</td>
                        <td>{{ $item->nw }} / {{ $item->gw }}</td>
                        <td>{{ $item->cbm }}</td>
                        <td>{{ $item->qty }}</td>
                        <td>{{ $item->price }}</td>
                        <td>{{ number_format(($item->qty ?? 0) * ($item->price ?? 0), 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="8" class="text-end">Grand Total</td>
                    <td>{{ $currencySymbol }}{{ number_format($subtotal, 2) }}</td>
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
                        <li>Delivery: 25 days after advance payment.</li>
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
                        <div>Balance Due: <span class="float-end">{{ $currencySymbol }}{{ number_format($total - ($order->advance_amount ?? 0), 2) }}</span></div>
                    @endforeach
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
                    '• Delivery after 25 days of deposit.',
                    '• No printing any Chinese letters. Only "made in China" on outer box.',
                ],
                'Packaging' => [
                    '• Customized packing - 2 color logo on product, customized color gift box, customized manual book / inner box 3ply & outer box 5ply.',
                    '• Print logo & "www.5CORE.com" & company certification logo & barcode & model/ref number on the individual GIFT boxes.',
                    '• Need to put a sticker/print with Barcode and sku on top of polymailer bag/brown inner box.',
                    '• Master carton should weigh less than 20LB and max size of 25x25x25 inch (63x63x63 cm).',
                    '• Master carton must contain 5 Core Logo, SKU, Quantity, Gross Weight (in Lbs), Dimensions (in Inches), Box No. - xx/xxx.',
                    '• SKU should be printed on 5 sides of the outer carton (except bottom). Mention color variation if any.',
                    '• Provide extra color and brown gift boxes for repackaging damaged items.',
                    '• Add color stickers on each gift and outer carton for color variants.',
                    '• Apply cello tape on corners of inner/outer box for secure packaging.',
                ],
                'Payment Terms' => [
                    '• Delivery after 25 days of 20% deposit, balance before shipping.',
                    '• Each item includes 2% additional free goods for damages.',
                ],
                'Replacements' => [
                    '• High-quality (8 pics) HD pictures + 1 video + description + specifications with client logo for marketing.',
                ],
                'Others' => [
                    '• Manual book required in English and Spanish with 5CORE logo printed on it.',
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

    </script>
</body>

</html>
