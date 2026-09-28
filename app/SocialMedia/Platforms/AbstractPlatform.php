<?php

namespace App\SocialMedia\Platforms;

use App\Models\SocialMediaAccount;
use App\SocialMedia\Exceptions\SocialMediaAuthException;
use App\SocialMedia\Platforms\Concerns\CallsOfficialApi;
use App\SocialMedia\SocialMediaPlatformInterface;
use App\SocialMedia\SocialMetric;
use Carbon\Carbon;
use Carbon\CarbonInterface;

abstract class AbstractPlatform implements SocialMediaPlatformInterface
{
    use CallsOfficialApi;

    public function metricCatalog(): array
    {
        return config('social_media.metrics.'.$this->platform(), []);
    }

    protected function token(SocialMediaAccount $account): string
    {
        $token = (string) $account->access_token;
        if ($token === '') {
            throw new SocialMediaAuthException('No access token is stored — reconnect the account.');
        }
        if ($account->token_expires_at && $account->token_expires_at->isPast()) {
            throw new SocialMediaAuthException(ucfirst($this->platform()).' access token expired — reconnect the account.');
        }

        return $token;
    }

    protected function graph(string $path): string
    {
        return 'https://graph.facebook.com/'.config('social_media.graph_version', 'v21.0').'/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, float|int|string|null>  $values  present API numbers only
     * @return list<SocialMetric>
     */
    protected function mapMetrics(array $values): array
    {
        $metrics = [];
        foreach ($this->metricCatalog() as $name => $availability) {
            if ($availability !== SocialMetric::AVAILABLE || ! array_key_exists($name, $values) || $values[$name] === null || $values[$name] === '') {
                $metrics[] = SocialMetric::missing($name);
                continue;
            }
            $metrics[] = SocialMetric::available($name, $values[$name]);
        }

        return $this->withEngagement($metrics);
    }

    /**
     * @param  list<SocialMetric>  $metrics
     * @return list<SocialMetric>
     */
    protected function withEngagement(array $metrics): array
    {
        $byName = [];
        foreach ($metrics as $metric) {
            $byName[$metric->name] = $metric;
        }
        $parts = ['likes', 'comments', 'shares', 'saves'];
        $sum = 0.0;
        $any = false;
        foreach ($parts as $part) {
            if (($byName[$part] ?? null)?->isAvailable()) {
                $sum += (float) $byName[$part]->value;
                $any = true;
            }
        }
        $metrics[] = $any ? SocialMetric::available('engagement', $sum) : SocialMetric::missing('engagement');

        $engagement = $any ? $sum : null;
        $impressions = ($byName['impressions'] ?? null)?->isAvailable() ? (float) $byName['impressions']->value : null;
        $reach = ($byName['reach'] ?? null)?->isAvailable() ? (float) $byName['reach']->value : null;
        $denominator = $impressions ?? $reach;
        if ($engagement !== null && $denominator !== null && $denominator > 0) {
            $metrics[] = SocialMetric::available('engagement_rate', $engagement / $denominator);
        } else {
            $metrics[] = SocialMetric::missing('engagement_rate');
        }

        return $metrics;
    }

    protected function insightDate(string $endTime): string
    {
        return Carbon::parse($endTime)->subDay()->toDateString();
    }

    protected function since(CarbonInterface $since): string
    {
        return $since->copy()->startOfDay()->toDateString();
    }
}
