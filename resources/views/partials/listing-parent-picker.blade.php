{{--
    Parent picker (search / select an existing parent, or name a new parent) for the
    "Publish variation listing" window of every /listing-<marketplace> page.
    Included from partials.listing-sku-search so each listing page gets it without editing the page.
--}}
<script>window.__listingParentsUrl = '{{ url('/listing-common/existing-parents') }}';</script>
<script src="{{ asset('js/listing-parent-picker.js') }}?v={{ @filemtime(public_path('js/listing-parent-picker.js')) }}"></script>
