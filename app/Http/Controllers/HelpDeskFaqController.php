<?php

namespace App\Http\Controllers;

use App\Models\HelpDeskFaq;
use App\Models\ResourceDepartment;
use Illuminate\Http\Request;

class HelpDeskFaqController extends Controller
{
    public function index()
    {
        $departments = ResourceDepartment::orderBy('name')->get();
        $faqs = HelpDeskFaq::orderByDesc('id')->get();

        $deptNames = $departments->pluck('name', 'id');

        return view('help-desk-faqs.index', [
            'faqs' => $faqs,
            'departments' => $departments,
            'deptNames' => $deptNames,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        HelpDeskFaq::create($data);

        return redirect()->route('help-desk-faqs.index')->with('success', 'FAQ added successfully.');
    }

    /** Users allowed to edit FAQs (matched against name or email, case-insensitive). */
    private const FAQ_EDITOR_TOKENS = ['president', 'innet', 'jasmine', 'hritikhsha'];

    private function ensureCanEditFaq(Request $request): void
    {
        $user = $request->user();
        $name = strtolower($user->name ?? '');
        $email = strtolower($user->email ?? '');
        foreach (self::FAQ_EDITOR_TOKENS as $token) {
            if (($name !== '' && str_contains($name, $token)) || ($email !== '' && str_contains($email, $token))) {
                return;
            }
        }
        abort(403, 'You are not allowed to edit FAQs.');
    }

    public function update(Request $request, HelpDeskFaq $help_desk_faq)
    {
        $data = $this->validateData($request);
        $help_desk_faq->update($data);

        return redirect()->route('help-desk-faqs.index')->with('success', 'FAQ updated successfully.');
    }

    public function destroy(HelpDeskFaq $help_desk_faq)
    {
        $help_desk_faq->delete();

        return redirect()->route('help-desk-faqs.index')->with('success', 'FAQ deleted successfully.');
    }

    /**
     * Bulk edit: apply selected field values to multiple FAQs at once.
     * Only fields listed in "apply" are updated, leaving the rest untouched.
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'apply' => ['nullable', 'array'],
            'apply.*' => ['string'],
            'answers' => ['nullable', 'string'],
            'dept' => ['nullable', 'array'],
            'dept.*' => ['string'],
            'link' => ['nullable', 'string', 'max:255'],
            'link2' => ['nullable', 'string', 'max:255'],
            'sop' => ['nullable', 'string', 'max:255'],
            'video' => ['nullable', 'string', 'max:255'],
            'ca' => ['nullable', 'string'],
            'plus_action' => ['nullable', 'string'],
        ]);

        $apply = $validated['apply'] ?? [];
        if (empty($apply)) {
            return redirect()->route('help-desk-faqs.index')
                ->with('error', 'Select at least one field to update in bulk.');
        }

        $update = [];
        foreach (['answers', 'link', 'link2', 'sop', 'video', 'ca', 'plus_action'] as $f) {
            if (in_array($f, $apply, true)) {
                $update[$f] = $request->input($f);
            }
        }

        if (in_array('dept', $apply, true)) {
            $dept = $validated['dept'] ?? [];
            if (in_array('all', $dept, true)) {
                $dept = ['all'];
            }
            $update['dept'] = array_values(array_unique($dept));
        }

        $count = 0;
        foreach (HelpDeskFaq::whereIn('id', $validated['ids'])->get() as $faq) {
            $faq->update($update);
            $count++;
        }

        return redirect()->route('help-desk-faqs.index')
            ->with('success', "Bulk updated {$count} FAQ(s).");
    }

    /**
     * Bulk import FAQs from a CSV file.
     * Expected header: faq, dept, answers, link, link2, sop, video
     * The "dept" cell accepts department names/slugs/ids separated by comma or pipe,
     * or the word "all" to show the FAQ to everyone.
     */
    public function bulkImport(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $departments = ResourceDepartment::all();
        $deptLookup = [];
        foreach ($departments as $d) {
            $deptLookup[strtolower(trim($d->name))] = (string) $d->id;
            $deptLookup[strtolower(trim($d->slug))] = (string) $d->id;
            $deptLookup[(string) $d->id] = (string) $d->id;
        }

        $path = $request->file('file')->getRealPath();
        $content = file_get_contents($path);
        // Strip UTF-8 BOM
        if (substr($content, 0, 3) === "\xef\xbb\xbf") {
            $content = substr($content, 3);
        }
        $tmp = tmpfile();
        fwrite($tmp, $content);
        rewind($tmp);

        $header = null;
        $map = [];
        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($tmp)) !== false) {
            if ($row === [null] || count(array_filter($row, fn($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }

            if ($header === null) {
                $header = array_map(fn($h) => strtolower(trim($h)), $row);
                foreach (['faq', 'dept', 'answers', 'link', 'link2', 'sop', 'video', 'action', 'ca', 'plus_action', 'messages'] as $col) {
                    $idx = array_search($col, $header, true);
                    if ($idx !== false) {
                        $map[$col] = $idx;
                    }
                }
                if (!isset($map['faq'])) {
                    fclose($tmp);
                    return redirect()->route('help-desk-faqs.index')
                        ->with('error', 'CSV must contain a "faq" column. Expected header: faq, dept, answers, link, link2, sop, video');
                }
                continue;
            }

            $get = fn($col) => isset($map[$col]) ? trim((string) ($row[$map[$col]] ?? '')) : '';

            $faq = $get('faq');
            if ($faq === '') {
                $skipped++;
                continue;
            }

            $dept = [];
            $deptRaw = $get('dept');
            if ($deptRaw !== '') {
                $parts = preg_split('/[|,]/', $deptRaw);
                foreach ($parts as $part) {
                    $key = strtolower(trim($part));
                    if ($key === '') {
                        continue;
                    }
                    if ($key === 'all') {
                        $dept = ['all'];
                        break;
                    }
                    if (isset($deptLookup[$key])) {
                        $dept[] = $deptLookup[$key];
                    }
                }
                $dept = array_values(array_unique($dept));
            }

            HelpDeskFaq::create([
                'faq' => $faq,
                'dept' => $dept,
                'answers' => $get('answers') ?: null,
                'link' => $get('link') ?: null,
                'link2' => $get('link2') ?: null,
                'sop' => $get('sop') ?: null,
                'video' => $get('video') ?: null,
                'action' => $get('action') ?: null,
                'ca' => $get('ca') ?: null,
                'plus_action' => $get('plus_action') ?: null,
                'messages' => $get('messages') ? mb_substr($get('messages'), 0, 200) : null,
            ]);
            $imported++;
        }

        fclose($tmp);

        $msg = "Imported {$imported} FAQ(s).";
        if ($skipped > 0) {
            $msg .= " Skipped {$skipped} row(s) with an empty FAQ.";
        }

        return redirect()->route('help-desk-faqs.index')->with('success', $msg);
    }

    /**
     * Download a sample CSV template for bulk FAQ import.
     */
    public function sampleCsv()
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="help-desk-faqs-sample.csv"',
        ];

        $callback = function () {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel
            fputcsv($out, ['faq', 'dept', 'answers', 'link', 'link2', 'sop', 'video', 'action', 'ca', 'plus_action', 'messages']);
            fputcsv($out, [
                'How do I reset my password?',
                'all',
                'Go to Settings > Security > Reset Password and follow the email link.',
                'https://example.com/reset',
                '',
                '',
                '',
                'Verify the user identity before resetting.',
                '',
                '',
                'Contact IT if you do not receive the reset email within 5 minutes.',
            ]);
            fputcsv($out, [
                'How do I apply for leave?',
                'HR|Management',
                'Open HR module > Apply Leave > select dates > submit.',
                '',
                '',
                'https://example.com/leave-sop',
                'https://example.com/leave-video',
                'Submit the leave form to your manager.',
                'Notify your manager and update the leave tracker.',
                'Set up an auto-responder before your leave starts.',
                'Leave requests must be submitted at least 3 days in advance.',
            ]);
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function validateData(Request $request): array
    {
        $validated = $request->validate([
            'faq' => ['required', 'string'],
            'answers' => ['nullable', 'string'],
            'dept' => ['nullable', 'array'],
            'dept.*' => ['string'],
            'link' => ['nullable', 'string', 'max:255'],
            'link2' => ['nullable', 'string', 'max:255'],
            'sop' => ['nullable', 'string', 'max:255'],
            'video' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'string'],
            'ca' => ['nullable', 'string'],
            'plus_action' => ['nullable', 'string'],
            'messages' => ['nullable', 'string', 'max:200'],
        ]);

        $dept = $validated['dept'] ?? [];
        // If "all" is selected, store just ["all"] so the FAQ is visible to everyone.
        if (in_array('all', $dept, true)) {
            $dept = ['all'];
        }
        $validated['dept'] = array_values(array_unique($dept));

        return $validated;
    }
}
