<?php

namespace App\Services\MarketplaceManager;

use App\Services\AmazonSpApiService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * "Amz Data": what Amazon holds per product type (the fields a listing of that type can carry) and per listing
 * (the values of those fields).
 *
 *  - The fields of a product type come from the Product Type Definitions API and are kept in amz1_type_columns,
 *    so every SKU of that type shows them as columns, blank until the value is known.
 *  - The values come from the Listings Items API (attributes), and edits go back with a PATCH on the same fields.
 *
 * Only simple fields are columns: a field whose value is a single text, number, yes/no or a list of those.
 * Fields with a nested value (dimensions, weight with a unit, offers, images) stay on their own Amazon pages.
 */
class Amz1DataService
{
    public const TYPE_TABLE = 'amz1_type_columns';

    public const REMOVED_TABLE = 'amz1_removed_columns';

    /** Browse category (shopper breadcrumb) of each SKU, read from the Amazon catalog. */
    public const BROWSE_TABLE = 'amz1_browse_categories';

    /** Tables of the Amz Data page: a copy of each eBay 1 Data table (same columns and indexes), starting empty. */
    private const COPIED_TABLES = [
        'item_specifics', 'extra_columns', 'column_sources', 'column_groups',
        'settings', 'pending_specifics', 'cell_styles', 'missing_history',
    ];

    public const TYPE_PATTERN = '/^[A-Z][A-Z0-9_]{1,119}$/';

    private const ENDPOINT = 'https://sellingpartnerapi-na.amazon.com';

    /** Fields that are not item specifics: offer, images, shipping, identifiers handled elsewhere, long texts. */
    private const SKIP_GROUPS = ['offer', 'images', 'fulfillment_availability', 'variations'];

    private const SKIP_NAMES = '/(image|offer|price|quantity|fulfillment|shipping|externally_assigned|supplier_declared|gpsr|parentage|variation_theme|child_parent|recommended_browse|merchant_|^item_name$|bullet_point|product_description|^condition|list_price|^map_|launch_date|purchasable|max_order|^program|sale_|b2b|business_|product_tax|dsa_|manufacturer_contact|compliance_media|safety_data|certification|^fnsku|^asin)/i';

    private const MAX_COLUMNS = 150;

    public string $lastError = '';

    private static bool $tablesChecked = false;

    public function __construct(private AmazonSpApiService $api)
    {
    }

