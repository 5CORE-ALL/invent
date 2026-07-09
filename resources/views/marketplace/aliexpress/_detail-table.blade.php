@php
    $rows = $rows ?? [];
@endphp
<table class="table table-sm mb-0">
    <tbody>
        @foreach($rows as $label => $value)
            @if($value !== null && $value !== '' && $value !== '—')
                <tr>
                    <th class="ps-3 text-muted" style="width: {{ $width ?? '200px' }};">{{ $label }}</th>
                    <td>
                        @if(is_array($value))
                            {{ implode(', ', array_filter($value)) }}
                        @else
                            {!! $value !!}
                        @endif
                    </td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>
