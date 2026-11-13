<?php

/*
|--------------------------------------------------------------------------
| Yesterday settle: tracking, verification and alerts (Phase 1 + 2)
|--------------------------------------------------------------------------
| All of this is read-only with respect to marketplace data. It only reads
| the existing order / ads / views tables and writes to the new settle_*
| tracking tables. See App\Support\Marketplace\Settle\*.
*/

return [
    /*
     * When true (default) the "Settled" state needs every task to be verified against the
     * stored data, not only to have exited with code 0. If the settle_* tables are missing
     * (migration not run yet) the day is reported as "Unverified", never "Settled".
     * Set SETTLE_REQUIRE_VERIFICATION=false to fall back to the old ledger-only behaviour.
     */
    'require_verification' => (bool) env('SETTLE_REQUIRE_VERIFICATION', true),

    /*
     * Registers the read-only `settle:verify` command in the scheduler (every 10 minutes,
     * Pacific time). Existing schedules are not touched.
     */
    'verify_schedule_enabled' => (bool) env('SETTLE_VERIFY_SCHEDULE', true),

    /*
     * A task whose verification failed / errored / is pending is re-verified at most this
     * often (minutes), so a flapping check does not hammer the database.
     */
    'reverify_after_minutes' => (int) env('SETTLE_REVERIFY_MINUTES', 10),

    /*
     * Alerts. The log is always written. E-mail and webhook are optional.
     *   SETTLE_ALERT_EMAILS=ops@example.com,dev@example.com
     *   SETTLE_ALERT_WEBHOOK=https://hooks.slack.com/services/...   (Slack-compatible {"text": ...})
     * The same alert (type + date + task + check codes) is not repeated for alert_repeat_hours.
     */
    'alerts_enabled' => (bool) env('SETTLE_ALERTS_ENABLED', true),
    'alert_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('SETTLE_ALERT_EMAILS', ''))))),
    'alert_webhook' => env('SETTLE_ALERT_WEBHOOK'),
    'alert_repeat_hours' => (int) env('SETTLE_ALERT_REPEAT_HOURS', 12),

    /*
     * Verification thresholds. "fail" blocks Settled, "warn" is shown but does not block.
     * They are deliberately conservative (fail only on clear problems) so a normal quiet day
     * does not block settlement; tune them once real production numbers are known.
     */
    'thresholds' => [
        // Previous Pacific days used as the "usual day" baseline.
        'baseline_days' => 7,
        // Zero records is a failure only when the channel normally has at least this many
        // records per day (median) on at least baseline_min_days of the baseline days.
        'zero_fail_min_median' => 3,
        'baseline_min_days' => 3,
        // Volume vs the baseline median.
        'low_volume_warn_ratio' => 0.25,
        'low_volume_warn_min_median' => 10,
        'low_volume_fail_ratio' => 0.10,
        'low_volume_fail_min_median' => 30,
        // Any duplicate key is a warning; duplicate rows at or above this % of the day's rows fail.
        'duplicate_fail_pct' => 5.0,
        // Rows with a missing / zero price as % of the day's rows (needs >= price_min_rows rows).
        'zero_price_warn_pct' => 10.0,
        'zero_price_fail_pct' => 50.0,
        'price_min_rows' => 10,
        // Orders without any line item, as % of the day's orders.
        'no_items_warn_pct' => 2.0,
        'no_items_fail_pct' => 20.0,
        // Amazon pending orders are priced only by the late pass, so zero prices are judged
        // with these (looser) numbers until that pass is done.
        'pending_price_warn_pct' => 30.0,
        // The newest record of the day is earlier than the usual one by more than this many hours.
        'latest_record_gap_hours' => 6,
        // A table that is pruned / rebuilt must not shrink below this share of its last verified size.
        'table_shrink_min_ratio' => 0.5,
        'table_shrink_min_rows' => 100,
        // Ads: spend below this share of the usual day is suspicious.
        'ads_low_spend_ratio' => 0.10,
        'ads_low_spend_min_median' => 20,
        // channel_master_calculated_data must hold at least this share of the active channels.
        'calculate_min_ratio' => 0.8,
    ],
];
