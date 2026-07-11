<?php

namespace App\Services;

/**
 * Placeholder auth helper for Marketplace Manager Connect page.
 * Reverb uses client_credentials / personal token (no browser OAuth like AE).
 */
class ReverbAuthService
{
    public function getAuthorizeUrl(?string $state = null): string
    {
        return 'https://reverb.com/my/api_settings';
    }
}
