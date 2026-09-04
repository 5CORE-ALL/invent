<?php

namespace App\Support;

use App\Models\GoogleAdsCampaign;
use Illuminate\Support\Facades\Cache;

/**
 * YouTube VIDEO campaigns cannot be paused through CampaignService
 * (Google Ads API returns MUTATE_NOT_ALLOWED / VIDEO). Google Ads Scripts
 * AdsApp.videoCampaigns().pause() is the supported write path.
 */
final class GoogleYoutubeVideoPause
{
    public const CACHE_KEY = 'google_youtube_video_pause_queue_v1';

    public const SCRIPT_CACHE_KEY = 'google_youtube_video_pause_last_script_v1';

    public const CACHE_TTL = 604800;

    public static function token(): string
    {
        $configured = trim((string) config('services.google_ads.youtube_pause_script_token', ''));
        if ($configured !== '') {
            return $configured;
        }

        return hash_hmac('sha256', 'google-youtube-video-pause-queue', (string) config('app.key'));
    }

    public static function tokenMatches(?string $given): bool
    {
        $given = trim((string) $given);

        return $given !== '' && hash_equals(self::token(), $given);
    }

    /**
     * @param  array<string, string>  $idToName
     */
    public static function enqueue(array $idToName): void
    {
        $queue = self::pending();
        foreach ($idToName as $id => $name) {
            $cid = preg_replace('/\D/', '', (string) $id) ?? '';
            if ($cid === '') {
                continue;
            }
            $queue[$cid] = [
                'name' => (string) $name,
                'queued_at' => now()->toIso8601String(),
            ];
        }
        Cache::put(self::CACHE_KEY, $queue, self::CACHE_TTL);
    }

    /**
     * @return array<string, array{name: string, queued_at: string}>
     */
    public static function pending(): array
    {
        $raw = Cache::get(self::CACHE_KEY, []);

        return is_array($raw) ? $raw : [];
    }

    /**
     * @return list<string>
     */
    public static function pendingIds(): array
    {
        return array_keys(self::pending());
    }

    public static function storeLastScript(string $script): void
    {
        Cache::put(self::SCRIPT_CACHE_KEY, $script, self::CACHE_TTL);
    }

    public static function lastScript(): string
    {
        $s = Cache::get(self::SCRIPT_CACHE_KEY, '');

        return is_string($s) ? $s : '';
    }

    public static function currentScript(string $callbackUrl): string
    {
        $ids = self::pendingIds();
        if ($ids !== []) {
            $script = self::oneShotScript($ids, $callbackUrl);
            self::storeLastScript($script);

            return $script;
        }

        return self::lastScript();
    }

    /**
     * @param  list<string|int>  $ids
     */
    public static function markPaused(array $ids): int
    {
        $queue = self::pending();
        $nowIds = [];
        foreach ($ids as $id) {
            $cid = preg_replace('/\D/', '', (string) $id) ?? '';
            if ($cid === '') {
                continue;
            }
            $nowIds[] = $cid;
            unset($queue[$cid]);
        }
        Cache::put(self::CACHE_KEY, $queue, self::CACHE_TTL);
        if ($nowIds === []) {
            return 0;
        }
        GoogleAdsCampaign::query()
            ->whereIn('campaign_id', $nowIds)
            ->update(['campaign_status' => 'PAUSED']);

        return count(array_unique($nowIds));
    }

