<?php

namespace App\Services\Support;

trait VideoMasterMarketplaceMethods
{
    /**
     * Video Master compatibility: push product videos to marketplace listing.
     *
     * @param  list<string>  $videos
     * @return array{success: bool, message: string}
     */
    public function updateVideos(string $identifier, array $videos, string $mode = 'replace'): array
    {
        $videos = array_values(array_filter(array_map('trim', $videos), fn ($v) => $v !== ''));
        if ($videos === [] && $mode !== 'replace') {
            return ['success' => true, 'message' => 'No videos to add; skipped.'];
        }
        if ($videos === []) {
            return ['success' => false, 'message' => 'At least one video URL is required.'];
        }

        foreach ($videos as $url) {
            if (! preg_match('#^https?://#i', $url)) {
                return ['success' => false, 'message' => 'Invalid video URL (must be http/https).'];
            }
        }

        return [
            'success' => false,
            'message' => 'Video push is not yet implemented for this marketplace API.',
        ];
    }

    /**
     * @param  list<string>  $videos
     * @return array{success: bool, message: string, dry_run?: bool}
     */
    public function dryRunUpdateVideos(string $identifier, array $videos): array
    {
        $videos = array_values(array_filter(array_map('trim', $videos), fn ($v) => $v !== ''));
        if ($videos === []) {
            return ['success' => false, 'message' => 'No videos to push.', 'dry_run' => true];
        }

        foreach ($videos as $url) {
            if (! preg_match('#^https?://#i', $url)) {
                return ['success' => false, 'message' => 'Dry run: invalid video URL.', 'dry_run' => true];
            }
        }

        return [
            'success' => true,
            'dry_run' => true,
            'message' => 'Dry run OK: would push '.count($videos).' video(s).',
        ];
    }

    /**
     * @param  list<string>  $videos
     * @return array{success: bool, message: string}
     */
    public function updateListingVideos(string $identifier, array $videos): array
    {
        return $this->updateVideos($identifier, $videos);
    }
}