    /**
     * The Amz Data tables are created the first time the page is used: a deploy is git pull + optimize:clear, not migrate.
     */
    public static function ensureTables(): void
    {
        if (self::$tablesChecked) {
            return;
        }
        self::$tablesChecked = true;
        try {
            foreach (self::COPIED_TABLES as $name) {
                $from = 'ebay1_'.$name;
                $to = 'amz1_'.$name;
                if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
                    DB::statement('CREATE TABLE `'.$to.'` LIKE `'.$from.'`');
                }
            }
            if (! Schema::hasTable(self::TYPE_TABLE)) {
                Schema::create(self::TYPE_TABLE, function (Blueprint $table) {
                    $table->id();
                    $table->string('product_type', 120)->unique();
                    $table->longText('columns')->nullable();
                    $table->string('error', 500)->nullable();
                    $table->timestamp('fetched_at')->nullable();
                    $table->timestamps();
                });
            }
            // Columns the user deleted from a product type tab: they stay off the tab even though Amazon defines them.
            if (! Schema::hasTable(self::REMOVED_TABLE)) {
                Schema::create(self::REMOVED_TABLE, function (Blueprint $table) {
                    $table->id();
                    $table->char('category_hash', 40);
                    $table->string('category', 500)->nullable();
                    $table->string('name', 191);
                    $table->string('name_key', 191);
                    $table->unsignedBigInteger('removed_by')->nullable();
                    $table->timestamps();
                    $table->unique(['category_hash', 'name_key']);
                });
            }
            if (! Schema::hasTable(self::BROWSE_TABLE)) {
                Schema::create(self::BROWSE_TABLE, function (Blueprint $table) {
                    $table->id();
                    $table->string('sku', 191)->unique();
                    $table->string('asin', 20)->nullable()->index();
                    $table->string('node_id', 40)->nullable();
                    $table->string('path', 500)->nullable();
                    $table->timestamp('fetched_at')->nullable();
                    $table->timestamps();
                });
            }
        } catch (\Throwable $e) {
            Log::warning('AmzData: could not create its tables', ['error' => $e->getMessage()]);
        }
    }

    // ---- Fields of a product type ----

    /**
     * The stored fields of a product type. state "none": never loaded.
     *
     * @return array{state: string, columns: list<array<string, mixed>>, error: string}
     */
    public static function cachedColumns(string $productType): array
    {
        $type = strtoupper(trim($productType));
        if ($type === '' || ! Schema::hasTable(self::TYPE_TABLE)) {
            return ['state' => 'none', 'columns' => [], 'error' => ''];
        }
        $row = DB::table(self::TYPE_TABLE)->where('product_type', $type)->first();
        if (! $row) {
            return ['state' => 'none', 'columns' => [], 'error' => ''];
        }
        $columns = json_decode((string) ($row->columns ?? ''), true);

        return ['state' => 'ready', 'columns' => is_array($columns) ? $columns : [], 'error' => (string) ($row->error ?? '')];
    }

    /**
     * The fields of a product type: the stored list, else loaded from Amazon now.
     *
     * @return list<array<string, mixed>>
     */
    public function columnsFor(string $productType): array
    {
        $cached = self::cachedColumns($productType);
        if ($cached['state'] === 'ready' && $cached['columns'] !== []) {
            return $cached['columns'];
        }
        $loaded = $this->loadDefinition($productType);

        return $loaded['columns'];
    }

    /**
     * Reads the definition of a product type from Amazon and stores its fields.
     *
     * @return array{ok: bool, columns: list<array<string, mixed>>, message: string}
     */
    public function loadDefinition(string $productType): array
    {
        $type = strtoupper(trim($productType));
        $this->lastError = '';
        if (preg_match(self::TYPE_PATTERN, $type) !== 1) {
            return $this->failed('"'.$productType.'" is not an Amazon product type.');
        }
        if (! $this->api->isConfigured()) {
            return $this->failed('Amazon SP-API is not connected.');
        }

        $query = [
            'marketplaceIds' => $this->marketplaceId(),
            'requirements' => 'LISTING',
            'requirementsEnforced' => 'ENFORCED',
            'locale' => 'en_US',
        ];
        $response = $this->call('GET', '/definitions/2020-09-01/productTypes/'.rawurlencode($type), $query, null, 40);
        if ($response === null) {
            return $this->failed($this->lastError !== '' ? $this->lastError : 'Amazon did not answer.');
        }
        $json = $response->json();
        $json = is_array($json) ? $json : [];
        if (! $response->successful()) {
            $message = trim((string) ($json['errors'][0]['message'] ?? ''));
            $message = $message !== '' ? $message : 'Amazon refused the product type definition (HTTP '.$response->status().').';
            // A product type Amazon does not know is remembered, so the page does not ask again on every load.
            if (in_array($response->status(), [400, 404], true)) {
                $this->store($type, [], $message);
            }

            return $this->failed($message);
        }

        $link = trim((string) ($json['schema']['link']['resource'] ?? ''));
        if ($link === '') {
            return $this->failed('Amazon returned no field list for '.$type.'.');
        }
        try {
            // The schema is a pre-signed file: it is read without the access token.
            $schemaResponse = Http::withoutVerifying()->timeout(60)->acceptJson()->get($link);
        } catch (\Throwable $e) {
            return $this->failed('The field list of '.$type.' could not be read: '.$e->getMessage());
        }
        $schema = $schemaResponse->successful() ? $schemaResponse->json() : null;
        if (! is_array($schema)) {
            return $this->failed('The field list of '.$type.' could not be read (HTTP '.$schemaResponse->status().').');
        }

        $columns = self::columnsFromSchema($schema, (array) ($json['propertyGroups'] ?? []));
        $this->store($type, $columns, '');

        return ['ok' => true, 'columns' => $columns, 'message' => ''];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $groups
     * @return list<array<string, mixed>>
     */
    public static function columnsFromSchema(array $schema, array $groups): array
    {
        $props = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $required = array_values(array_filter((array) ($schema['required'] ?? []), 'is_string'));

        $wanted = [];
        foreach ($groups as $groupName => $group) {
            if (! is_array($group) || in_array((string) $groupName, self::SKIP_GROUPS, true)) {
                continue;
            }
            foreach ((array) ($group['propertyNames'] ?? []) as $name) {
                if (is_string($name)) {
                    $wanted[$name] = true;
                }
            }
        }
        foreach ($required as $name) {
            $wanted[$name] = true;
        }
        if ($wanted === []) {
            foreach (array_keys($props) as $name) {
                $wanted[(string) $name] = true;
            }
        }

        $columns = [];
        $titles = [];
        foreach (array_keys($wanted) as $name) {
            $name = (string) $name;
            if (preg_match(self::SKIP_NAMES, $name) === 1) {
                continue;
            }
            $def = $props[$name] ?? null;
            if (! is_array($def) || ! is_array($def['items'] ?? null)) {
                continue;
            }
            $itemProps = is_array($def['items']['properties'] ?? null) ? $def['items']['properties'] : [];
            if (! isset($itemProps['value']) || ! is_array($itemProps['value'])) {
                continue;
            }
            // Only a plain value: nothing but the value, its marketplace and its language.
            if (array_diff(array_keys($itemProps), ['value', 'marketplace_id', 'language_tag']) !== []) {
                continue;
            }
            $valueType = (string) ($itemProps['value']['type'] ?? 'string');
            if (! in_array($valueType, ['string', 'integer', 'number', 'boolean'], true)) {
                continue;
            }

            $enum = [];
            if (is_array($itemProps['value']['enum'] ?? null)) {
                foreach ($itemProps['value']['enum'] as $option) {
                    if (is_scalar($option)) {
                        $enum[] = (string) $option;
                    }
                }
            }

            $title = trim((string) ($def['title'] ?? ''));
            if ($title === '') {
                $title = ucwords(str_replace('_', ' ', $name));
            }
            $titleKey = strtolower($title);
            if (isset($titles[$titleKey])) {
                $title .= ' ('.$name.')';
            }
            $titles[strtolower($title)] = true;

            $columns[] = [
                'key' => $name,
                'title' => $title,
                'type' => $valueType,
                'enum' => array_slice($enum, 0, 400),
                'multi' => (int) ($def['maxItems'] ?? 1) > 1,
                'lang' => isset($itemProps['language_tag']),
                'required' => in_array($name, $required, true),
            ];
        }

        // Required fields first, then the schema's own order.
        usort($columns, static fn ($a, $b) => ((int) $b['required']) <=> ((int) $a['required']));

        return array_slice($columns, 0, self::MAX_COLUMNS);
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     */
    private function store(string $type, array $columns, string $error): void
    {
        $payload = [
            'columns' => json_encode($columns, JSON_UNESCAPED_UNICODE),
            'error' => $error !== '' ? mb_substr($error, 0, 500) : null,
            'fetched_at' => now(),
            'updated_at' => now(),
        ];
        if (DB::table(self::TYPE_TABLE)->where('product_type', $type)->exists()) {
            DB::table(self::TYPE_TABLE)->where('product_type', $type)->update($payload);
        } else {
            DB::table(self::TYPE_TABLE)->insert($payload + ['product_type' => $type, 'created_at' => now()]);
        }
    }

    /**
     * @return array{ok: bool, columns: list<array<string, mixed>>, message: string}
     */
    private function failed(string $message): array
    {
        $this->lastError = $message;

        return ['ok' => false, 'columns' => [], 'message' => $message];
    }

    // ---- Values of a listing ----

    /**
     * The live listing of a seller SKU: its product type, ASIN and the values of its fields.
     *
     * @return array{product_type: string, asin: string, title: string, attributes: array<string, mixed>}|null
     */
    public function fetchListing(string $sellerSku): ?array
    {
        $this->lastError = '';
        if (! $this->api->isConfigured()) {
            $this->lastError = 'Amazon SP-API is not connected.';

            return null;
        }
        $path = '/listings/2021-08-01/items/'.rawurlencode($this->sellerId()).'/'.rawurlencode($sellerSku);
        $response = $this->call('GET', $path, [
            'marketplaceIds' => $this->marketplaceId(),
            'includedData' => 'summaries,attributes',
            'issueLocale' => 'en_US',
        ], null, 40);
        if ($response === null) {
            return null;
        }
        $json = $response->json();
        $json = is_array($json) ? $json : [];
        if ($response->status() === 404) {
            $this->lastError = 'Amazon has no listing for the seller SKU "'.$sellerSku.'".';

            return null;
        }
        if (! $response->successful()) {
            $message = trim((string) ($json['errors'][0]['message'] ?? ''));
            $this->lastError = $message !== '' ? $message : 'Amazon refused the listing request (HTTP '.$response->status().').';

            return null;
        }

        $summary = [];
        foreach ((array) ($json['summaries'] ?? []) as $row) {
            if (is_array($row) && (($row['marketplaceId'] ?? $this->marketplaceId()) === $this->marketplaceId() || $summary === [])) {
                $summary = $row;
            }
        }

        return [
            'product_type' => strtoupper(trim((string) ($summary['productType'] ?? ''))),
            'asin' => strtoupper(trim((string) ($summary['asin'] ?? ''))),
            'title' => trim((string) ($summary['itemName'] ?? '')),
            'attributes' => is_array($json['attributes'] ?? null) ? $json['attributes'] : [],
        ];
    }

    /**
     * The text of one field in the attributes of a listing (several values are joined with "; ").
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $column
     */
    public function attributeText(array $attributes, array $column): string
    {
        $entries = $attributes[(string) ($column['key'] ?? '')] ?? null;
        if (! is_array($entries)) {
            return '';
        }
        $values = [];
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            if (isset($entry['marketplace_id']) && (string) $entry['marketplace_id'] !== $this->marketplaceId()) {
                continue;
            }
            $value = $entry['value'] ?? null;
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            if (is_scalar($value) && trim((string) $value) !== '') {
                $values[] = trim((string) $value);
            }
        }

        return implode('; ', $values);
    }

    /**
     * Sends the edited fields of a listing. One request first; when Amazon refuses it, each field is tried alone,
     * so a single wrong value does not stop the others.
     *
     * @param  array<string, array{column: array<string, mixed>, value: string}>  $changes  field key => column and new value
     * @return array{success: bool, message: string, applied: list<string>, rejected: array<string, string>}
     */
    public function patchAttributes(string $sellerSku, string $productType, array $changes): array
    {
        $applied = [];
        $rejected = [];
        $patches = [];
        foreach ($changes as $key => $change) {
            $column = $change['column'];
            $entries = $this->entriesFor($column, (string) $change['value'], $why);
            if ($entries === null) {
                $rejected[(string) $column['title']] = $why;

                continue;
            }
            $patches[(string) $column['title']] = [
                'op' => 'replace',
                'path' => '/attributes/'.$key,
                'value' => $entries,
            ];
        }
        if ($patches === []) {
            return ['success' => false, 'message' => $this->rejectedText($rejected), 'applied' => [], 'rejected' => $rejected];
        }

        $first = $this->sendPatches($sellerSku, $productType, array_values($patches));
        if ($first['ok']) {
            $applied = array_keys($patches);
        } elseif (count($patches) === 1) {
            $rejected[(string) array_key_first($patches)] = $first['message'];
        } else {
            foreach ($patches as $title => $patch) {
                $one = $this->sendPatches($sellerSku, $productType, [$patch]);
                if ($one['ok']) {
                    $applied[] = (string) $title;
                } else {
                    $rejected[(string) $title] = $one['message'];
                }
            }
        }

        $message = $applied !== [] ? 'Amazon accepted '.count($applied).' field'.(count($applied) === 1 ? '' : 's').'.' : '';
        if ($rejected !== []) {
            $message = trim($message.' '.$this->rejectedText($rejected));
        }

        return ['success' => $applied !== [], 'message' => $message, 'applied' => $applied, 'rejected' => $rejected];
    }

    /**
     * @param  array<string, string>  $rejected
     */
    private function rejectedText(array $rejected): string
    {
        $parts = [];
        foreach ($rejected as $title => $why) {
            $parts[] = $title.': '.$why;
        }

        return implode(' | ', $parts);
    }

    /**
     * @param  array<string, mixed>  $column
     * @return list<array<string, mixed>>|null  null when the value is not valid for the field ($why says why)
     */
    private function entriesFor(array $column, string $raw, ?string &$why = null): ?array
    {
        $why = '';
        $raw = trim($raw);
        $parts = ! empty($column['multi'])
            ? array_values(array_filter(array_map('trim', explode(';', $raw)), static fn ($p) => $p !== ''))
            : [$raw];
        if ($parts === [] || $raw === '') {
            $why = 'The value is blank.';

            return null;
        }

        $entries = [];
        foreach ($parts as $part) {
            $value = $this->typedValue($column, $part, $why);
            if ($value === null) {
                return null;
            }
            $entry = ['value' => $value, 'marketplace_id' => $this->marketplaceId()];
            if (! empty($column['lang'])) {
                $entry['language_tag'] = 'en_US';
            }
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $column
     */
    private function typedValue(array $column, string $text, string &$why): string|int|float|bool|null
    {
        $enum = array_values(array_filter((array) ($column['enum'] ?? []), 'is_string'));
        if ($enum !== []) {
            foreach ($enum as $option) {
                if (strcasecmp($option, $text) === 0) {
                    return $this->castValue((string) ($column['type'] ?? 'string'), $option, $why);
                }
            }
            $why = '"'.$text.'" is not one of the values Amazon accepts here ('.implode(', ', array_slice($enum, 0, 8)).(count($enum) > 8 ? ', …' : '').').';

            return null;
        }

        return $this->castValue((string) ($column['type'] ?? 'string'), $text, $why);
    }

    private function castValue(string $type, string $text, string &$why): string|int|float|bool|null
    {
        switch ($type) {
            case 'integer':
                if (preg_match('/^-?\d+$/', $text) !== 1) {
                    $why = '"'.$text.'" is not a whole number.';

                    return null;
                }

                return (int) $text;
            case 'number':
                if (! is_numeric($text)) {
                    $why = '"'.$text.'" is not a number.';

                    return null;
                }

                return (float) $text;
            case 'boolean':
                $lower = strtolower($text);
                if (in_array($lower, ['true', 'yes', 'y', '1'], true)) {
                    return true;
                }
                if (in_array($lower, ['false', 'no', 'n', '0'], true)) {
                    return false;
                }
                $why = '"'.$text.'" is not yes or no.';

                return null;
            default:
                return $text;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $patches
     * @return array{ok: bool, message: string}
     */
    private function sendPatches(string $sellerSku, string $productType, array $patches): array
    {
        $path = '/listings/2021-08-01/items/'.rawurlencode($this->sellerId()).'/'.rawurlencode($sellerSku);
        $response = $this->call('PATCH', $path, [
            'marketplaceIds' => $this->marketplaceId(),
            'issueLocale' => 'en_US',
        ], ['productType' => $productType, 'patches' => $patches], 45);
        if ($response === null) {
            return ['ok' => false, 'message' => $this->lastError !== '' ? $this->lastError : 'Amazon did not answer.'];
        }
        $json = $response->json();
        $json = is_array($json) ? $json : [];

        $issues = [];
        foreach ((array) ($json['issues'] ?? []) as $issue) {
            if (! is_array($issue)) {
                continue;
            }
            $severity = strtoupper((string) ($issue['severity'] ?? ''));
            $message = trim((string) ($issue['message'] ?? $issue['code'] ?? ''));
            if ($message !== '' && ($severity === 'ERROR' || $severity === '')) {
                $issues[] = $message;
            }
        }
        $status = strtoupper((string) ($json['status'] ?? ''));
        if ($response->successful() && $status !== 'INVALID' && $issues === []) {
            return ['ok' => true, 'message' => ''];
        }
        if ($issues === []) {
            $apiMessage = trim((string) ($json['errors'][0]['message'] ?? ''));
            $issues[] = $apiMessage !== '' ? $apiMessage : 'Amazon did not accept the change (HTTP '.$response->status().($status !== '' ? ' '.$status : '').').';
        }

        return ['ok' => false, 'message' => implode(' ', array_slice($issues, 0, 3))];
    }

    // ---- Change the product type of a live listing ----

    /** Attributes that belong to the offer (price, stock, shipping), not to the product: they are not part of a product type change. */
    private const OFFER_ATTRIBUTES = '/(offer|price|quantity|fulfillment|shipping|merchant_|^map_|^condition|b2b|business_|max_order|^fnsku|^asin$)/i';

    /**
     * Moves one live listing to another product type: the product facts Amazon holds for the SKU are sent again under
     * the new type (offer, price and stock are left alone). Amazon checks the submission first (VALIDATION_PREVIEW, which
     * changes nothing); the real change is sent only when $apply is true AND that check found no error.
     *
     * @return array{ok: bool, applied: bool, old_type: string, new_type: string, message: string, issues: list<array{severity: string, message: string}>}
     */
    public function changeProductType(string $sellerSku, string $newType, bool $apply): array
    {
        $this->lastError = '';
        $newType = strtoupper(trim($newType));
        $result = ['ok' => false, 'applied' => false, 'old_type' => '', 'new_type' => $newType, 'message' => '', 'issues' => []];

        if (preg_match(self::TYPE_PATTERN, $newType) !== 1) {
            $result['message'] = '"'.$newType.'" is not an Amazon product type.';

            return $result;
        }
        if (! $this->api->isConfigured()) {
            $result['message'] = 'Amazon SP-API is not connected.';

            return $result;
        }
        $listing = $this->fetchListing($sellerSku);
        if ($listing === null) {
            $result['message'] = $this->lastError !== '' ? $this->lastError : 'Amazon did not return the listing.';

            return $result;
        }
        $result['old_type'] = $listing['product_type'];
        if ($listing['product_type'] === $newType) {
            $result['message'] = 'The listing already has the product type '.$newType.'.';

            return $result;
        }

        $attributes = [];
        foreach ($listing['attributes'] as $key => $entries) {
            if (is_string($key) && preg_match(self::OFFER_ATTRIBUTES, $key) !== 1) {
                $attributes[$key] = $entries;
            }
        }
        $body = ['productType' => $newType, 'requirements' => 'LISTING_PRODUCT_ONLY', 'attributes' => (object) $attributes];

        // 1. Amazon checks it; nothing is changed.
        $check = $this->putListing($sellerSku, $body, true);
        $result['issues'] = $check['issues'];
        if (! $check['ok']) {
            $result['message'] = 'Amazon would not accept '.$newType.' for this listing. Nothing was changed.';

            return $result;
        }
        if (! $apply) {
            $result['ok'] = true;
            $result['message'] = 'Amazon accepts '.$newType.' for this listing. Nothing was changed yet.';

            return $result;
        }

        // 2. The real change.
        $real = $this->putListing($sellerSku, $body, false);
        $result['issues'] = $real['issues'];
        if (! $real['ok']) {
            $result['message'] = 'Amazon refused the change: '.($real['message'] !== '' ? $real['message'] : 'no reason given').'.';

            return $result;
        }
        $result['ok'] = true;
        $result['applied'] = true;
        $result['message'] = 'Amazon accepted the change from '.$listing['product_type'].' to '.$newType.'. It can take a few minutes to show on Amazon.';

        return $result;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{ok: bool, message: string, issues: list<array{severity: string, message: string}>}
     */
    private function putListing(string $sellerSku, array $body, bool $previewOnly): array
    {
        $path = '/listings/2021-08-01/items/'.rawurlencode($this->sellerId()).'/'.rawurlencode($sellerSku);
        $query = ['marketplaceIds' => $this->marketplaceId(), 'issueLocale' => 'en_US'];
        if ($previewOnly) {
            $query['mode'] = 'VALIDATION_PREVIEW';
        }
        $response = $this->call('PUT', $path, $query, $body, 60);
        if ($response === null) {
            return ['ok' => false, 'message' => $this->lastError !== '' ? $this->lastError : 'Amazon did not answer.', 'issues' => []];
        }
        $json = $response->json();
        $json = is_array($json) ? $json : [];

        $issues = [];
        $hasError = false;
        foreach ((array) ($json['issues'] ?? []) as $issue) {
            if (! is_array($issue)) {
                continue;
            }
            $severity = strtoupper((string) ($issue['severity'] ?? ''));
            $message = trim((string) ($issue['message'] ?? $issue['code'] ?? ''));
            if ($message === '') {
                continue;
            }
            $names = array_filter((array) ($issue['attributeNames'] ?? []), 'is_string');
            $issues[] = ['severity' => $severity !== '' ? $severity : 'ERROR', 'message' => $message.($names !== [] ? ' ['.implode(', ', $names).']' : '')];
            if ($severity === 'ERROR' || $severity === '') {
                $hasError = true;
            }
        }
        $status = strtoupper((string) ($json['status'] ?? ''));
        $okStatus = in_array($status, ['VALID', 'ACCEPTED'], true);
        if ($response->successful() && $okStatus && ! $hasError) {
            return ['ok' => true, 'message' => '', 'issues' => $issues];
        }
        $message = $issues === [] ? trim((string) ($json['errors'][0]['message'] ?? '')) : '';
        if ($message === '' && $issues === []) {
            $message = 'HTTP '.$response->status().($status !== '' ? ' '.$status : '');
        }

        return ['ok' => false, 'message' => $message, 'issues' => $issues];
    }

    // ---- Browse category (the breadcrumb a shopper sees, e.g. Musical Instruments > Microphones & Accessories > Microphones) ----
    // It is not the product type: Amazon keeps both, and they can differ (a bundle listed as AUDIO_MIXING_CONSOLE can sit
    // in the Microphones browse category). Tabs stay by product type; this is shown as its own column.

    /**
     * Browse category of up to many ASINs, read with the Catalog Items search (20 ASINs per call).
     *
     * @param  list<string>  $asins
     * @return array<string, array{id: string, path: string}> keyed by ASIN
     */
    public function browseCategories(array $asins): array
    {
        $asins = array_values(array_unique(array_filter(array_map(
            static fn ($a) => strtoupper(trim((string) $a)),
            $asins
        ), static fn ($a) => preg_match('/^[A-Z0-9]{10}$/', $a) === 1)));
        $out = [];
        foreach (array_chunk($asins, 20) as $i => $chunk) {
            if ($i > 0) {
                usleep(600000); // searchCatalogItems allows about 2 requests a second
            }
            $response = $this->call('GET', '/catalog/2022-04-01/items', [
                'marketplaceIds' => $this->marketplaceId(),
                'identifiersType' => 'ASIN',
                'identifiers' => implode(',', $chunk),
                'includedData' => 'classifications',
                'pageSize' => 20,
            ], null, 40);
            if ($response === null) {
                continue;
            }
            if ($response->status() === 429) {
                sleep(2);
                $response = $this->call('GET', '/catalog/2022-04-01/items', [
                    'marketplaceIds' => $this->marketplaceId(),
                    'identifiersType' => 'ASIN',
                    'identifiers' => implode(',', $chunk),
                    'includedData' => 'classifications',
                    'pageSize' => 20,
                ], null, 40);
                if ($response === null) {
                    continue;
                }
            }
            if (! $response->successful()) {
                $this->lastError = 'Amazon refused the catalog request (HTTP '.$response->status().').';

                continue;
            }
            foreach ((array) ($response->json('items') ?? []) as $item) {
                if (! is_array($item) || empty($item['asin'])) {
                    continue;
                }
                $found = self::browseFromClassifications((array) ($item['classifications'] ?? []));
                if ($found !== null) {
                    $out[strtoupper((string) $item['asin'])] = $found;
                }
            }
        }

        return $out;
    }

    /**
     * "Musical Instruments › Microphones & Accessories › Microphones" from the classification chain of a catalog item.
     * The generic "Products" root Amazon puts in the chain is not shown on the product page, so it is left out.
     *
     * @param  array<int, mixed>  $classifications
     * @return array{id: string, path: string}|null
     */
    public static function browseFromClassifications(array $classifications): ?array
    {
        foreach ($classifications as $block) {
            foreach ((array) ($block['classifications'] ?? []) as $leaf) {
                if (! is_array($leaf)) {
                    continue;
                }
                $names = [];
                $node = $leaf;
                $guard = 0;
                while (is_array($node) && $guard < 12) {
                    $name = trim((string) ($node['displayName'] ?? ''));
                    if ($name !== '' && strcasecmp($name, 'Products') !== 0) {
                        array_unshift($names, $name);
                    }
                    $node = $node['parent'] ?? null;
                    $guard++;
                }
                if ($names !== []) {
                    return ['id' => trim((string) ($leaf['classificationId'] ?? '')), 'path' => implode(' › ', $names)];
                }
            }
        }

        return null;
    }

    /**
     * Keeps the browse category of SKUs (sku key => ASIN) for the Amz Data page.
     *
     * @param  array<string, string>  $skuAsin  sku key => ASIN
     * @param  array<string, array{id: string, path: string}>  $found  ASIN => browse category
     */
    public static function storeBrowseCategories(array $skuAsin, array $found): int
    {
        if (! Schema::hasTable(self::BROWSE_TABLE)) {
            return 0;
        }
        $now = now();
        $rows = [];
        foreach ($skuAsin as $skuKey => $asin) {
            $hit = $found[strtoupper($asin)] ?? null;
            if ($hit === null || $hit['path'] === '') {
                continue;
            }
            $rows[] = [
                'sku' => (string) $skuKey,
                'asin' => strtoupper($asin),
                'node_id' => $hit['id'] !== '' ? $hit['id'] : null,
                'path' => mb_substr($hit['path'], 0, 500),
                'fetched_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table(self::BROWSE_TABLE)->upsert($chunk, ['sku'], ['asin', 'node_id', 'path', 'fetched_at', 'updated_at']);
        }

        return count($rows);
    }

    // ---- Plumbing ----

    private function marketplaceId(): string
    {
        return (string) (config('services.amazon_sp.marketplace_id') ?: 'ATVPDKIKX0DER');
    }

    private function sellerId(): string
    {
        return (string) config('services.amazon_sp.seller_id');
    }

    /**
     * One SP-API call with the access token; a refused token is renewed once. Null when nothing came back.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    private function call(string $method, string $path, array $query, ?array $body, int $timeout): ?\Illuminate\Http\Client\Response
    {
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $token = $this->api->getAccessToken($attempt > 1);
                if (empty($token)) {
                    $this->lastError = 'Could not obtain an Amazon access token.';

                    return null;
                }
                $request = Http::withoutVerifying()->timeout($timeout)->withHeaders([
                    'x-amz-access-token' => $token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]);
                $url = self::ENDPOINT.$path;
                $response = match ($method) {
                    'PATCH' => $request->patch($url.'?'.http_build_query($query), $body ?? []),
                    'PUT' => $request->put($url.'?'.http_build_query($query), $body ?? []),
                    default => $request->get($url, $query),
                };
                if (in_array($response->status(), [401, 403, 429, 500, 502, 503], true) && $attempt < 2) {
                    sleep(1);

                    continue;
                }

                return $response;
            } catch (\Throwable $e) {
                $this->lastError = $e->getMessage();
                if ($attempt >= 2) {
                    return null;
                }
                sleep(1);
            }
        }

        return null;
    }
}
