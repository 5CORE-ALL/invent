@props(['size' => 20, 'alt' => 'Edit'])

{{--
    Reusable edit icon. Renders the shared edit image so all pages can use a
    consistent icon. Usage:
        <x-edit-icon />
        <x-edit-icon :size="18" />
        <x-edit-icon :size="24" alt="Edit FAQ" />
--}}
<img src="{{ asset('images/edit-icon.png') }}" alt="{{ $alt }}"
    {{ $attributes->merge(['class' => 'edit-icon-img']) }}
    style="height: {{ $size }}px; width: {{ $size }}px; object-fit: contain; vertical-align: middle;">
