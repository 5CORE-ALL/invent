@auth
    @if (auth()->user()->is5CoreMember())
        <a href="{{ url('/help-desk-faqs') }}" class="help-desk-fab" title="5Core Help Desk" aria-label="Open 5Core Help Desk">
            <img src="{{ asset('images/chat-icon.png') }}" alt="5Core Help Desk" class="help-desk-fab-icon">
        </a>

        <style>
            .help-desk-fab {
                position: fixed;
                bottom: 24px;
                right: 24px;
                z-index: 9998;
                display: block;
                width: 100px;
                height: 100px;
                line-height: 0;
            }

            .help-desk-fab-icon {
                width: 100%;
                height: 100%;
                object-fit: contain;
                filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.15));
                transition: transform 0.2s ease;
            }

            .help-desk-fab:hover .help-desk-fab-icon {
                transform: scale(1.06);
            }
        </style>
    @endif
@endauth
