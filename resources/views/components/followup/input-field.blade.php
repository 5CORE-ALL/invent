{{-- Reusable form input for follow-up modals --}}
@props([
    'label',
    'name',
    'type' => 'text',
    'required' => false,
    'placeholder' => '',
    'wrapperClass' => 'col-md-6 mb-3',
])
<div class="{{ $wrapperClass }}">
    <label for="{{ $name }}" class="form-label">{{ $label }}@if($required)<span class="text-danger">*</span>@endif</label>
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        class="form-control @error($name) is-invalid @enderror"
        placeholder="{{ $placeholder }}"
        {{ $required ? 'required' : '' }}
        {{ $attributes }}
    />
    <div class="invalid-feedback" data-error-for="{{ $name }}"></div>
</div>