    public static function isVideoMutateBlocked(\Throwable $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, 'MUTATE_NOT_ALLOWED')
            || (str_contains($msg, 'Mutates are not allowed') && str_contains($msg, 'VIDEO'));
    }

    public static function isVideoChannel(?string $channel): bool
    {
        $c = strtoupper(trim((string) $channel));

        return $c === '' || $c === 'VIDEO';
    }

    /**
     * @param  list<string>  $campaignIds
     * @return array<string, string>
     */
    public static function channelTypesByCampaignId(array $campaignIds): array
    {
        $campaignIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (string) $id, $campaignIds),
            static fn (string $id) => $id !== ''
        )));
        if ($campaignIds === []) {
            return [];
        }

        $rows = GoogleAdsCampaign::query()
            ->select('campaign_id', 'advertising_channel_type')
            ->whereIn('campaign_id', $campaignIds)
            ->orderByDesc('date')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $cid = (string) ($row->campaign_id ?? '');
            if ($cid === '' || isset($out[$cid])) {
                continue;
            }
            $out[$cid] = strtoupper(trim((string) ($row->advertising_channel_type ?? '')));
        }

        return $out;
    }

    /**
     * @param  list<string>  $campaignIds
     */
    public static function oneShotScript(array $campaignIds, string $callbackUrl): string
    {
        $ids = self::numericIdList($campaignIds);

        return self::wrapScript(
            self::sharedHelpers()
            ."function main() {\n"
            .'  var ids = ['.$ids."];\n"
            .'  var token = '.json_encode(self::token()).";\n"
            .'  var callbackUrl = '.json_encode($callbackUrl).";\n"
            ."  var paused = pauseIds(ids);\n"
            ."  reportPaused(callbackUrl, token, paused);\n"
            ."  Logger.log('Done. Paused ' + paused.length + ' of ' + ids.length + '.');\n"
            ."}\n"
        );
    }

    public static function watcherScript(string $queueUrl, string $callbackUrl): string
    {
        return self::wrapScript(
            self::sharedHelpers()
            ."function main() {\n"
            .'  var token = '.json_encode(self::token()).";\n"
            .'  var queueUrl = '.json_encode($queueUrl).";\n"
            .'  var callbackUrl = '.json_encode($callbackUrl).";\n"
            ."  var resp = UrlFetchApp.fetch(queueUrl, { muteHttpExceptions: true });\n"
            ."  var data = JSON.parse(resp.getContentText() || '{}');\n"
            ."  var raw = data.campaign_ids || [];\n"
            ."  var ids = [];\n"
            ."  for (var i = 0; i < raw.length; i++) {\n"
            ."    var n = Number(String(raw[i]).replace(/\\D/g, ''));\n"
            ."    if (n) ids.push(n);\n"
            ."  }\n"
            ."  if (!ids.length) {\n"
            ."    Logger.log('Nothing queued.');\n"
            ."    return;\n"
            ."  }\n"
            ."  var paused = pauseIds(ids);\n"
            ."  reportPaused(callbackUrl, token, paused);\n"
            ."  Logger.log('Done. Paused ' + paused.length + ' of ' + ids.length + '.');\n"
            ."}\n"
        );
    }

    /**
     * @param  list<string>  $campaignIds
     */
    private static function numericIdList(array $campaignIds): string
    {
        $nums = [];
        foreach ($campaignIds as $id) {
            $cid = preg_replace('/\D/', '', (string) $id) ?? '';
            if ($cid !== '') {
                $nums[$cid] = $cid;
            }
        }

        return implode(', ', array_values($nums));
    }

    private static function wrapScript(string $body): string
    {
        $header = <<<'JS'
/**
 * Invent — pause YouTube VIDEO campaigns on this Google Ads account.
 * Google Ads API cannot pause VIDEO (MUTATE_NOT_ALLOWED). This script can.
 *
 * Tools and settings → Bulk actions → Scripts → + → paste → Save → Authorize → Run
 */

JS;

        return $header.$body;
    }

    private static function sharedHelpers(): string
    {
        return <<<'JS'
function pauseIds(ids) {
  var pending = {};
  var paused = [];
  for (var i = 0; i < ids.length; i++) {
    pending[String(ids[i])] = true;
  }
  pauseIterator(AdsApp.videoCampaigns().withIds(ids).get(), pending, paused);
  var leftover = [];
  for (var key in pending) {
    if (pending[key]) leftover.push(Number(key));
  }
  if (leftover.length) {
    pauseIterator(AdsApp.campaigns().withIds(leftover).get(), pending, paused);
  }
  for (var miss in pending) {
    if (pending[miss]) Logger.log('Not found: ' + miss);
  }
  return paused;
}

function pauseIterator(iter, pending, paused) {
  while (iter.hasNext()) {
    var c = iter.next();
    var id = String(c.getId());
    pending[id] = false;
    if (c.isEnabled()) {
      c.pause();
      Logger.log('Paused: ' + c.getName() + ' (' + id + ')');
    } else {
      Logger.log('Already not enabled: ' + c.getName() + ' (' + id + ')');
    }
    paused.push(id);
  }
}

function reportPaused(callbackUrl, token, pausedIds) {
  if (!callbackUrl || !pausedIds.length) return;
  try {
    UrlFetchApp.fetch(callbackUrl, {
      method: 'post',
      contentType: 'application/json',
      payload: JSON.stringify({ token: token, paused_ids: pausedIds }),
      muteHttpExceptions: true
    });
  } catch (e) {
    Logger.log('Callback skipped: ' + e);
  }
}

JS;
    }
}
