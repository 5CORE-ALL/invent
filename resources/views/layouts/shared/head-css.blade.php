@vite([
    'resources/js/head.js',
    'resources/js/config.js',
    'resources/scss/app.scss',
    'resources/scss/icons.scss'
    ])

{{-- Global: badges with light backgrounds must use black text for readability --}}
<style>
    .badge.bg-warning,
    .badge.bg-info,
    .badge.bg-light,
    .badge.bg-primary,
    .badge.bg-success,
    .badge.text-bg-warning,
    .badge.text-bg-info,
    .badge.text-bg-light,
    .badge.text-bg-primary,
    .badge.text-bg-success,
    .badge.bg-warning-subtle,
    .badge.bg-info-subtle,
    .badge.bg-light-subtle,
    .badge.bg-success-subtle,
    .badge.bg-primary-subtle,
    .badge.bg-secondary-subtle,
    .badge.bg-danger-subtle,
    .badge.bg-warning *,
    .badge.bg-info *,
    .badge.bg-light *,
    .badge.bg-primary *,
    .badge.bg-success * {
        color: #000 !important;
    }
</style>
