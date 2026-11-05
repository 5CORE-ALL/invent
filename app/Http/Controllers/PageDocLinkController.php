<?php

namespace App\Http\Controllers;

use App\Support\PageDocLinks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PageDocLinkController extends Controller
{
    /**
     * Save (or clear, when url is empty) the SOP / Checklist document link of a page.
     */
    public function save(Request $request)
    {
        $data = $request->validate([
            'page_key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_\-\.\/]+$/i'],
            'doc_type' => ['required', 'in:'.implode(',', PageDocLinks::TYPES)],
            'url' => ['nullable', 'string', 'max:2048'],
        ]);

        if (! Schema::hasTable(PageDocLinks::TABLE)) {
            return response()->json([
                'success' => false,
                'message' => 'Doc links table is missing. Run php artisan migrate.',
            ], 503);
        }

        $url = trim((string) ($data['url'] ?? ''));
        if ($url !== '' && (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $url))) {
            return response()->json([
                'success' => false,
                'message' => 'Enter a valid http(s) link to the document.',
            ], 422);
        }

        $where = ['page_key' => $data['page_key'], 'doc_type' => $data['doc_type']];
        if ($url === '') {
            DB::table(PageDocLinks::TABLE)->where($where)->delete();
        } else {
            $values = ['url' => $url, 'updated_by' => $request->user()?->id, 'updated_at' => now()];
            if (DB::table(PageDocLinks::TABLE)->where($where)->exists()) {
                DB::table(PageDocLinks::TABLE)->where($where)->update($values);
            } else {
                DB::table(PageDocLinks::TABLE)->insert($where + $values + ['created_at' => now()]);
            }
        }

        return response()->json(['success' => true, 'url' => $url]);
    }
}
