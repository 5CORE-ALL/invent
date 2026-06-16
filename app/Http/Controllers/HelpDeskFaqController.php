<?php

namespace App\Http\Controllers;

use App\Models\HelpDeskFaq;
use App\Models\HelpDeskGuru;
use App\Models\ResourceDepartment;
use Illuminate\Http\Request;

class HelpDeskFaqController extends Controller
{
    /** Emails that can manage the Guru list and delete FAQs. */
    private const GURU_MANAGERS = ['president@5core.com', 'software5@5core.com', 'mgr-advertisement@5core.com'];

    private function userEmail(Request $request): string
    {
        return strtolower(trim((string) ($request->user()->email ?? '')));
    }

    /** Managers (president / software5) can manage gurus and delete FAQs. */
    private function isGuruManager(Request $request): bool
    {
        return in_array($this->userEmail($request), self::GURU_MANAGERS, true);
    }

    /** Gurus + managers can add/edit FAQ data (no delete power for gurus). */
    private function canEditFaq(Request $request): bool
    {
        if ($this->isGuruManager($request)) {
            return true;
        }
        if (! \Illuminate\Support\Facades\Schema::hasTable('help_desk_gurus')) {
            return false;
        }
        $email = $this->userEmail($request);
        return $email !== '' && HelpDeskGuru::whereRaw('LOWER(email) = ?', [$email])->exists();
    }

    private function appendHistory(?array $history, string $email, string $action): array
    {
        $history = is_array($history) ? $history : [];
        $history[] = [
            'email' => $email,
            'action' => $action,
            'at' => now()->toDateTimeString(),
        ];
        return $history;
    }

