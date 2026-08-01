@php
    $images = $images ?? [];
    $mainImage = $mainImage ?? ($images[0] ?? null);
    $galleryId = $galleryId ?? ('gallery-'.uniqid());
@endphp
@if($mainImage)
    <div class="text-center" data-gallery-wrap="{{ $galleryId }}">
        <img id="{{ $galleryId }}-main" src="{{ $mainImage }}" alt="" class="img-fluid ae-main-image mb-3 rounded border">
        @if(count($images) > 1)
            <div class="d-flex flex-wrap gap-2 justify-content-center ae-gallery" data-gallery="{{ $galleryId }}">
                @foreach($images as $img)
                    <img src="{{ $img }}" alt="" class="ae-gallery-thumb {{ $img === $mainImage ? 'active' : '' }}"
                         data-gallery="{{ $galleryId }}" data-src="{{ $img }}">
                @endforeach
            </div>
        @endif
    </div>
@else
    <p class="text-muted mb-0 small">No images available</p>
@endif
