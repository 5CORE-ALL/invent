<?php

namespace App\Support;

/**
 * Normalizes OPENAI_API_KEY from .env and builds request headers for api.openai.com.
 */
class OpenAiRequest
{
    /**
     * Strip invisible Unicode, odd whitespace, and smart quotes so pasted keys match what OpenAI issued.
     * OpenAI secrets are ASCII (sk-… / sk-proj-…); anything else is almost always a copy/paste artifact.
     */
    public static function normalizeApiKey(?string $k): ?string
    {
        if ($k === null) {
            return null;
        }
        $k = (string) $k;
        $k = str_replace(["\r\n", "\r", "\n"], '', $k);
        if (str_starts_with($k, "\xEF\xBB\xBF")) {
            $k = substr($k, 3);
        }
        // Zero-width spaces, word joiner, BOM-as-char, etc.
        $k = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{2060}\x{180E}]/u', '', $k) ?? '';
        // Unicode space / separator classes (NBSP, thin space, etc.)
        $k = preg_replace('/\p{Z}+/u', '', $k) ?? '';
        $k = trim($k);
        if ($k === '') {
            return null;
        }
        if (
            (str_starts_with($k, '"') && str_ends_with($k, '"') && strlen($k) >= 2)
            || (str_starts_with($k, "'") && str_ends_with($k, "'") && strlen($k) >= 2)
        ) {
            $k = trim(substr($k, 1, -1));
        }
        // Curly quotes often pasted from docs / chat
        $k = trim($k, " \t\x0B\u{00A0}\u{201C}\u{201D}\u{2018}\u{2019}");
        // Drop any remaining non-ASCII (homoglyphs, RTL marks, accidental emoji)
        $k = preg_replace('/[^\x21-\x7E]/', '', $k) ?? '';
        $k = trim($k);

        return $k !== '' ? $k : null;
    }

    private static function sanitizeOptionalHeaderValue(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = (string) $v;
        $v = str_replace(["\r\n", "\r", "\n"], '', $v);
        $v = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{2060}]/u', '', $v) ?? '';
        $v = preg_replace('/\p{Z}+/u', '', $v) ?? '';
        $v = trim($v);
        $v = preg_replace('/[^\x21-\x7E]/', '', $v) ?? '';

        return $v !== '' ? $v : null;
    }

    /**
     * @return array<string, string>
     */
    public static function authHeaders(): array
    {
        $key = config('services.openai.key');
        if (! is_string($key) || $key === '') {
            return [];
        }

        $headers = [
            'Authorization' => 'Bearer '.$key,
            'Content-Type' => 'application/json',
        ];

        $org = self::sanitizeOptionalHeaderValue(config('services.openai.organization'));
        if ($org !== null) {
            $headers['OpenAI-Organization'] = $org;
        }

        $project = self::sanitizeOptionalHeaderValue(config('services.openai.project'));
        if ($project !== null) {
            $headers['OpenAI-Project'] = $project;
        }

        return $headers;
    }
}
