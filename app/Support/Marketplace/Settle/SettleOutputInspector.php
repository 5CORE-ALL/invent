<?php

namespace App\Support\Marketplace\Settle;

/**
 * Reads the text a marketplace command printed and finds failures that did NOT change its exit
 * code (e.g. "Failed to get access token" followed by exit 0, "Page 3 failed ... Skipping").
 *
 * Read-only text analysis: the fetch commands themselves are not touched.
 */
final class SettleOutputInspector
{
    public const AUTH = 'auth';

    public const PAGINATION = 'pagination';

    public const RATE_LIMIT = 'rate_limit';

    public const TIMEOUT = 'timeout';

    /** @var array<string, list<string>> type => regexes */
    private const PATTERNS = [
        self::AUTH => [
            '/unauthori[sz]ed/i',
            '/\b(?:http|status|code|error)[\s:=#]*(?:401|403)\b/i',
            '/invalid[_\s]grant/i',
            '/invalid[_\s]client/i',
            '/(?:fail\w*|unable|cannot|could\s+not|no)\b.{0,30}access[\s_-]?token/i',
            '/access[\s_-]?token.{0,40}(?:expired|invalid|rejected|missing|revoked)/i',
            '/token\s*exception/i',
            '/refresh[\s_-]?token.{0,40}(?:expired|invalid|revoked|fail)/i',
            '/authentication\s+(?:failed|error)/i',
            '/rejecting\s+the\s+LWA/i',
        ],
        self::PAGINATION => [
            '/\bpage\s+\d+\s+failed/i',
            '/too\s+many\s+consecutive\s+failures/i',
            '/stopped\s+at\s+the\s+09:00\s+PT\s+deadline/i',
            '/finished\s+with\s+\d+\s+failed\s+day/i',
            '/\bgave\s+up\b/i',
            '/(?:window|chunk)\s+\S+\s+failed/i',
            '/failed\s+to\s+fetch\s+(?:page|orders)/i',
            '/partial\s+(?:data|result|response)/i',
        ],
        self::RATE_LIMIT => [
            '/quota\s*exceeded/i',
            '/\b(?:http|status|code|error)[\s:=#]*429\b/i',
            '/rate[\s_-]?limit/i',
            '/too\s+many\s+requests/i',
            '/\bthrottl/i',
        ],
        self::TIMEOUT => [
            '/timed\s+out/i',
            '/timeout\s+(?:exceeded|reached)/i',
        ],
    ];

    /**
     * @return array{markers: list<array{type: string, line: string}>, error_class: ?string}
     */
    public static function inspect(string $output, string $error = ''): array
    {
        $markers = [];
        $perType = [];
        $text = $output.($error !== '' ? "\n".$error : '');

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            foreach (self::PATTERNS as $type => $patterns) {
                if (($perType[$type] ?? 0) >= 5) {
                    continue;
                }
                foreach ($patterns as $re) {
                    if (preg_match($re, $line)) {
                        $markers[] = ['type' => $type, 'line' => mb_substr($line, 0, 240)];
                        $perType[$type] = ($perType[$type] ?? 0) + 1;
                        break;
                    }
                }
            }
        }

        return ['markers' => $markers, 'error_class' => self::classify($markers)];
    }

    /**
     * Most serious class first: auth > rate_limit > timeout > pagination.
     *
     * @param  list<array{type: string, line: string}>  $markers
     */
    public static function classify(array $markers): ?string
    {
        $types = array_column($markers, 'type');
        foreach ([self::AUTH, self::RATE_LIMIT, self::TIMEOUT, self::PAGINATION] as $t) {
            if (in_array($t, $types, true)) {
                return $t;
            }
        }

        return null;
    }

    /**
     * Verification checks derived from stored markers. Auth / pagination / timeout markers in the
     * latest run are failures; rate-limit mentions are only a warning.
     *
     * @param  list<array{type: string, line: string}>|null  $markers
     * @return list<array{code: string, status: string, message: string, data: array<string, mixed>}>
     */
    public static function checks(?array $markers): array
    {
        if ($markers === null) {
            return [];
        }
        $checks = [];
        $by = [];
        foreach ($markers as $m) {
            $by[$m['type']][] = $m['line'];
        }
        $map = [
            self::AUTH => ['auth_failure', 'fail', 'API authentication problem reported by the command'],
            self::PAGINATION => ['incomplete_pagination', 'fail', 'Command reported failed pages / windows (data may be incomplete)'],
            self::TIMEOUT => ['command_timeout', 'fail', 'Command reported a timeout'],
            self::RATE_LIMIT => ['rate_limited', 'warn', 'Command hit API rate limits'],
        ];
        foreach ($map as $type => [$code, $status, $msg]) {
            if (! empty($by[$type])) {
                $checks[] = [
                    'code' => $code,
                    'status' => $status,
                    'message' => $msg.': '.$by[$type][0],
                    'data' => ['lines' => array_slice($by[$type], 0, 3)],
                ];
            }
        }

        return $checks;
    }
}
