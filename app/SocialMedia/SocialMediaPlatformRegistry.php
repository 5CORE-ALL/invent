<?php

namespace App\SocialMedia;

use App\SocialMedia\Platforms\FacebookService;
use App\SocialMedia\Platforms\InstagramService;
use App\SocialMedia\Platforms\LinkedInService;
use App\SocialMedia\Platforms\TikTokService;
use App\SocialMedia\Platforms\XService;
use App\SocialMedia\Platforms\YouTubeService;
use InvalidArgumentException;

class SocialMediaPlatformRegistry
{
    /** @var array<string, class-string<SocialMediaPlatformInterface>> */
    private array $map = [
        'facebook' => FacebookService::class,
        'instagram' => InstagramService::class,
        'linkedin' => LinkedInService::class,
        'youtube' => YouTubeService::class,
        'tiktok' => TikTokService::class,
        'x' => XService::class,
    ];

    public function get(string $platform): SocialMediaPlatformInterface
    {
        $class = $this->map[$platform] ?? null;
        if ($class === null) {
            throw new InvalidArgumentException('Unsupported social platform.');
        }

        return app($class);
    }

    /**
     * @return list<string>
     */
    public function platforms(): array
    {
        return array_keys($this->map);
    }
}