    public function index(Request $request)
    {
        $departments = ResourceDepartment::orderBy('name')->get();
        $perPage = (int) ($request->input('per_page', 50));
        if (!in_array($perPage, [25, 50, 100, 200, 500])) {
            $perPage = 50;
        }

        $faqs = \Illuminate\Support\Facades\Schema::hasTable('help_desk_faqs')
            ? HelpDeskFaq::orderByDesc('id')->paginate($perPage)->withQueryString()
            : collect();

        $deptNames = $departments->pluck('name', 'id');

        $gurus = \Illuminate\Support\Facades\Schema::hasTable('help_desk_gurus')
            ? HelpDeskGuru::orderBy('name')->orderBy('email')->get()
            : collect();

        return view('help-desk-faqs.index', [
            'faqs' => $faqs,
            'departments' => $departments,
            'deptNames' => $deptNames,
            'gurus' => $gurus,
            'canEditFaq' => $this->canEditFaq($request),
            'canDeleteFaq' => $this->isGuruManager($request),
            'isGuruManager' => $this->isGuruManager($request),
            'perPage' => $perPage,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $email = $this->userEmail($request);
        $data['created_by_email'] = $email;
        $data['updated_by_email'] = $email;
        $data['edit_history'] = $this->appendHistory([], $email, 'added');
        HelpDeskFaq::create($data);

        return redirect()->route('help-desk-faqs.index')->with('success', 'FAQ added successfully.');
    }

    public function update(Request $request, HelpDeskFaq $help_desk_faq)
    {
        if (!$this->canEditFaq($request)) {
            abort(403, 'Only Guru users can edit FAQs.');
        }
        $data = $this->validateData($request);
        $email = $this->userEmail($request);
        $data['updated_by_email'] = $email;
        $data['edit_history'] = $this->appendHistory($help_desk_faq->edit_history, $email, 'edited');
        $help_desk_faq->update($data);

        return redirect()->route('help-desk-faqs.index')->with('success', 'FAQ updated successfully.');
    }

    public function destroy(Request $request, HelpDeskFaq $help_desk_faq)
    {
        if (!$this->isGuruManager($request)) {
            abort(403, 'You are not allowed to delete FAQs.');
        }
        $help_desk_faq->delete(); // soft delete (archive)

        return redirect()->route('help-desk-faqs.index')->with('success', 'FAQ archived. You can restore it from Archived.');
    }

    /**
     * Add a Guru user (managers only).
     */
    public function storeGuru(Request $request)
    {
        if (!$this->isGuruManager($request)) {
            abort(403, 'Only president@5core.com and software5@5core.com can manage Guru users.');
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        HelpDeskGuru::updateOrCreate(
            ['email' => strtolower(trim($validated['email']))],
            ['name' => $validated['name'] ?? null, 'created_by_email' => $this->userEmail($request)]
        );

        return redirect()->route('help-desk-faqs.index')->with('success', 'Guru user saved.');
    }

    /**
     * Remove a Guru user (managers only).
     */
    public function destroyGuru(Request $request, $id)
    {
        if (!$this->isGuruManager($request)) {
            abort(403, 'Only president@5core.com and software5@5core.com can manage Guru users.');
        }

        HelpDeskGuru::where('id', $id)->delete();

        return redirect()->route('help-desk-faqs.index')->with('success', 'Guru user removed.');
    }

    /**
     * List archived (soft-deleted) FAQs.
     */
    public function archived()
    {
        $departments = ResourceDepartment::orderBy('name')->get();
        $deptNames = $departments->pluck('name', 'id');
        $faqs = HelpDeskFaq::onlyTrashed()->orderByDesc('deleted_at')->get();

        return view('help-desk-faqs.archived', [
            'faqs' => $faqs,
            'departments' => $departments,
            'deptNames' => $deptNames,
        ]);
    }

    /**
     * Restore an archived FAQ.
     */
    public function restore(Request $request, $id)
    {
        if (!$this->isGuruManager($request)) {
            abort(403, 'You are not allowed to restore FAQs.');
        }
        $faq = HelpDeskFaq::onlyTrashed()->findOrFail($id);
        $faq->restore();

        return redirect()->route('help-desk-faqs.archived')->with('success', 'FAQ restored successfully.');
    }

    /**
     * Permanently delete an archived FAQ.
     */
    public function forceDelete(Request $request, $id)
    {
        if (!$this->isGuruManager($request)) {
            abort(403, 'You are not allowed to permanently delete FAQs.');
        }
        $faq = HelpDeskFaq::onlyTrashed()->findOrFail($id);
        $faq->forceDelete();

        return redirect()->route('help-desk-faqs.archived')->with('success', 'FAQ permanently deleted.');
    }

    /**
     * Bulk edit: apply selected field values to multiple FAQs at once.
     * Only fields listed in "apply" are updated, leaving the rest untouched.
     */
    public function bulkUpdate(Request $request)
    {
        if (!$this->canEditFaq($request)) {
            abort(403, 'Only Guru users can edit FAQs.');
        }
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'apply' => ['nullable', 'array'],
            'apply.*' => ['string'],
            'group_name' => ['nullable', 'string', 'max:255'],
            'type_variant' => ['nullable', 'string'],
            'what' => ['nullable', 'string'],
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
            'messages' => ['nullable', 'string'],
        ]);

        $apply = $validated['apply'] ?? [];
        if (empty($apply)) {
            return redirect()->route('help-desk-faqs.index')
                ->with('error', 'Select at least one field to update in bulk.');
        }

        $update = [];
        foreach (['group_name', 'type_variant', 'what', 'answers', 'link', 'link2', 'sop', 'video', 'action', 'ca', 'plus_action', 'messages'] as $f) {
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

        $email = $this->userEmail($request);
        $count = 0;
        foreach (HelpDeskFaq::whereIn('id', $validated['ids'])->get() as $faq) {
            $rowUpdate = $update;
            $rowUpdate['updated_by_email'] = $email;
            $rowUpdate['edit_history'] = $this->appendHistory($faq->edit_history, $email, 'edited (bulk)');
            $faq->update($rowUpdate);
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
        if (!$this->canEditFaq($request)) {
            abort(403, 'Only Guru users can import FAQs.');
        }
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
        ]);

        $importerEmail = $this->userEmail($request);

        $departments = ResourceDepartment::all();
        $deptLookup = [];
        foreach ($departments as $d) {
            $deptLookup[strtolower(trim($d->name))] = (string) $d->id;
            $deptLookup[strtolower(trim($d->slug))] = (string) $d->id;
            $deptLookup[(string) $d->id] = (string) $d->id;
        }

        // Read all rows from CSV or Excel into a uniform array.
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $rows = [];

        if (in_array($ext, ['xlsx', 'xls'], true)) {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        } else {
            $content = file_get_contents($file->getRealPath());
            if (substr($content, 0, 3) === "\xef\xbb\xbf") {
                $content = substr($content, 3);
            }
            // Normalize line endings (handle Windows \r\n and old Mac \r) so every row is read.
            $content = str_replace(["\r\n", "\r"], "\n", $content);
            $tmp = tmpfile();
            fwrite($tmp, $content);
            rewind($tmp);
            while (($csvRow = fgetcsv($tmp)) !== false) {
                $rows[] = $csvRow;
            }
            fclose($tmp);
        }

        $header = null;
        $map = [];
        $imported = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (!is_array($row) || count(array_filter($row, fn($v) => $v !== null && trim((string) $v) !== '')) === 0) {
                continue;
            }

            if ($header === null) {
                $header = array_map(fn($h) => strtolower(trim((string) $h)), $row);
                foreach (['group', 'faq', 'dept', 'type_variant', 'what', 'answers', 'link', 'link2', 'sop', 'video', 'action', 'ca', 'plus_action', 'messages'] as $col) {
                    $idx = array_search($col, $header, true);
                    if ($idx !== false) {
                        $map[$col] = $idx;
                    }
                }
                if (!isset($map['faq'])) {
                    return redirect()->route('help-desk-faqs.index')
                        ->with('error', 'File must contain a "faq" column. Expected header: group, faq, dept, ...');
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

            $groupName = $get('group') ?: null;

            // Match an existing FAQ by group + question so re-uploading the same
            // row overwrites it instead of creating a duplicate.
            $existing = HelpDeskFaq::where('faq', $faq)
                ->where(function ($q) use ($groupName) {
                    if ($groupName === null) {
                        $q->whereNull('group_name');
                    } else {
                        $q->where('group_name', $groupName);
                    }
                })
                ->first();

            $attributes = [
                'group_name' => $groupName,
                'faq' => $faq,
                'dept' => $dept,
                'type_variant' => $get('type_variant') ?: null,
                'what' => $get('what') ?: null,
                'answers' => $get('answers') ?: null,
                'link' => $get('link') ?: null,
                'link2' => $get('link2') ?: null,
                'sop' => $get('sop') ?: null,
                'video' => $get('video') ?: null,
                'action' => $get('action') ?: null,
                'ca' => $get('ca') ?: null,
                'plus_action' => $get('plus_action') ?: null,
                'messages' => $get('messages') ?: null,
            ];

            if ($existing) {
                $attributes['updated_by_email'] = $importerEmail;
                $attributes['edit_history'] = $this->appendHistory($existing->edit_history, $importerEmail, 'edited (import)');
                $existing->update($attributes);
                $updated++;
            } else {
                $attributes['created_by_email'] = $importerEmail;
                $attributes['updated_by_email'] = $importerEmail;
                $attributes['edit_history'] = $this->appendHistory([], $importerEmail, 'added (import)');
                HelpDeskFaq::create($attributes);
                $imported++;
            }
        }

        $msg = "Imported {$imported} new FAQ(s).";
        if ($updated > 0) {
            $msg .= " Updated {$updated} existing FAQ(s).";
        }
        if ($skipped > 0) {
            $msg .= " Skipped {$skipped} row(s) with an empty FAQ.";
        }

        return redirect()->route('help-desk-faqs.index')->with('success', $msg);
    }

    /**
     * Download an Excel template for bulk FAQ import, with a department dropdown
     * (data validation) on the "dept" column.
     */
    public function sampleCsv()
    {
        $columns = ['group', 'faq', 'dept', 'type_variant', 'what', 'answers', 'link', 'link2', 'sop', 'video', 'action', 'ca', 'plus_action', 'messages'];

        $deptOptions = array_merge(['all'], ResourceDepartment::orderBy('name')->pluck('name')->toArray());
        $listFormula = '"' . implode(',', $deptOptions) . '"';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('FAQs');

        // Header row
        $sheet->fromArray($columns, null, 'A1');
        $sheet->getStyle('A1:N1')->getFont()->setBold(true);

        // "dept" is the 3rd column (C). Add a dropdown to the data rows.
        $deptColIndex = array_search('dept', $columns, true);
        $deptColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($deptColIndex + 1);

        for ($row = 2; $row <= 500; $row++) {
            $validation = $sheet->getCell($deptColLetter . $row)->getDataValidation();
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(false); // allow typing multiple (e.g. HR|MGMT)
            $validation->setShowDropDown(true);
            $validation->setPromptTitle('Department');
            $validation->setPrompt('Pick a department, type multiple separated by | (e.g. HR|MGMT), or "all".');
            $validation->setFormula1($listFormula);
        }

        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'help-desk-faqs-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function validateData(Request $request): array
    {
        $validated = $request->validate([
            'group_name' => ['nullable', 'string', 'max:255'],
            'faq' => ['required', 'string'],
            'answers' => ['nullable', 'string'],
            'type_variant' => ['nullable', 'string'],
            'what' => ['nullable', 'string'],
            'dept' => ['nullable', 'array'],
            'dept.*' => ['string'],
            'link' => ['nullable', 'string', 'max:255'],
            'link2' => ['nullable', 'string', 'max:255'],
            'sop' => ['nullable', 'string', 'max:255'],
            'video' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'string'],
            'ca' => ['nullable', 'string'],
            'plus_action' => ['nullable', 'string'],
            'messages' => ['nullable', 'string'],
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
