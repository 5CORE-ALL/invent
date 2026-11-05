<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SOP / Checklist document links attached to a page (shared by all users).
 * Rendered by resources/views/partials/page-doc-links.blade.php.
 */
class PageDocLinks
{
    public const TABLE = 'page_doc_links';
    public const TYPES = ['sop', 'checklist'];

    /**
     * @return array{sop: string, checklist: string}
     */
    public static function forPage(string $pageKey): array
    {
        $out = ['sop' => '', 'checklist' => ''];
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return $out;
            }
            foreach (DB::table(self::TABLE)->where('page_key', $pageKey)->get(['doc_type', 'url']) as $row) {
                if (isset($out[$row->doc_type])) {
                    $out[$row->doc_type] = (string) $row->url;
                }
            }
        } catch (\Throwable $e) {
            // table not migrated yet: behave as "no links"
        }

        return $out;
    }
}
