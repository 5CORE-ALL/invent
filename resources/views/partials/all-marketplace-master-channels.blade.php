@php
    $allMpMaster = $allMpMaster ?? 'bullet';
    $allMpConfig = app(\App\Services\Support\AllMarketplaceChannelRegistry::class)->jsConfig($allMpMaster);
@endphp
<script>
window.__ALL_MP__ = @json($allMpConfig);
</script>
