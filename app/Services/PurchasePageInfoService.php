<?php

namespace App\Services;

use App\Models\PurchasePageInfoNote;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PurchasePageInfoService
{
    private const EDIT_EMAILS = [
        'purchase@5core.com',
        'president@5core.com',
    ];

    public static function userCanEdit(): bool
    {
        $email = strtolower(trim((string) Auth::user()?->email ?? ''));

        return in_array($email, self::EDIT_EMAILS, true);
    }

    public static function resolvePageKeyFromRoute(?string $routeName = null): ?string
    {
        $routeName = $routeName ?? request()->route()?->getName();
        if (! $routeName) {
            return null;
        }

        $keys = config('purchase.page_info_keys', []);

        return is_array($keys) ? ($keys[$routeName] ?? null) : null;
    }

    public static function isValidPageKey(string $pageKey): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_]{0,63}$/', $pageKey);
    }

    public static function hasContent(?string $html): bool
    {
        if ($html === null) {
            return false;
        }

        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($decoded)) ?? '');

        return $text !== '';
    }

    /** Convert GPT markdown / plain text / HTML into safe display HTML. */
    public static function normalizeContent(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $raw = trim(str_replace(["\r\n", "\r"], "\n", $raw));
        if ($raw === '') {
            return null;
        }

        if (self::looksLikeHtml($raw)) {
            return self::sanitizeHtml($raw);
        }

        return self::sanitizeHtml(
            (string) Str::markdown($raw, [
                'html_input' => 'allow',
                'allow_unsafe_links' => false,
            ])
        );
    }

    public static function looksLikeHtml(string $content): bool
    {
        return (bool) preg_match('/<\/?[a-z][\s\S]*>/i', $content);
    }

    public static function sanitizeHtml(string $html): string
    {
        $html = preg_replace('/<(script|iframe|object|embed|form|meta|link|base)[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
        $html = preg_replace('/<(script|iframe|object|embed|form|meta|link|base)[^>]*\/?>/i', '', $html) ?? $html;
        $html = preg_replace('/\s(on\w+)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/javascript:/i', '', $html) ?? $html;

        return trim($html);
    }

    /** Plain / markdown text for the editor — never raw HTML tags. */
    public static function contentForEditor(?string $stored): string
    {
        if ($stored === null) {
            return '';
        }

        $stored = trim(str_replace(["\r\n", "\r"], "\n", $stored));
        if ($stored === '') {
            return '';
        }

        if (! self::looksLikeHtml($stored)) {
            return $stored;
        }

        return self::htmlToPlainText($stored);
    }

    public static function htmlToPlainText(string $html): string
    {
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/p>\s*/i', "\n\n", $html) ?? $html;
        $html = preg_replace('/<\/h[1-6]>\s*/i', "\n\n", $html) ?? $html;
        $html = preg_replace('/<\/li>\s*/i', "\n", $html) ?? $html;
        $html = preg_replace('/<li[^>]*>/i', '- ', $html) ?? $html;
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    public function getNote(string $pageKey): ?PurchasePageInfoNote
    {
        $this->assertValidPageKey($pageKey);

        return PurchasePageInfoNote::query()->where('page_key', $pageKey)->first();
    }

    public function pagePayload(string $pageKey): array
    {
        $note = $this->getNote($pageKey);
        $stored = $note?->html_content;

        return [
            'page_key' => $pageKey,
            'content' => $stored ?? '',
            'has_content' => self::hasContent($stored),
            'can_edit' => self::userCanEdit(),
            'updated_by' => $note?->updated_by,
            'updated_at' => optional($note?->updated_at)->toIso8601String(),
        ];
    }

    public function saveHtml(string $pageKey, ?string $htmlContent): PurchasePageInfoNote
    {
        $this->assertValidPageKey($pageKey);

        $stored = $htmlContent !== null ? trim(str_replace(["\r\n", "\r"], "\n", $htmlContent)) : null;
        if ($stored === '') {
            $stored = null;
        }

        return PurchasePageInfoNote::query()->updateOrCreate(
            ['page_key' => $pageKey],
            [
                'html_content' => $stored,
                'updated_by' => Auth::user()?->email,
            ]
        );
    }

    private function assertValidPageKey(string $pageKey): void
    {
        if (! self::isValidPageKey($pageKey)) {
            throw new \InvalidArgumentException('Invalid page key.');
        }
    }
}
