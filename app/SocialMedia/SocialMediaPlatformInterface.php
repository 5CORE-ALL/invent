<?php

namespace App\SocialMedia;

use App\Models\SocialMediaAccount;
use Carbon\CarbonInterface;

interface SocialMediaPlatformInterface
{
    public function platform(): string;

    /**
     * @return array<string, string> metric => available|not_available
     */
    public function metricCatalog(): array;

    /**
     * @return array{profile: array<string, mixed>, days: list<array{date: string, metrics: list<SocialMetric>, raw: array<string, mixed>}>, posts: list<array<string, mixed>>}
     */
    public function fetch(SocialMediaAccount $account, CarbonInterface $since): array;
}
