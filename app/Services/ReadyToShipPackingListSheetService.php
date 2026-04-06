<?php

namespace App\Services;

use App\Models\ReadyToShip;
use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_ValueRange;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReadyToShipPackingListSheetService
{
    public static function normalizeSku(?string $sku): string
    {
        if ($sku === null || $sku === '') {
            return '';
        }
        $sku = strtoupper(trim($sku));
        $sku = preg_replace('/\s+/u', ' ', $sku);
        $sku = preg_replace('/[^\S\r\n]+/u', ' ', $sku);

        return trim($sku);
    }

    /**
     * @return array<string, string> normalized SKU => https URL
     */
    public function getSkuToLinkMap(bool $skipCache = false): array
    {
        $csvUrl = trim((string) config('googlesheets.ready_to_ship_packing_list.csv_url', ''));
        if ($csvUrl === '') {
            return [];
        }

        if ($skipCache) {
            return $this->parseCsvFromUrl($csvUrl);
        }

        $ttl = max(30, (int) config('googlesheets.ready_to_ship_packing_list.cache_seconds', 120));

        return Cache::remember('r2s_packing_list_links_v1', $ttl, function () use ($csvUrl) {
            return $this->parseCsvFromUrl($csvUrl);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function parseCsvFromUrl(string $csvUrl): array
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'Invent-ReadyToShip-PackingList/1.0'])
                ->get($csvUrl);
            if (! $response->successful()) {
                Log::warning('Ready To Ship packing list CSV HTTP error', [
                    'status' => $response->status(),
                    'url' => $csvUrl,
                ]);

                return [];
            }
            $body = $response->body();
        } catch (\Throwable $e) {
            Log::warning('Ready To Ship packing list CSV fetch failed', [
                'message' => $e->getMessage(),
                'url' => $csvUrl,
            ]);

            return [];
        }

        return self::parseCsvBody($body);
    }

    /**
     * DB links override CSV for the same SKU (app is authoritative when a link is stored).
     *
     * @param  array<string, string>  $csvMap
     * @return array<string, string>
     */
    public function mergeDbLinksOverCsv(array $csvMap): array
    {
        $merged = $csvMap;
        $rows = ReadyToShip::query()
            ->where('transit_inv_status', 0)
            ->whereNull('deleted_at')
            ->whereNotNull('packing_list_link')
            ->get(['sku', 'packing_list_link']);

        foreach ($rows as $row) {
            $norm = self::normalizeSku($row->sku ?? '');
            $url = trim((string) ($row->packing_list_link ?? ''));
            if ($norm === '' || $url === '') {
                continue;
            }
            if (! self::isAllowedHttpUrl($url)) {
                continue;
            }
            $merged[$norm] = $url;
        }

        return $merged;
    }

    /**
     * Upsert column A = SKU (as entered), column B = URL, on the configured packing-list sheet.
     * Requires google-credentials.json and spreadsheet shared with the service account.
     */
    public function pushLinkToSheet(string $displaySku, string $url): bool
    {
        $spreadsheetId = $this->resolveSpreadsheetId();
        if ($spreadsheetId === null || $spreadsheetId === '') {
            Log::notice('Ready To Ship packing list: no spreadsheet_id (set READY_TO_SHIP_PACKING_LIST_SPREADSHEET_ID or a valid csv_url).');

            return false;
        }

        $service = $this->createSheetsService();
        if ($service === null) {
            return false;
        }

        $tab = $this->sheetTabQuoted();
        $range = $tab . '!A:B';
        $targetNorm = self::normalizeSku($displaySku);
        if ($targetNorm === '') {
            return false;
        }

        try {
            $response = $service->spreadsheets_values->get($spreadsheetId, $range);
            $values = $response->getValues() ?? [];
        } catch (\Throwable $e) {
            Log::warning('Ready To Ship packing list: failed to read sheet', ['message' => $e->getMessage()]);

            return false;
        }

        $rowIndex = null;
        foreach ($values as $i => $row) {
            $cellSku = (string) ($row[0] ?? '');
            if (self::normalizeSku($cellSku) === $targetNorm) {
                $rowIndex = $i + 1;
                break;
            }
        }

        $url = trim($url);
        $params = ['valueInputOption' => 'USER_ENTERED'];

        try {
            if ($rowIndex !== null) {
                $cellRange = $tab . '!B' . $rowIndex;
                $body = new Google_Service_Sheets_ValueRange(['values' => [[$url]]]);
                $service->spreadsheets_values->update($spreadsheetId, $cellRange, $body, $params);
            } elseif ($url !== '') {
                $body = new Google_Service_Sheets_ValueRange(['values' => [[trim($displaySku), $url]]]);
                $service->spreadsheets_values->append($spreadsheetId, $range, $body, array_merge($params, ['insertDataOption' => 'INSERT_ROWS']));
            } else {
                return true;
            }
        } catch (\Throwable $e) {
            Log::warning('Ready To Ship packing list: failed to write sheet', ['message' => $e->getMessage()]);

            return false;
        }

        return true;
    }

    public function resolveSpreadsheetId(): ?string
    {
        $id = trim((string) config('googlesheets.ready_to_ship_packing_list.spreadsheet_id', ''));
        if ($id !== '') {
            return $id;
        }
        $csvUrl = trim((string) config('googlesheets.ready_to_ship_packing_list.csv_url', ''));
        $editUrl = trim((string) config('googlesheets.ready_to_ship_packing_list.sheet_edit_url', ''));
        foreach ([$csvUrl, $editUrl] as $u) {
            if ($u !== '' && preg_match('#/spreadsheets/d/([a-zA-Z0-9-_]+)#', $u, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    protected function sheetTabQuoted(): string
    {
        $name = trim((string) config('googlesheets.ready_to_ship_packing_list.sheet_tab', 'Sheet1'));
        if ($name === '') {
            $name = 'Sheet1';
        }
        if (preg_match('/[^A-Za-z0-9_]/', $name)) {
            return "'" . str_replace("'", "''", $name) . "'";
        }

        return $name;
    }

    protected function createSheetsService(): ?Google_Service_Sheets
    {
        $credentialsPath = config('googlesheets.credentials_path', storage_path('app/google-credentials.json'));
        if (! is_readable($credentialsPath)) {
            Log::notice('Ready To Ship packing list: credentials not readable at ' . $credentialsPath);

            return null;
        }
        try {
            $client = new Google_Client();
            $client->setApplicationName('Invent Ready To Ship Packing List');
            $client->setScopes([Google_Service_Sheets::SPREADSHEETS]);
            $client->setAccessType('offline');
            $client->setAuthConfig($credentialsPath);

            return new Google_Service_Sheets($client);
        } catch (\Throwable $e) {
            Log::warning('Ready To Ship packing list: Sheets client init failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    public static function parseCsvBody(string $body): array
    {
        if (str_starts_with($body, "\xEF\xBB\xBF")) {
            $body = substr($body, 3);
        }
        $body = trim($body);
        if ($body === '') {
            return [];
        }

        $lines = preg_split("/\r\n|\n|\r/", $body);
        $map = [];
        $isFirst = true;

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $row = str_getcsv($line);
            if (count($row) < 2) {
                continue;
            }
            $colSku = trim((string) ($row[0] ?? ''));
            $colUrl = trim((string) ($row[1] ?? ''));
            if ($isFirst && strcasecmp($colSku, 'sku') === 0) {
                $isFirst = false;
                continue;
            }
            $isFirst = false;
            if ($colSku === '' || $colUrl === '') {
                continue;
            }
            $norm = self::normalizeSku($colSku);
            if ($norm === '') {
                continue;
            }
            if (! self::isAllowedHttpUrl($colUrl)) {
                continue;
            }
            $map[$norm] = $colUrl;
        }

        return $map;
    }

    public static function isAllowedHttpUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));

        return in_array($scheme, ['http', 'https'], true);
    }
}
