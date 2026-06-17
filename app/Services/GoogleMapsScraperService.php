<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class GoogleMapsScraperService
{
    private const CITY_QUERY_CHUNK_SIZE = 30;
    private const MAX_LOCAL_PAGES_PER_CITY = 100;
    private const EMPTY_LOCAL_PAGES_BEFORE_STOP = 2;

    private Client $client;

    public function __construct(
        private readonly WebsiteContactExtractorService $contactExtractor,
        ?Client $client = null
    ) {
        $this->client = $client ?: new Client([
            'timeout' => 18,
            'connect_timeout' => 8,
            'allow_redirects' => ['max' => 4],
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
            ],
        ]);
    }

    public function search(
        string $query,
        ?string $location = null,
        int $limit = 10,
        ?callable $progressCallback = null,
        ?callable $controlCallback = null
    ): array {
        $limit = max(1, min($limit, 5000));
        $searchQueries = $this->buildSearchQueries($query, $location, $limit);
        $records = [];
        $lastSearchQuery = '';

        Log::info('Google Maps extractor search started', [
            'query' => $query,
            'location_preview' => Str::limit((string) $location, 500, '...'),
            'search_query_count' => count($searchQueries),
            'search_query_preview' => array_slice($searchQueries, 0, 5),
            'limit' => $limit,
        ]);

        $this->reportProgress($progressCallback, [
            'status' => 'running',
            'message' => 'Prepared ' . count($searchQueries) . ' city/search attempt(s).',
            'records' => 0,
            'total_queries' => count($searchQueries),
            'current_query' => null,
        ]);

        foreach ($searchQueries as $attempt => $searchQuery) {
            $controlAction = $this->waitIfPausedOrStopped($controlCallback, $progressCallback, count($this->dedupeRecords($records)));

            if (in_array($controlAction, ['stop', 'cancel'], true)) {
                return array_slice($this->dedupeRecords($records), 0, $limit);
            }

            $lastSearchQuery = $searchQuery;

            if ($attempt > 0 && $attempt % self::CITY_QUERY_CHUNK_SIZE === 0) {
                Log::info('Google Maps extractor starting next throttled city chunk', [
                    'chunk' => (int) floor($attempt / self::CITY_QUERY_CHUNK_SIZE) + 1,
                    'chunk_size' => self::CITY_QUERY_CHUNK_SIZE,
                    'total_queries' => count($searchQueries),
                ]);

                $this->reportProgress($progressCallback, [
                    'message' => 'Starting throttled city batch ' . ((int) floor($attempt / self::CITY_QUERY_CHUNK_SIZE) + 1) . '.',
                    'records' => count($this->dedupeRecords($records)),
                ]);
            }

            Log::info('Google Maps extractor search attempt started', [
                'attempt' => $attempt + 1,
                'search_query' => $searchQuery,
                'current_records' => count($this->dedupeRecords($records)),
            ]);

            $searchUrls = $this->buildSearchUrls($searchQuery);
            $this->reportProgress($progressCallback, [
                'message' => 'Fetching ' . $this->humanSearchQuery($searchQuery) . ' (' . ($attempt + 1) . ' of ' . count($searchQueries) . ').',
                'records' => count($this->dedupeRecords($records)),
                'current_query' => $searchQuery,
                'current_query_number' => $attempt + 1,
                'total_queries' => count($searchQueries),
            ]);

            $consecutiveEmptyLocalPages = 0;

            foreach ($searchUrls as $sourceIndex => $sourceUrl) {
                $controlAction = $this->waitIfPausedOrStopped($controlCallback, $progressCallback, count($this->dedupeRecords($records)));

                if (in_array($controlAction, ['stop', 'cancel'], true)) {
                    return array_slice($this->dedupeRecords($records), 0, $limit);
                }

                $source = $sourceUrl['source'];
                $url = $sourceUrl['url'];
                $page = $sourceUrl['page'];

                $this->reportProgress($progressCallback, [
                    'message' => 'Fetching ' . $this->humanSearchQuery($searchQuery) . ' page ' . $page . ' via ' . str_replace('_', ' ', $source) . '.',
                    'records' => count($this->dedupeRecords($records)),
                    'current_source' => $source,
                    'current_page' => $page,
                ]);

                $html = $this->fetch($url);

                if (! $html) {
                    Log::warning('Google Maps extractor fetch returned no HTML', [
                        'source' => $source,
                        'url' => $url,
                    ]);

                    continue;
                }

                $sourceRecords = $this->extractRecords($html, $source, $url);

                Log::info('Google Maps extractor source parsed', [
                    'source' => $source,
                    'url' => $url,
                    'html_length' => strlen($html),
                    'records_found' => count($sourceRecords),
                ]);

                if (count($sourceRecords) === 0) {
                    Log::debug('Google Maps extractor zero-result HTML summary', [
                        'source' => $source,
                        'markers' => [
                            'maps_place' => substr_count($html, '/maps/place/'),
                            'rllt' => substr_count($html, 'rllt'),
                            'dbg0pd' => substr_count($html, 'dbg0pd'),
                            'aria_label' => substr_count($html, 'aria-label'),
                            'preload_map' => substr_count($html, '/search?tbm=map'),
                        ],
                        'text_sample' => Str::limit($this->cleanText($html), 500, ''),
                    ]);
                }

                $beforeDedupeCount = count($this->dedupeRecords($records));
                $records = array_merge($records, $sourceRecords);
                $dedupedCount = count($this->dedupeRecords($records));
                $newUniqueCount = max(0, $dedupedCount - $beforeDedupeCount);

                $this->reportProgress($progressCallback, [
                    'message' => 'Parsed ' . count($sourceRecords) . ' record(s) from ' . $this->humanSearchQuery($searchQuery) . ' page ' . $page . '. New unique: ' . $newUniqueCount . '. Total unique: ' . $dedupedCount . '.',
                    'records' => $dedupedCount,
                    'last_found' => count($sourceRecords),
                    'records_batch' => $sourceRecords,
                ]);

                if ($source === 'google_local') {
                    $consecutiveEmptyLocalPages = $newUniqueCount > 0 ? 0 : $consecutiveEmptyLocalPages + 1;

                    if ($consecutiveEmptyLocalPages >= self::EMPTY_LOCAL_PAGES_BEFORE_STOP) {
                        $this->reportProgress($progressCallback, [
                            'message' => 'Stopping local pagination for ' . $this->humanSearchQuery($searchQuery) . ' after ' . $consecutiveEmptyLocalPages . ' page(s) with no new unique records.',
                            'records' => $dedupedCount,
                        ]);

                        break;
                    }
                }

                if ($source === 'google_maps') {
                    foreach ($this->extractMapPreloadUrls($html) as $preloadUrl) {
                        $preloadHtml = $this->fetch($preloadUrl);

                        if (! $preloadHtml) {
                            continue;
                        }

                        $preloadRecords = $this->extractRecords($preloadHtml, 'google_maps_preload', $preloadUrl);

                        Log::info('Google Maps extractor preload parsed', [
                            'url' => $preloadUrl,
                            'payload_length' => strlen($preloadHtml),
                            'records_found' => count($preloadRecords),
                        ]);

                        if (count($preloadRecords) === 0) {
                            Log::debug('Google Maps extractor zero-result preload summary', [
                                'markers' => [
                                    'maps_place' => substr_count($preloadHtml, '/maps/place/'),
                                    'http_links' => substr_count($preloadHtml, 'http'),
                                    'music' => substr_count(strtolower($preloadHtml), 'music'),
                                    'school' => substr_count(strtolower($preloadHtml), 'school'),
                                ],
                                'text_sample' => Str::limit($this->cleanText($preloadHtml), 800, ''),
                            ]);
                        }

                        $records = array_merge($records, $preloadRecords);
                        $dedupedCount = count($this->dedupeRecords($records));

                        $this->reportProgress($progressCallback, [
                            'message' => 'Parsed ' . count($preloadRecords) . ' preload record(s). Total unique: ' . $dedupedCount . '.',
                            'records' => $dedupedCount,
                            'last_found' => count($preloadRecords),
                            'records_batch' => $preloadRecords,
                        ]);

                        if ($dedupedCount >= $limit) {
                            break 3;
                        }
                    }
                }

                if (count($this->dedupeRecords($records)) >= $limit) {
                    break 2;
                }

                if ($sourceIndex < count($searchUrls) - 1) {
                    $pageDelay = $this->delaySecondsForPage($sourceIndex);
                    $this->reportProgress($progressCallback, [
                        'message' => 'Short page delay before continuing same city (' . $pageDelay . 's).',
                        'records' => count($this->dedupeRecords($records)),
                    ]);
                    $controlAction = $this->controlledSleep($pageDelay, $controlCallback, $progressCallback, count($this->dedupeRecords($records)));

                    if (in_array($controlAction, ['stop', 'cancel'], true)) {
                        return array_slice($this->dedupeRecords($records), 0, $limit);
                    }
                }
            }

            if (count($this->dedupeRecords($records)) < $limit && $attempt < count($searchQueries) - 1) {
                $delaySeconds = $this->delaySecondsForAttempt($attempt);

                Log::info('Google Maps extractor sleeping before next attempt', [
                    'seconds' => $delaySeconds,
                    'records_so_far' => count($this->dedupeRecords($records)),
                ]);

                $this->reportProgress($progressCallback, [
                    'message' => 'City delay before next search (' . $delaySeconds . 's). Unique records so far: ' . count($this->dedupeRecords($records)) . '.',
                    'records' => count($this->dedupeRecords($records)),
                ]);

                $controlAction = $this->controlledSleep($delaySeconds, $controlCallback, $progressCallback, count($this->dedupeRecords($records)));

                if (in_array($controlAction, ['stop', 'cancel'], true)) {
                    return array_slice($this->dedupeRecords($records), 0, $limit);
                }
            }
        }

        $records = array_slice($this->dedupeRecords($records), 0, $limit);

        Log::info('Google Maps extractor search parsed records', [
            'search_query' => $lastSearchQuery,
            'records_after_dedupe' => count($records),
        ]);

        $this->reportProgress($progressCallback, [
            'message' => 'Cleaning and enriching ' . count($records) . ' record(s).',
            'records' => count($records),
        ]);

        $quickEnrichment = count($records) > 10;
        $enrichedRecords = $this->dedupeRecords(array_map(fn (array $record): array => $this->enrichRecord($record, $quickEnrichment), $records));

        Log::info('Google Maps extractor search completed', [
            'search_query' => $lastSearchQuery,
            'records_returned' => count($enrichedRecords),
        ]);

        $this->reportProgress($progressCallback, [
            'status' => 'completed',
            'message' => 'Scraper completed with ' . count($enrichedRecords) . ' unique record(s).',
            'records' => count($enrichedRecords),
        ]);

        return $enrichedRecords;
    }

    public function buildSearchPlan(string $query, ?string $location, int $limit): array
    {
        return $this->buildSearchQueries($query, $location, max(1, min($limit, 5000)));
    }

    public function buildSearchUrlPlan(string $searchQuery): array
    {
        return $this->buildSearchUrls($searchQuery);
    }

    public function scrapeSearchUrl(string $searchQuery, array $sourceUrl): array
    {
        $source = $sourceUrl['source'] ?? 'google_local';
        $url = $sourceUrl['url'] ?? '';
        $page = (int) ($sourceUrl['page'] ?? 1);
        $records = [];

        if ($url === '') {
            return [
                'records' => [],
                'message' => 'Skipped empty URL.',
                'source' => $source,
                'page' => $page,
            ];
        }

        $html = $this->fetch($url);

        if (! $html) {
            return [
                'records' => [],
                'message' => 'No HTML returned for ' . $this->humanSearchQuery($searchQuery) . ' page ' . $page . '.',
                'source' => $source,
                'page' => $page,
            ];
        }

        $sourceRecords = $this->extractRecords($html, $source, $url);
        $records = array_merge($records, $sourceRecords);

        if ($source === 'google_maps') {
            foreach ($this->extractMapPreloadUrls($html) as $preloadUrl) {
                $preloadHtml = $this->fetch($preloadUrl);

                if (! $preloadHtml) {
                    continue;
                }

                $records = array_merge(
                    $records,
                    $this->extractRecords($preloadHtml, 'google_maps_preload', $preloadUrl)
                );
            }
        }

        return [
            'records' => $this->dedupeRecords($records),
            'message' => 'Parsed ' . count($records) . ' raw record(s) from ' . $this->humanSearchQuery($searchQuery) . ' page ' . $page . ' via ' . str_replace('_', ' ', $source) . '.',
            'source' => $source,
            'page' => $page,
        ];
    }

    private function buildSearchQueries(string $query, ?string $location, int $limit): array
    {
        $query = trim($query);
        $location = trim((string) $location);
        $base = trim($query . ' ' . $location);
        $queries = [];
        $locationKey = Str::lower($location);
        $locationParts = array_values(array_filter(array_map('trim', explode(',', $location))));

        if (count($locationParts) > 3) {
            $country = array_pop($locationParts);
            $state = array_pop($locationParts);

            foreach ($this->uniqueCities($locationParts) as $city) {
                $queries[] = trim($query . ' in ' . $city . ', ' . $state . ', ' . $country);
            }

            return array_values(array_unique(array_filter($queries)));
        }

        $queries[] = $base !== '' ? $base : $query;

        $cityMap = [
            'california' => ['Los Angeles', 'San Diego', 'San Francisco', 'San Jose', 'Sacramento', 'Fresno', 'Long Beach', 'Oakland', 'Pasadena', 'Santa Barbara', 'Irvine', 'Anaheim', 'Riverside', 'Burbank', 'Glendale', 'Santa Monica', 'Beverly Hills', 'Torrance', 'Costa Mesa', 'Huntington Beach', 'Newport Beach', 'San Bernardino', 'Ontario', 'Bakersfield', 'Stockton', 'Modesto', 'Chula Vista', 'Oceanside', 'Carlsbad', 'Santa Ana', 'Fullerton', 'Pomona', 'Berkeley', 'Palo Alto', 'Mountain View', 'San Mateo', 'Santa Clara', 'Sunnyvale', 'Concord', 'Walnut Creek'],
            'ca' => ['Los Angeles', 'San Diego', 'San Francisco', 'San Jose', 'Sacramento', 'Fresno', 'Long Beach', 'Oakland', 'Pasadena', 'Santa Barbara', 'Irvine', 'Anaheim', 'Riverside', 'Burbank', 'Glendale', 'Santa Monica', 'Beverly Hills', 'Torrance', 'Costa Mesa', 'Huntington Beach', 'Newport Beach', 'San Bernardino', 'Ontario', 'Bakersfield', 'Stockton', 'Modesto', 'Chula Vista', 'Oceanside', 'Carlsbad', 'Santa Ana', 'Fullerton', 'Pomona', 'Berkeley', 'Palo Alto', 'Mountain View', 'San Mateo', 'Santa Clara', 'Sunnyvale', 'Concord', 'Walnut Creek'],
            'texas' => ['Houston', 'Dallas', 'Austin', 'San Antonio', 'Fort Worth', 'El Paso', 'Arlington', 'Plano'],
            'florida' => ['Miami', 'Orlando', 'Tampa', 'Jacksonville', 'Fort Lauderdale', 'St. Petersburg'],
            'new york' => ['New York', 'Brooklyn', 'Queens', 'Buffalo', 'Rochester', 'Albany'],
        ];

        $cityMapKey = $locationKey;

        foreach (array_keys($cityMap) as $knownLocation) {
            if (str_contains($locationKey, $knownLocation)) {
                $cityMapKey = $knownLocation;
                break;
            }
        }

        $cities = $cityMap[$cityMapKey] ?? [];
        foreach ($cities as $city) {
            $queries[] = trim($query . ' in ' . $city . ', ' . ($location ?: ''));
        }

        return array_values(array_unique(array_filter($queries)));
    }

    private function uniqueCities(array $cities): array
    {
        return array_values(array_unique(array_filter(array_map('trim', $cities))));
    }

    private function delaySecondsForAttempt(int $attempt): int
    {
        if ($attempt === 0) {
            return random_int(2, 4);
        }

        if (($attempt + 1) % self::CITY_QUERY_CHUNK_SIZE === 0) {
            return random_int(25, 40);
        }

        return random_int(3, 7);
    }

    private function delaySecondsForPage(int $sourceIndex): int
    {
        return $sourceIndex === 0 ? random_int(2, 3) : random_int(3, 5);
    }

    private function waitIfPausedOrStopped(?callable $controlCallback, ?callable $progressCallback, int $recordCount): ?string
    {
        if (! $controlCallback) {
            return null;
        }

        $action = $controlCallback();

        while ($action === 'pause') {
            $this->reportProgress($progressCallback, [
                'status' => 'paused',
                'message' => 'Extraction paused. Waiting for resume...',
                'records' => $recordCount,
            ]);
            sleep(2);
            $action = $controlCallback();
        }

        if (in_array($action, ['stop', 'cancel'], true)) {
            $this->reportProgress($progressCallback, [
                'status' => $action === 'cancel' ? 'cancelled' : 'stopped',
                'message' => $action === 'cancel'
                    ? 'Extraction cancellation requested. Stopping current run...'
                    : 'Extraction stop requested. Keeping fetched records...',
                'records' => $recordCount,
            ]);

            return $action;
        }

        return null;
    }

    private function controlledSleep(int $seconds, ?callable $controlCallback, ?callable $progressCallback, int $recordCount): ?string
    {
        for ($elapsed = 0; $elapsed < $seconds; $elapsed++) {
            $action = $this->waitIfPausedOrStopped($controlCallback, $progressCallback, $recordCount);

            if (in_array($action, ['stop', 'cancel'], true)) {
                return $action;
            }

            sleep(1);
        }

        return null;
    }

    private function buildSearchUrls(string $searchQuery): array
    {
        $encoded = rawurlencode($searchQuery);
        $urls = [[
            'source' => 'google_maps',
            'page' => 1,
            'url' => 'https://www.google.com/maps/search/' . $encoded . '?hl=en',
        ]];

        for ($page = 1; $page <= self::MAX_LOCAL_PAGES_PER_CITY; $page++) {
            $start = ($page - 1) * 10;
            $urls[] = [
                'source' => 'google_local',
                'page' => $page,
                'url' => 'https://www.google.com/search?tbm=lcl&hl=en&q=' . $encoded . ($start > 0 ? '&start=' . $start : ''),
            ];
        }

        return $urls;
    }

    private function reportProgress(?callable $progressCallback, array $progress): void
    {
        if (! $progressCallback) {
            return;
        }

        $progressCallback($progress);
    }

    private function humanSearchQuery(string $searchQuery): string
    {
        return Str::limit($searchQuery, 120, '...');
    }

    private function extractMapPreloadUrls(string $html): array
    {
        $decoded = $this->decodeGoogleHtml($html);
        $urls = [];

        if (preg_match_all('#/search\?tbm=map[^"\'\s<>]+#i', $decoded, $matches)) {
            foreach ($matches[0] as $path) {
                $path = html_entity_decode($path, ENT_QUOTES | ENT_HTML5);
                $urls[] = str_starts_with($path, 'http') ? $path : 'https://www.google.com' . $path;
            }
        }

        return array_values(array_unique($urls));
    }

    private function fetch(string $url): ?string
    {
        try {
            $response = $this->client->get($url);
            $statusCode = $response->getStatusCode();

            Log::info('Google Maps extractor HTTP response received', [
                'url' => $url,
                'status_code' => $statusCode,
            ]);

            if ($statusCode >= 400) {
                return null;
            }

            return (string) $response->getBody();
        } catch (\Throwable $exception) {
            Log::warning('Google Maps extractor HTTP request failed', [
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function extractRecords(string $html, string $source, string $sourceUrl): array
    {
        $decoded = $this->decodeGoogleHtml($html);
        $records = array_merge(
            $this->extractLocalResultBlocks($decoded, $source, $sourceUrl),
            $this->extractMapsPlaceLinks($decoded, $source, $sourceUrl),
            $this->extractGoogleMapsPayloadRecords($decoded, $source, $sourceUrl)
        );

        return array_values(array_filter($records, fn (array $record): bool => ! empty($record['name'])));
    }

    private function decodeGoogleHtml(string $html): string
    {
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);
        $decoded = str_replace(['\\/', '\\"', '\\u0026', '\\u003d', '\\u003c', '\\u003e'], ['/', '"', '&', '=', '<', '>'], $decoded);

        return preg_replace('/\s+/', ' ', $decoded) ?: $decoded;
    }

    private function extractLocalResultBlocks(string $html, string $source, string $sourceUrl): array
    {
        $records = [];

        preg_match_all('/<div[^>]+class="[^"]*(?:rllt__details|VkpGBb|dbg0pd)[^"]*"[^>]*>.*?(?=<div[^>]+class="[^"]*(?:rllt__details|VkpGBb|dbg0pd)|<\/body>)/is', $html, $blocks);

        foreach (($blocks[0] ?? []) as $block) {
            $name = $this->firstMatch([
                '/<span[^>]*class="[^"]*OSrXXb[^"]*"[^>]*>(.*?)<\/span>/is',
                '/<div[^>]*role="heading"[^>]*>(.*?)<\/div>/is',
                '/<span[^>]*>([^<]{3,120})<\/span>/is',
            ], $block);

            $text = $this->cleanText($block);
            $website = $this->extractGoogleRedirectUrl($block);

            $records[] = [
                'source' => $source,
                'name' => $this->cleanText($name ?: ''),
                'phone' => $this->extractPhone($text),
                'address' => $this->extractAddress($text),
                'website' => $website,
                'maps_url' => $this->extractMapsUrl($block),
                'category' => $this->extractCategory($text),
                'rating' => $this->extractRating($text),
                'reviews_count' => $this->extractReviewsCount($text),
                'raw_payload' => [
                    'source_url' => $sourceUrl,
                    'snippet' => Str::limit($text, 700, ''),
                ],
            ];
        }

        return $records;
    }

    private function extractMapsPlaceLinks(string $html, string $source, string $sourceUrl): array
    {
        $records = [];

        preg_match_all('#(?:https://www\.google\.com)?/maps/place/([^"\'<>\\\\]+)#i', $html, $matches);

        foreach (array_unique($matches[0] ?? []) as $match) {
            $mapsUrl = str_starts_with($match, 'http') ? $match : 'https://www.google.com' . $match;
            $name = $this->nameFromMapsUrl($mapsUrl);
            $context = $this->nearbyText($html, $match);

            if (! $name || $this->looksLikeGoogleChrome($name)) {
                continue;
            }

            $records[] = [
                'source' => $source,
                'name' => $name,
                'phone' => $this->extractPhone($context),
                'address' => $this->extractAddress($context),
                'website' => $this->extractGoogleRedirectUrl($context),
                'maps_url' => $mapsUrl,
                'category' => $this->extractCategory($context),
                'rating' => $this->extractRating($context),
                'reviews_count' => $this->extractReviewsCount($context),
                'raw_payload' => [
                    'source_url' => $sourceUrl,
                    'snippet' => Str::limit($context, 700, ''),
                ],
            ];
        }

        return $records;
    }

    private function extractGoogleMapsPayloadRecords(string $payload, string $source, string $sourceUrl): array
    {
        if (! str_contains($payload, ")]}'") && ! str_contains($payload, 'Music') && ! str_contains($payload, 'School')) {
            return [];
        }

        $json = trim($payload);

        if (str_starts_with($json, ")]}'")) {
            $json = trim(substr($json, 4));
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return [];
        }

        $strings = [];
        $this->collectPayloadStrings($decoded, $strings);

        $records = [];

        foreach ($strings as $index => $value) {
            if (! $this->looksLikeBusinessName($value)) {
                continue;
            }

            $nearbyStrings = array_slice($strings, max(0, $index - 8), 35);
            $nearbyText = implode(' ', $nearbyStrings);
            $website = $this->firstWebsiteFromStrings($nearbyStrings);

            $records[] = [
                'source' => $source,
                'name' => $value,
                'phone' => $this->extractPhone($nearbyText),
                'address' => $this->extractAddressFromStrings($nearbyStrings),
                'website' => $website,
                'maps_url' => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($value),
                'category' => $this->extractCategory($nearbyText),
                'rating' => $this->extractRating($nearbyText),
                'reviews_count' => $this->extractReviewsCount($nearbyText),
                'raw_payload' => [
                    'source_url' => $sourceUrl,
                    'snippet' => Str::limit($nearbyText, 700, ''),
                ],
            ];
        }

        return $this->dedupeRecords($records);
    }

    private function collectPayloadStrings(mixed $value, array &$strings): void
    {
        if (is_string($value)) {
            $cleaned = trim($value);

            if ($cleaned !== '') {
                $strings[] = $cleaned;
            }

            return;
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $child) {
            $this->collectPayloadStrings($child, $strings);
        }
    }

    private function enrichRecord(array $record, bool $quick = false): array
    {
        if ($quick) {
            $record['email'] = null;
            $record['social_links'] = [];

            Log::info('Google Maps extractor quick enrichment skipped website crawl', [
                'name' => $record['name'] ?? null,
                'has_website' => ! empty($record['website']),
            ]);

            return $record;
        }

        $contactData = $this->contactExtractor->extract($record['website'] ?? null, $quick);
        $emails = $contactData['emails'] ?? [];
        $phones = $contactData['phones'] ?? [];

        if (empty($record['phone']) && ! empty($phones)) {
            $record['phone'] = $phones[0];
        }

        $record['email'] = $emails[0] ?? null;
        $record['social_links'] = $contactData['social_links'] ?? [];

        Log::info('Google Maps extractor record enriched', [
            'name' => $record['name'] ?? null,
            'has_website' => ! empty($record['website']),
            'quick' => $quick,
            'email_count' => count($emails),
            'phone_count' => count($phones),
            'social_count' => count($record['social_links']),
        ]);

        return $record;
    }

    private function dedupeRecords(array $records): array
    {
        $unique = [];
        $seenNames = [];

        foreach ($records as $record) {
            $nameKey = Str::lower(trim((string) ($record['name'] ?? '')));

            if ($nameKey !== '' && isset($seenNames[$nameKey])) {
                continue;
            }

            $key = ! empty($record['website'])
                ? Str::lower(trim(($record['website'] ?? '') . '|' . ($record['address'] ?? '')))
                : Str::lower(trim(($record['name'] ?? '') . '|' . ($record['address'] ?? '') . '|' . ($record['maps_url'] ?? '')));

            if ($key === '||' || isset($unique[$key])) {
                continue;
            }

            $unique[$key] = $record;
            $seenNames[$nameKey] = true;
        }

        return array_values($unique);
    }

    private function firstMatch(array $patterns, string $subject): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $subject, $match)) {
                return $match[1] ?? null;
            }
        }

        return null;
    }

    private function extractGoogleRedirectUrl(string $html): ?string
    {
        preg_match_all('/href="([^"]+)"/i', $html, $matches);

        foreach (($matches[1] ?? []) as $href) {
            $href = html_entity_decode($href, ENT_QUOTES);

            if (str_starts_with($href, '/url?q=')) {
                parse_str(parse_url($href, PHP_URL_QUERY) ?: '', $query);
                $href = $query['q'] ?? $href;
            }

            if (! preg_match('#^https?://#i', $href)) {
                continue;
            }

            if (preg_match('/google\.|gstatic\.|schema\.org|w3\.org/i', $href)) {
                continue;
            }

            return $href;
        }

        return null;
    }

    private function firstWebsiteFromStrings(array $strings): ?string
    {
        foreach ($strings as $value) {
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5);

            if (str_starts_with($value, '/url?q=')) {
                parse_str(parse_url($value, PHP_URL_QUERY) ?: '', $query);
                $value = $query['q'] ?? $value;
            }

            if (! preg_match('#^https?://#i', $value) && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}(?:\/.*)?$/i', $value)) {
                $value = 'https://' . $value;
            }

            if (! preg_match('#^https?://#i', $value)) {
                continue;
            }

            if (preg_match('/google\.|googleusercontent\.|gstatic\.|schema\.org|w3\.org/i', $value)) {
                continue;
            }

            return $value;
        }

        return null;
    }

    private function extractMapsUrl(string $html): ?string
    {
        if (preg_match('#(?:https://www\.google\.com)?/maps/place/([^"\'<>\\\\]+)#i', $html, $match)) {
            return str_starts_with($match[0], 'http') ? $match[0] : 'https://www.google.com' . $match[0];
        }

        return null;
    }

    private function nameFromMapsUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';

        if (! preg_match('#/maps/place/([^/]+)#', $path, $match)) {
            return null;
        }

        return trim(str_replace('+', ' ', rawurldecode($match[1])));
    }

    private function nearbyText(string $html, string $needle): string
    {
        $position = strpos($html, $needle);

        if ($position === false) {
            return '';
        }

        $chunk = substr($html, max(0, $position - 1200), 2600);

        return $this->cleanText($chunk);
    }

    private function cleanText(string $value): string
    {
        $value = strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5));
        $value = preg_replace('/\s+/', ' ', $value) ?: $value;

        return trim($value);
    }

    private function extractPhone(string $text): ?string
    {
        if (! preg_match('/(?:\+?\d[\d\s().-]{7,}\d)/', $text, $match)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $match[0]);

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        $phone = trim($match[0]);

        if (preg_match('/^\d+\.\d+$/', $phone) || (str_contains($phone, ')') && ! str_contains($phone, '('))) {
            return null;
        }

        if (! preg_match('/^\+|\(\d{2,4}\)|\d{2,4}[-.]\d{2,4}|\d{3}\s\d{3}\s\d{4}/', $phone)) {
            return null;
        }

        return $phone;
    }

    private function extractAddress(string $text): ?string
    {
        $parts = preg_split('/(?:Phone|Call|Website|Directions|Hours|Rating|Reviews)/i', $text);
        $candidate = trim($parts[0] ?? '');
        $candidate = preg_replace('/^(.*?)(?:Address|Located in)[:\s]*/i', '', $candidate) ?: $candidate;

        return strlen($candidate) > 20 ? Str::limit($candidate, 255, '') : null;
    }

    private function extractAddressFromStrings(array $strings): ?string
    {
        $candidates = [];

        foreach ($strings as $value) {
            $value = $this->cleanText($value);

            if (strlen($value) < 12 || preg_match('#https?://#i', $value)) {
                continue;
            }

            if (preg_match('/\b(?:CA|California)\b|\b\d{5}(?:-\d{4})?\b/', $value)) {
                $candidates[] = $value;
            }
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return Str::limit($candidates[0], 255, '');
    }

    private function extractCategory(string $text): ?string
    {
        if (preg_match('/(?:Business|School|Shop|Store|Restaurant|Clinic|Agency|Service|Company|College|Institute)/i', $text, $match)) {
            return $match[0];
        }

        return null;
    }

    private function extractRating(string $text): ?float
    {
        if (preg_match('/\b([1-5]\.\d)\b/', $text, $match)) {
            return (float) $match[1];
        }

        return null;
    }

    private function extractReviewsCount(string $text): ?int
    {
        if (preg_match('/\(?([\d,]+)\)?\s+reviews?/i', $text, $match)) {
            return (int) str_replace(',', '', $match[1]);
        }

        return null;
    }

    private function looksLikeGoogleChrome(string $name): bool
    {
        return preg_match('/google|maps|directions|search|photos|street view/i', $name) === 1;
    }

    private function looksLikeBusinessName(string $name): bool
    {
        $name = $this->cleanText($name);
        $lower = Str::lower($name);

        if (strlen($name) < 5 || strlen($name) > 120) {
            return false;
        }

        if (in_array($lower, [
            'music school',
            'music schools',
            'music',
            'school',
            'college',
            'drum school',
            'technical school',
            'guitar instructor',
            'music instructor',
            'piano instructor',
            'vocal instructor',
            'california',
            'music school california',
        ], true)) {
            return false;
        }

        if (str_starts_with($lower, 'school for ') || str_starts_with($lower, 'school developing ')) {
            return false;
        }

        if (preg_match('/google|maps|directions|review|reviews|photo|traffic|other_user|hotel|vr_partner|schema|owner|http|www\.|^0ahU|^gcid:|^\/m\//i', $name)) {
            return false;
        }

        if (str_contains($name, '_') || str_contains($name, ',') || preg_match('/^\d+$|\.[a-z]{2,}/i', $name)) {
            return false;
        }

        if (! preg_match('/music|school|academy|studio|lesson|conservatory|institute|arts|piano|guitar|voice|vocal|drum/i', $name)) {
            return false;
        }

        return preg_match('/[a-z]/i', $name) === 1;
    }
}
