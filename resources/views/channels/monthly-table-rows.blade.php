@foreach ($monthlyData as $row)
<tr>
    <td>{{ $row->month }}</td>
    <td contenteditable="true" class="editable" data-field="system_data" data-month="{{ $row->month }}">
        {{ $row->system_data }}
    </td>
    <td contenteditable="true" class="editable" data-field="site_amount" data-month="{{ $row->month }}">
        {{ $row->site_amount }}
    </td>
    <td contenteditable="true" class="editable" data-field="receipt_amount" data-month="{{ $row->month }}">
        {{ $row->receipt_amount }}
    </td>
    <td>
        {{ $row->expense_percentage }}%
    </td>
    <td contenteditable="true" class="editable" data-field="ours_percentage" data-month="{{ $row->month }}">
        {{ $row->ours_percentage }}
    </td>
</tr>
@endforeach
