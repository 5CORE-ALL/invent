@php $pm = \App\Models\Meeting::priorityInfo($priority ?? 'normal'); $pk = array_key_exists($priority ?? '', \App\Models\Meeting::PRIORITIES) ? $priority : 'normal'; @endphp
<span class="mt-prio {{ $pk }}" title="Priority: {{ $pm['label'] }}"><i></i>{{ $pm['label'] }}</span>
