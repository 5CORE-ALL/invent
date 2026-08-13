@php
    $rows = $rows ?? [];
    $showEmpty = $showEmpty ?? false;
@endphp
<table class="table table-sm mb-0">
    <tbody>
        @foreach($rows as $label => $value)
            @php
                $display = $value;
                if ($display === null || $display === '') {
                    $display = '—';
                }
            @endphp
            @if($showEmpty || $display !== '—')
                <tr>
                    <th class="ps-3 text-muted" style="width: {{ $width ?? '200px' }};">{{ $label }}</th>
                    <td>
                        @if(is_array($display))
                            <pre class="small mb-0">{{ json_encode($display, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        @else
                            {!! $display !!}
                        @endif
                    </td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>
