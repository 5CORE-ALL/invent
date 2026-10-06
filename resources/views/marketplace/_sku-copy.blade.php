<span class="mm-sku-copy">
    @if(!empty($detailUrl))
        <a href="{{ $detailUrl }}" class="text-decoration-none" onclick="event.stopPropagation();"><code>{{ $sku }}</code></a>
    @else
        <code>{{ $sku }}</code>
    @endif
    @if(trim((string) ($sku ?? '')) !== '')
        <button type="button" class="mm-sku-copy-btn" data-sku="{{ $sku }}" title="Copy SKU" aria-label="Copy SKU" onclick="event.stopPropagation(); event.preventDefault(); window.mmCopySku(this);">
            <i class="ri-file-copy-line" aria-hidden="true"></i>
        </button>
    @endif
</span>
@once
    <style>
        .mm-sku-copy {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            max-width: 100%;
        }
        .mm-sku-copy code {
            word-break: break-word;
        }
        .mm-sku-copy-btn {
            border: 0;
            background: transparent;
            color: #6c757d;
            padding: 0 2px;
            line-height: 1;
            cursor: pointer;
            flex: 0 0 auto;
        }
        .mm-sku-copy-btn:hover,
        .mm-sku-copy-btn:focus {
            color: #0d6efd;
        }
        .mm-sku-copy-btn.is-copied {
            color: #198754;
        }
    </style>
    <script>
        window.mmCopySku = function (btn) {
            var sku = btn.getAttribute('data-sku') || '';
            if (!sku) {
                return;
            }
            var done = function () {
                var icon = btn.querySelector('i');
                btn.classList.add('is-copied');
                btn.setAttribute('title', 'Copied');
                if (icon) {
                    icon.classList.remove('ri-file-copy-line');
                    icon.classList.add('ri-check-line');
                }
                setTimeout(function () {
                    btn.classList.remove('is-copied');
                    btn.setAttribute('title', 'Copy SKU');
                    if (icon) {
                        icon.classList.remove('ri-check-line');
                        icon.classList.add('ri-file-copy-line');
                    }
                }, 900);
            };
            var fallback = function () {
                var area = document.createElement('textarea');
                area.value = sku;
                area.setAttribute('readonly', '');
                area.style.position = 'fixed';
                area.style.left = '-9999px';
                document.body.appendChild(area);
                area.select();
                try {
                    document.execCommand('copy');
                    done();
                } catch (e) {}
                document.body.removeChild(area);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(sku).then(done).catch(fallback);
            } else {
                fallback();
            }
        };
    </script>
@endonce
