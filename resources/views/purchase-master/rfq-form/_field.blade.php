<div class="form-group">
    <label class="form-label @if(!empty($field['required'])) required @endif">
        {{ $field['label'] }}
    </label>

    @if($field['type'] === 'select')
        <select name="{{ $field['name'] }}" class="form-select" @if(!empty($field['required'])) required @endif>
            <option value="">Select</option>
            @if(!empty($field['options']))
                @foreach(explode(',', $field['options']) as $option)
                    <option value="{{ trim($option) }}">{{ trim($option) }}</option>
                @endforeach
            @endif
        </select>
    @else
        <input type="{{ $field['type'] }}"
            name="{{ $field['name'] }}"
            class="form-control"
            @if(!empty($field['required'])) required @endif>
    @endif
</div>
