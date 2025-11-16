<tbody>
    @php $i = 1; @endphp
    @foreach ($data as $row)
        @php
            $isParent = $row->is_parent ?? false;
            $approvedQty = (int) ($row->{'Approved QTY'} ?? 0);
            $stage = trim($row->Stage ?? '');
            $nrl = trim($row->nrl ?? '');
            $review = \App\Models\ToOrderReview::where('sku', $row->SKU)->where('parent', $row->Parent)->first();

        @endphp

        @continue(!$isParent && $approvedQty === 0)
        @continue($stage === 'Mfrg Progress')
        @continue($nrl === 'NR')

        <tr style="{{ $isParent ? 'background-color: #e0f7ff;' : '' }}"
            data-is-parent="{{ $row->is_parent ? '1' : '0' }}">
            <td style="position: relative;">
                @if (!empty($row->Image))
                    <div class="image-hover-wrapper">
                        <img src="{{ asset($row->Image) }}" alt="Product Image"
                            style="width: 30px; height: 30px; object-fit: contain; border-radius: 4px; border: 1px solid #ddd; cursor: zoom-in;">
                        <div class="image-hover-preview">
                            <img src="{{ asset($row->Image) }}" alt="Preview Image">
                        </div>
                    </div>
                @else
                    <span class="text-muted">No Image</span>
                @endif
            </td>

            <td class="fw-semibold" data-column="Parent">{{ $row->Parent ?? '-' }}</td>
            <td data-column="SKU"><span class="fw-semibold text-dark px-2 py-1">{{ $row->SKU ?? '-' }}</span></td>

            <td>
                @if ($isParent)
                    <input type="number" class="form-control form-control-sm" placeholder="Enter Approved QTY"
                        value="{{ $row->{'Approved QTY'} ?? '' }}" min="0" max="99999"
                        style="background:#f8fafd;color:#000;width:80px;" readonly>
                @else
                    <input type="number" class="form-control form-control-sm order-qty"
                        placeholder="Enter Approved QTY" data-sku="{{ $row->SKU }}" data-column="approved_qty"
                        value="{{ $row->{'Approved QTY'} ?? '' }}" min="0" max="99999"
                        style="background:#f8fafd;color:#000;width:80px;">
                @endif
            </td>

            @php
                $bgColor = '';
                $daysDiff = null;

                if (!empty($row->{'Date of Appr'})) {
                    $daysDiff = \Carbon\Carbon::parse($row->{'Date of Appr'})->diffInDays(\Carbon\Carbon::today());

                    if ($daysDiff > 14) {
                        $bgColor = 'background-color: red; color: white;';
                    } elseif ($daysDiff > 7) {
                        $bgColor = 'background-color: yellow; color: black;';
                    }else{
                        $bgColor = 'background-color: green; color: white;';
                    }
                }
            @endphp
            
            <td class="date-cell" data-dateOfAppr="{{ $daysDiff }}">
                <div style="display: flex; flex-direction: column; align-items: flex-start;">
                    <input type="date" class="form-control form-control-sm stage-select"
                        data-sku="{{ $row->SKU }}" data-column="Date of Appr"
                        value="{{ $row->{'Date of Appr'} ?? '' }}" style="width: 82px; {{ $bgColor }}">

                    @if ($daysDiff !== null)
                        <small style="font-size: 12px; color: rgb(72, 69, 69);">
                            {{ $daysDiff }} days ago
                        </small>
                    @endif
                </div>
            </td>

            <td>
                <select class="form-select stage-select" data-sku="{{ $row->SKU }}" data-column="Supplier"
                    style="width: 120px;">
                    @foreach ($uniqueSuppliers as $supplier)
                        <option value="{{ $supplier }}" {{ $row->Supplier == $supplier ? 'selected' : '' }}>
                            {{ $supplier }}
                        </option>
                    @endforeach
                </select>
            </td>
            <td>
                @if ($review)
                    <button class="btn btn-sm btn-outline-success open-review-modal"
                        data-parent="{{ $row->Parent ?? '' }}" data-sku="{{ $row->SKU ?? '' }}"
                        data-supplier="{{ $row->Supplier ?? '' }}"
                        data-positive="{{ $review->positive_review ?? '' }}"
                        data-negative="{{ $review->negative_review ?? '' }}"
                        data-improvement="{{ $review->improvement ?? '' }}" data-clink="{{ $row->Clink ?? '' }}"
                        data-date_updated="{{ $review->date_updated ?? '' }}">
                        <i class="fas fa-eye me-1"></i> View Review
                    </button>
                @else
                    <button class="btn btn-sm btn-outline-dark open-review-modal"
                        data-parent="{{ $row->Parent ?? '' }}" data-sku="{{ $row->SKU ?? '' }}"
                        data-supplier="{{ $row->Supplier ?? '' }}">
                        <i class="fas fa-pen me-1"></i> Review
                    </button>
                @endif
            </td>
            @if (!empty($row->{'RFQ Form Link'}))
                <td>
                    <a href="{{ $row->{'RFQ Form Link'} }}" class="copy-btn btn btn-sm btn-outline-primary"
                        data-link="{{ $row->{'RFQ Form Link'} }}">
                        <i class="mdi mdi-link"></i> Open
                    </a>
                </td>
            @else
                <td contenteditable="true" class="editable-cell" data-placeholder="Enter RFQ Form Link"
                    data-sku="{{ $row->SKU }}" data-column="RFQ Form Link"
                    data-original-value="{{ $row->{'RFQ Form Link'} ?? '' }}"
                    style="background:#f8fafd;min-width:180px;color:#000;"></td>
            @endif

            @if (!empty($row->{'Rfq Report Link'}))
                <td>
                    <a href="{{ $row->{'Rfq Report Link'} }}" class="copy-btn btn btn-sm btn-outline-success"
                        data-link="{{ $row->{'Rfq Report Link'} }}">
                        <i class="mdi mdi-link"></i> Open
                    </a>
                </td>
            @else
                <td contenteditable="true" class="editable-cell report-cell" data-placeholder="Enter Rfq Report Link"
                    data-sku="{{ $row->SKU }}" data-column="Rfq Report Link"
                    data-original-value="{{ $row->{'Rfq Report Link'} ?? '' }}" style="background:#f8fafd;color:#888;">
                </td>
            @endif

            @if (!empty($row->sheet_link))
                <td>
                    <a href="{{ $row->sheet_link }}" class="copy-btn btn btn-sm btn-outline-success"
                        data-link="{{ $row->sheet_link }}">
                        <i class="mdi mdi-link"></i> Open
                    </a>
                </td>
            @else
                <td contenteditable="true" class="editable-cell report-cell" data-placeholder="Enter Sheet Link"
                    data-sku="{{ $row->SKU }}" data-column="sheet_link"
                    data-original-value="{{ $row->sheet_link ?? '' }}" style="background:#f8fafd;color:#888;">
                </td>
            @endif

            <td>
                <select class="form-select form-select-sm stage-select" data-sku="{{ $row->SKU }}"
                    data-column="nrl" style="width:90px;">
                    @php
                        $nrls = ['REQ', 'NR'];
                        $sel = trim($row->nrl ?? '');
                    @endphp
                    @foreach ($nrls as $nrl)
                        <option value="{{ $nrl }}" {{ $nrl === $sel ? 'selected' : '' }}>
                            {{ $nrl }}
                        </option>
                    @endforeach
                </select>
            </td>

            <td>
                <select class="form-select form-select-sm stage-select" data-sku="{{ $row->SKU }}"
                    data-column="Stage" style="min-width:120px;">
                    @php
                        $stages = ['RFQ Sent', 'Analytics', 'To Approve', 'Approved', 'Advance', 'Mfrg Progress'];
                        $sel = trim($row->Stage ?? '');
                    @endphp
                    @foreach ($stages as $stage)
                        <option value="{{ $stage }}" {{ $stage === $sel ? 'selected' : '' }}>
                            {{ $stage }}
                        </option>
                    @endforeach
                </select>
            </td>

            <td>
                @php
                    $advDaysDiff = null;
                    $bgColor = '';
                    $daysText = '';

                    if (!empty($row->{'Adv date'})) {
                        $advDaysDiff = \Carbon\Carbon::parse($row->{'Adv date'})->diffInDays(\Carbon\Carbon::today());
                        $daysText = $advDaysDiff . ' days';

                        if ($advDaysDiff > 30) {
                            $bgColor = 'background-color: red; color: #fff;';
                        } elseif ($advDaysDiff > 20) {
                            $bgColor = 'background-color: yellow; color: #000;';
                        }
                    }
                @endphp

                @if ($isParent)
                    <div style="display: flex; flex-direction: column; align-items: flex-start;">
                        <input type="date" class="form-control form-control-sm" data-sku="{{ $row->SKU }}"
                            data-column="Adv date" value="{{ $row->{'Adv date'} ?? '' }}"
                            style="width: 82px; {{ $bgColor }}" readonly>
                        @if ($daysText)
                            <small style="font-size: 10px; color: rgb(72, 69, 69);">{{ $daysText }}</small>
                        @endif
                    </div>
                @else
                    <div style="display: flex; flex-direction: column; align-items: flex-start;">
                        <input type="date" class="form-control form-control-sm stage-select"
                            data-sku="{{ $row->SKU }}" data-column="Adv date"
                            value="{{ $row->{'Adv date'} ?? '' }}" style="width: 82px; {{ $bgColor }}">
                        @if ($daysText)
                            <small style="font-size: 12px; color: rgb(72, 69, 69);">{{ $daysText }}</small>
                        @endif
                    </div>
                @endif
            </td>

            <td>
                @if ($isParent)
                    <input type="number" class="form-control form-control-sm" placeholder="Order Qty"
                        value="{{ $row->order_qty ?? '' }}" min="0"
                        style="background:#f8fafd;color:#000;width:110px;" readonly>
                @else
                    <input type="number" class="form-control form-control-sm order-qty" placeholder="Order Qty"
                        data-sku="{{ $row->SKU }}" data-column="order_qty" value="{{ $row->order_qty ?? '' }}"
                        min="0" style="background:#f8fafd;color:#000;width:80px;">
                @endif
            </td>
        </tr>
        @php $i++; @endphp
    @endforeach
</tbody>
