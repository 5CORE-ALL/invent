# Google Maps Data Extractor

## Purpose

The Google Maps Data Extractor is a Laravel/Blade tool for collecting real business lead data from Google Maps/search results without using the Google Places API. It is currently built for testing/prototyping and focused on USA locations.

It extracts and stores:

- Business or school name
- Phone
- Address
- Website
- Email, when found through website enrichment
- Social media links
- Google Maps URL
- Category, rating, and review count when available

## Main Files

- `resources/views/tools/google-maps-data-extractor.blade.php`
  - Main UI.
  - Search form.
  - USA state/city picker.
  - AJAX extraction progress overlay.
  - Pause/resume, stop, cancel controls.
  - Result filtering and CSV export link behavior.

- `app/Http/Controllers/GoogleMapsDataExtractorController.php`
  - Handles extraction requests, AJAX batch processing, progress, controls, enrichment, display, and export.
  - Persists records incrementally while scraping so fetched rows are not lost if a run is stopped.

- `app/Services/GoogleMapsScraperService.php`
  - Builds Google search/map queries.
  - Fetches Google local/maps/preload responses.
  - Parses lead records.
  - Supports throttling, same-city pagination, and cooperative pause/stop/cancel checks.

- `app/Services/WebsiteContactExtractorService.php`
  - Crawls business websites to enrich leads with emails, phones, and social links.
  - Filters placeholder/infrastructure emails and deduplicates social links.

- `app/Models/GoogleMapsExtractorSearch.php`
  - Stores extraction search metadata.

- `app/Models/GoogleMapsExtractorResult.php`
  - Stores extracted leads.

- `database/migrations/2026_06_09_215000_create_google_maps_extractor_tables.php`
  - Creates `google_maps_extractor_searches` and `google_maps_extractor_results`.

- `routes/web.php`
  - Contains the `google-maps-data-extractor.*` routes.

## Routes

All routes are under:

```text
google-maps-data-extractor
```

Current route names:

- `google-maps-data-extractor.index`
- `google-maps-data-extractor.search`
- `google-maps-data-extractor.start`
- `google-maps-data-extractor.process`
- `google-maps-data-extractor.progress`
- `google-maps-data-extractor.control`
- `google-maps-data-extractor.show`
- `google-maps-data-extractor.enrich`
- `google-maps-data-extractor.enrich-batch`
- `google-maps-data-extractor.export`

## UI Behavior

The search form supports:

- Search query, for example `music school`
- Country fixed to `United States`
- State picker
- Specific city multi-select
- Specific ZIP code manual input
- Result limit up to `5000`

The city picker uses the browser ES module version of `country-state-city`:

```js
import { State, City } from 'https://cdn.jsdelivr.net/npm/country-state-city@3.2.1/+esm';
```

City selections are submitted as one JSON field:

```text
location_city_payload
```

This avoids PHP `max_input_vars` issues when users select hundreds or thousands of cities.

## Extraction Flow

The current UI uses AJAX batch extraction instead of one long blocking request.

Flow:

1. User submits the form.
2. Frontend creates a `progress_token`.
3. Frontend calls `google-maps-data-extractor.start`.
4. Backend creates a `GoogleMapsExtractorSearch` row and stores the query plan in cache.
5. Frontend repeatedly calls `google-maps-data-extractor.process`.
6. Each `process` call handles one search source/page step.
7. Records are persisted immediately after each step.
8. Frontend polls `google-maps-data-extractor.progress` for live logs and record counts.
9. When complete, stopped, or cancelled, the frontend redirects to the relevant page.

The older synchronous `search` method still exists as fallback/backward-compatible code.

## Progress and Controls

Progress state is stored in Laravel cache using:

- `google_maps_extractor_progress:{token}`
- `google_maps_extractor_state:{token}`
- `google_maps_extractor_control:{token}`

The overlay shows:

- Current progress message
- Total saved records
- Current search index
- Rolling log messages

Controls:

- Pause/Resume toggle
- Stop & Keep
- Cancel & Discard

Behavior:

- Pause holds the AJAX loop until resumed.
- Resume continues processing.
- Stop ends the run and keeps already saved records.
- Cancel ends the run and deletes records from that extraction.

## Pagination Strategy

For each selected city/query, the scraper tries:

- Google Maps page
- Google local search pages using offsets:

```text
start=0
start=10
start=20
...
```

The scraper has a high safety ceiling per city and stops local pagination early if consecutive pages add no new unique records.

Relevant constants in `GoogleMapsScraperService`:

```php
private const CITY_QUERY_CHUNK_SIZE = 30;
private const MAX_LOCAL_PAGES_PER_CITY = 100;
private const EMPTY_LOCAL_PAGES_BEFORE_STOP = 2;
```

## Throttling

The scraper uses delays between page/city requests to reduce request bursts.

Important behavior:

- Short delays between pages for the same city.
- Longer delays after city chunks.
- AJAX mode naturally spaces requests because each step is a separate request.

This is not stealth or anti-detection logic. It is only conservative throttling and workload control.

## Data Persistence

Records are saved incrementally through `persistExtractorRecords()`.

Deduping preference:

1. Website
2. Google Maps URL
3. Name + address

This fixes the old issue where logs showed records but the UI stayed at `0` until the whole run finished.

## Enrichment

Website enrichment is separate from initial Google scraping.

The enrichment flow:

- Uses `WebsiteContactExtractorService`
- Crawls websites in quick mode for larger runs
- Extracts emails, phones, and social links
- Filters invalid emails like placeholders, `wixpress.com`, `user@domain.com`, etc.
- Deduplicates social platform links

AJAX enrichment endpoint:

```text
google-maps-data-extractor.enrich-batch
```

## Export and Filtering

The UI includes lead filters:

- All leads
- Has email
- Has phone
- Has email or phone
- Has social

Export respects the selected filter through the `filter` query parameter.

## Known Limitations

- This is scraping, not the official Google Places API.
- Google can return different HTML or fewer results to automated requests than manual browser searches.
- Some Google local pages return shell/limited HTML with no parseable records.
- Same-city pagination uses `start=` offsets for local search and preload URLs where available; it does not automate browser scrolling/clicking.
- ZIP-code dropdowns are not implemented because `country-state-city` does not provide ZIP-code lists.
- Large runs can take a long time, especially with all cities selected and high limits.
- For production-scale or compliance-sensitive use, switch to the official Google Places API.

## Operational Notes

To run locally:

```powershell
php artisan serve
```

If an extraction is stuck and controls do not respond, stop the Laravel PHP server process and restart it.

During development, check:

```text
storage/logs/laravel.log
```

Useful log markers:

- `Google Maps extractor search started`
- `Google Maps extractor search attempt started`
- `Google Maps extractor HTTP response received`
- `Google Maps extractor source parsed`
- `Google Maps extractor preload parsed`
- `Google Maps extractor sleeping before next attempt`

## Future Improvements

- Move extraction processing to Laravel queues/jobs instead of browser-driven AJAX.
- Add durable database-backed progress/control state instead of cache.
- Add retry/backoff for transient Google/network failures.
- Add API-based extraction mode using Google Places API.
- Add richer duplicate merging rules.
- Add downloadable partial export during a running extraction.
