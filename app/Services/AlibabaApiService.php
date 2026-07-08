<?php

namespace App\Services;

/**
 * Alibaba.com Open Platform — same IOP signing model as AliExpress.
 */
class AlibabaApiService extends AliExpressApiService
{
    protected string $channelLabel = 'Alibaba';

    protected string $tokenEnvKey = 'ALIBABA_ACCESS_TOKEN';

    public function __construct()
    {
        parent::__construct();

        $this->appKey = (string) (config('services.alibaba.app_key') ?: '');
        $this->appSecret = (string) (config('services.alibaba.app_secret') ?: '');
        $this->accessToken = config('services.alibaba.access_token');
        $base = (string) (config('services.alibaba.api_base') ?: 'https://openapi.alibaba.com');
        $this->apiBase = str_ends_with($base, '/sync') ? $base : rtrim($base, '/').'/sync';
        $this->signPath = '/sync';
        $this->tokenParam = 'access_token';
    }
}
