<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ComplianceDocRequest;
use App\Support\Badges\ComplianceMasterBadgeCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * "Mail" badge on the Compliance <Badge> pages: writes (and sends) an e-mail asking the parent's supplier for the
 * compliance documents still missing for that badge. The recipient is always resolved on the server from the
 * parent's SKUs (supplier name + e-mail of that SKU), never taken from the browser.
 */
class ComplianceDocRequestController extends Controller
{
    private const FIELD_LABELS = [
        'battery' => 'Battery',
        'wireless' => 'Wireless',
        'electric' => 'Electric',
        'gcc' => 'GCC',
        'rohs' => 'RoHS',
        'blanket' => 'Blanket',
        'bluetooth' => 'Bluetooth',
        'logo' => 'Logo',
        'graph' => 'Graph',
        'tsp' => 'TSP',
        'batch' => 'Batch',
        'energy_star_registration' => 'Energy Star Registration',
        'us_energy_efficiency_label' => 'US Energy Efficiency Label',
    ];

    /** What to ask for, per badge. Badges not listed get a generic request. */
    private const FIELD_DOCS = [
        'battery' => ['UN38.3 test summary / report', 'MSDS / SDS (safety data sheet) for the battery', 'Battery test report and certificate (IEC 62133 / UL)', 'Battery label / marking photos'],
        'wireless' => ['FCC / CE-RED test report', 'Wireless compliance certificate'],
        'electric' => ['Electrical safety test report', 'Safety certificate (UL / ETL / CE-LVD)'],
        'gcc' => ['GCC certificate of conformity'],
        'rohs' => ['RoHS test report', 'RoHS certificate / declaration of conformity'],
        'bluetooth' => ['Bluetooth SIG listing (QDID) / certificate', 'Bluetooth test report'],
        'energy_star_registration' => ['Energy Star registration / certificate'],
    ];

    /** GET: recipient, sender, subject and body for one parent (nothing is sent). */
    public function preview(Request $request): JsonResponse
    {
        $built = $this->build($request);
        if ($built instanceof JsonResponse) {
            return $built;
        }

        return response()->json(['success' => true] + $this->publicView($built));
    }

    /**
     * POST: send the e-mail to the parent's supplier. A request form (PDF) is always attached, plus any files the
     * user picked in the modal; the mail and its attachments are saved (see history()).
     */
    public function send(Request $request): JsonResponse
    {
        return $this->handle($request, true);
    }

    /** POST: save the mail and its attachments without sending it (a draft in the history list). */
    public function save(Request $request): JsonResponse
    {
        return $this->handle($request, false);
    }

    /** GET: the saved mails (sent / drafts) of one parent + badge, newest first, with download links. */
    public function history(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parent' => 'required|string|max:255',
            'field' => 'required|string|in:'.implode(',', ComplianceMasterBadgeCalculator::FIELD_KEYS),
        ]);

        $rows = ComplianceDocRequest::query()
            ->where('parent', $data['parent'])->where('field', $data['field'])
            ->orderByDesc('id')->limit(15)->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(function (ComplianceDocRequest $r) {
                return [
                    'id' => $r->id,
                    'status' => $r->status,
                    'subject' => $r->subject,
                    'to' => $r->to_emails,
                    'by' => $r->user_name,
                    'when' => optional($r->sent_at ?: $r->created_at)->format('d M Y H:i'),
                    'error' => $r->error,
                    'attachments' => collect($r->attachments ?? [])->map(fn ($a, $i) => [
                        'name' => $a['name'] ?? 'file',
                        'size' => (int) ($a['size'] ?? 0),
                        'url' => route('compliance.doc.request.download', ['id' => $r->id, 'index' => $i]),
                    ])->values(),
                ];
            })->values(),
        ]);
    }

    /** GET: download one saved attachment. */
    public function download(int $id, int $index)
    {
        $r = ComplianceDocRequest::query()->findOrFail($id);
        $att = ($r->attachments ?? [])[$index] ?? null;
        if (! $att || empty($att['path']) || ! Storage::disk('local')->exists($att['path'])) {
            abort(404);
        }

        return Storage::disk('local')->download($att['path'], $att['name'] ?? basename($att['path']));
    }

    private function handle(Request $request, bool $send): JsonResponse
    {
        $request->validate([
            'note' => 'nullable|string|max:2000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,csv,txt,zip',
        ]);
        $built = $this->build($request);
        if ($built instanceof JsonResponse) {
            return $built;
        }

        $sender = $built['sender'];
        if ($send) {
            if ($built['to_emails'] === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'No email address saved for supplier "'.($built['supplier'] ?: 'unknown').'". Add it on the Suppliers page first.',
                ], 422);
            }
            if (str_starts_with($sender['mailer'], 'quotes') && trim((string) config('mail.mailers.'.$sender['mailer'].'.password')) === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'The sending mailbox is not connected yet (mail password is missing in .env).',
                ], 422);
            }
        }

        $user = Auth::user();
        $record = ComplianceDocRequest::query()->create([
            'parent' => $built['parent'],
            'field' => $built['field'],
            'supplier' => $built['supplier'],
            'to_emails' => implode(', ', $built['to_emails']),
            'from_email' => $sender['email'],
            'from_name' => $built['from_name'],
            'subject' => $built['subject'],
            'body_html' => $built['html'],
            'note' => $built['note'] !== '' ? $built['note'] : null,
            'status' => 'draft',
            'user_id' => $user->id ?? null,
            'user_name' => $built['from_name'],
        ]);

        // The request form (PDF) is always attached; uploaded files go along with it.
        $files = $this->storeAttachments($record, $built, $request);
        $names = array_column($files, 'name');
        $html = $built['html'];
        if ($names !== []) {
            $html .= '<p style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280">Attached: '
                .htmlspecialchars(implode(', ', $names), ENT_QUOTES, 'UTF-8').'</p>';
        }
        $record->update(['attachments' => $files, 'body_html' => $html]);

        if (! $send) {
            return response()->json([
                'success' => true,
                'message' => 'Saved (not sent) with '.count($files).' attachment'.(count($files) === 1 ? '' : 's').'.',
            ]);
        }

        try {
            Mail::mailer($sender['mailer'])->html($html, function ($m) use ($built, $sender, $user, $files) {
                $m->from($sender['email'], $built['from_name'])->subject($built['subject']);
                $userEmail = trim((string) ($user->email ?? ''));
                if ($userEmail !== '' && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                    $m->replyTo($userEmail, $built['from_name']);
                    $m->cc($userEmail); // a copy for the person who asked
                }
                foreach ($built['to_emails'] as $i => $addr) {
                    $m->to($addr, $i === 0 ? $built['supplier'] : null);
                }
                foreach ($files as $f) {
                    $abs = Storage::disk('local')->path($f['path']);
                    if (is_file($abs)) {
                        $m->attach($abs, ['as' => $f['name'], 'mime' => $f['mime'] ?: 'application/octet-stream']);
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Compliance document request mail failed: '.$e->getMessage());
            $record->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);

            return response()->json(['success' => false, 'message' => 'Could not send: '.mb_substr($e->getMessage(), 0, 200).' (the mail is saved in the list below).'], 500);
        }

        $record->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Mail sent to '.($built['supplier'] ?: 'supplier').' ('.implode(', ', $built['to_emails']).') with '.count($files).' attachment'.(count($files) === 1 ? '' : 's').'.',
        ]);
    }

    /**
     * Writes the generated request form and the uploaded files under storage/app/compliance-doc-requests/{id}/.
     *
     * @return list<array{name:string,path:string,size:int,mime:string,generated:bool}>
     */
    private function storeAttachments(ComplianceDocRequest $record, array $built, Request $request): array
    {
        $disk = Storage::disk('local');
        $dir = 'compliance-doc-requests/'.$record->id;
        $out = [];

        $pdf = $this->requestPdf($built);
        if ($pdf !== null) {
            $path = $dir.'/'.$built['pdf_name'];
            $disk->put($path, $pdf);
            $out[] = ['name' => $built['pdf_name'], 'path' => $path, 'size' => strlen($pdf), 'mime' => 'application/pdf', 'generated' => true];
        }

        foreach ((array) $request->file('attachments', []) as $i => $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $orig = $file->getClientOriginalName();
            $name = trim(preg_replace('/[^A-Za-z0-9._ -]+/', '_', $orig), '. ') ?: 'attachment';
            $mime = (string) ($file->getMimeType() ?: 'application/octet-stream');
            $size = (int) $file->getSize();
            $stored = $file->storeAs($dir, ($i + 1).'-'.$name, 'local');
            if ($stored) {
                $out[] = ['name' => $name, 'path' => $stored, 'size' => $size, 'mime' => $mime, 'generated' => false];
            }
        }

        return $out;
    }

    /** The request form that is always attached (what we need, for which SKUs, product image). */
    private function requestPdf(array $b): ?string
    {
        try {
            $logoPath = public_path('images/5core_logo_tight.png');
            $logo = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath)) : '';

            $image = null;
            $rel = trim((string) ($b['image_path'] ?? ''));
            if ($rel !== '' && ! preg_match('#^https?://#i', $rel)) {
                $abs = public_path(ltrim($rel, '/'));
                $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
                if (is_file($abs) && filesize($abs) < 3 * 1024 * 1024 && in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
                    $image = 'data:image/'.($ext === 'jpg' ? 'jpeg' : $ext).';base64,'.base64_encode((string) file_get_contents($abs));
                }
            }

            return Pdf::loadView('purchase-master.compliance-doc-request-pdf', [
                'label' => $b['label'],
                'parent' => $b['parent'],
                'supplier' => $b['supplier'],
                'skus' => $b['skus'],
                'docs' => $b['docs'],
                'note' => $b['note'],
                'userName' => $b['from_name'],
                'userEmail' => $b['user_email'],
                'logo' => $logo,
                'image' => $image,
                'date' => now()->format('d M Y'),
            ])->setPaper('a4')->output();
        } catch (\Throwable $e) {
            Log::warning('Compliance request PDF could not be generated: '.$e->getMessage());

            return null;
        }
    }

    /** @return array<string, mixed>|JsonResponse */
    private function build(Request $request)
    {
        $data = $request->validate([
            'parent' => 'required|string|max:255',
            'field' => 'required|string|in:'.implode(',', ComplianceMasterBadgeCalculator::FIELD_KEYS),
        ]);
        $field = $data['field'];
        $label = self::FIELD_LABELS[$field] ?? ucfirst($field);
        $parent = $this->norm($data['parent']);

        $rows = app(CategoryController::class)->getComplianceMasterData($request)->getData(true)['data'] ?? [];
        $group = array_values(array_filter($rows, fn ($r) => $this->norm((string) ($r['Parent'] ?? '')) === $parent));
        if ($group === []) {
            return response()->json(['success' => false, 'message' => 'Parent "'.$data['parent'].'" was not found.'], 404);
        }

        $isParentRow = fn ($r) => str_contains(strtoupper((string) ($r['SKU'] ?? '')), 'PARENT');
        $children = array_values(array_filter($group, fn ($r) => ! $isParentRow($r)));
        $missing = array_values(array_filter($children, fn ($r) => ComplianceMasterBadgeCalculator::isFieldReq($r, $field)));
        $skuRows = $missing ?: $children;

        // Supplier: first SKU (missing ones first) that has a supplier; prefer one that also has an e-mail.
        $pool = array_merge($missing, $children, $group);
        $pick = null;
        foreach ($pool as $r) {
            if (trim((string) ($r['supplier'] ?? '')) !== '' && trim((string) ($r['supplier_email'] ?? '')) !== '') {
                $pick = $r;
                break;
            }
        }
        if ($pick === null) {
            foreach ($pool as $r) {
                if (trim((string) ($r['supplier'] ?? '')) !== '') {
                    $pick = $r;
                    break;
                }
            }
        }
        $supplier = trim((string) ($pick['supplier'] ?? ''));
        $toEmails = $this->emails((string) ($pick['supplier_email'] ?? ''));

        // Image: the parent row's picture, else the first SKU under it that has one.
        $imagePath = null;
        foreach (array_merge(array_filter($group, $isParentRow), $children) as $r) {
            if (! empty($r['image_path'])) {
                $imagePath = (string) $r['image_path'];
                break;
            }
        }
        $imageUrl = $imagePath ? (preg_match('#^https?://#i', $imagePath) ? $imagePath : url($imagePath)) : null;

        $user = Auth::user();
        $userName = trim((string) ($user->name ?? '')) ?: 'Team';
        $userEmail = trim((string) ($user->email ?? ''));
        $sender = app(ContainerQuoteRequestController::class)->sender();

        $docs = self::FIELD_DOCS[$field] ?? [$label.' compliance test report', $label.' compliance certificate'];
        $skus = array_map(fn ($r) => (string) $r['SKU'], $skuRows);
        $note = trim((string) $request->input('note', ''));

        $subject = $label.' compliance documents required - '.$data['parent'];
        $html = $this->html($supplier, $label, $data['parent'], $skus, $docs, $imageUrl, $note, $userName, $userEmail);

        return [
            'parent' => $data['parent'],
            'field' => $field,
            'label' => $label,
            'supplier' => $supplier,
            'to_emails' => $toEmails,
            'sender' => $sender,
            'from_name' => $userName,
            'user_email' => $userEmail,
            'subject' => $subject,
            'html' => $html,
            'image_url' => $imageUrl,
            'image_path' => $imagePath,
            'skus' => $skus,
            'docs' => $docs,
            'note' => $note,
            'pdf_name' => 'Document-Request-'.(preg_replace('/[^A-Za-z0-9_-]+/', '-', $data['parent'].'-'.$label) ?: 'request').'.pdf',
        ];
    }

    /** @param  array<string, mixed>  $b */
    private function publicView(array $b): array
    {
        return [
            'parent' => $b['parent'],
            'label' => $b['label'],
            'supplier' => $b['supplier'],
            'to' => $b['to_emails'],
            'from_name' => $b['from_name'],
            'from_email' => $b['sender']['email'],
            'reply_to' => $b['user_email'],
            'subject' => $b['subject'],
            'html' => $b['html'],
            'image_url' => $b['image_url'],
            'sku_count' => count($b['skus']),
            'auto_attachments' => [$b['pdf_name']],
        ];
    }

    private function html(string $supplier, string $label, string $parent, array $skus, array $docs, ?string $imageUrl, string $note, string $userName, string $userEmail): string
    {
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $maxList = 40;
        $skuItems = '';
        foreach (array_slice($skus, 0, $maxList) as $s) {
            $skuItems .= '<li>'.$e($s).'</li>';
        }
        if (count($skus) > $maxList) {
            $skuItems .= '<li>... and '.(count($skus) - $maxList).' more</li>';
        }
        $docItems = '';
        foreach ($docs as $d) {
            $docItems .= '<li>'.$e($d).'</li>';
        }

        $h = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#1f2937;line-height:1.5">';
        $h .= '<p>Dear '.$e($supplier !== '' ? $supplier : 'Supplier').',</p>';
        $h .= '<p>We need the <strong>'.$e($label).'</strong> compliance documents for the product below, so we can complete our listing compliance. Please send them at your earliest convenience.</p>';
        $h .= '<p><strong>Parent SKU:</strong> '.$e($parent).'</p>';
        if ($imageUrl) {
            $h .= '<p><img src="'.$e($imageUrl).'" alt="'.$e($parent).'" style="max-width:260px;max-height:260px;border:1px solid #e5e7eb;border-radius:6px"></p>';
        }
        if ($skus) {
            $h .= '<p><strong>SKUs ('.count($skus).'):</strong></p><ul>'.$skuItems.'</ul>';
        }
        $h .= '<p><strong>Documents required:</strong></p><ul>'.$docItems.'</ul>';
        if ($note !== '') {
            $h .= '<p>'.nl2br($e($note)).'</p>';
        }
        $h .= '<p>Please reply to this email with the files attached (PDF or clear images).</p>';
        $h .= '<p>Thank you,<br>'.$e($userName).($userEmail !== '' ? '<br>'.$e($userEmail) : '').'<br>5Core</p>';

        return $h.'</div>';
    }

    private function norm(string $s): string
    {
        $s = html_entity_decode(trim($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = str_replace("\u{00A0}", ' ', $s);

        return mb_strtoupper(preg_replace('/\s+/u', ' ', trim($s)));
    }

    /** @return list<string> */
    private function emails(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[;,\s]+/', $raw) ?: [] as $part) {
            $part = strtolower(trim($part));
            if ($part !== '' && filter_var($part, FILTER_VALIDATE_EMAIL)) {
                $out[] = $part;
            }
        }

        return array_values(array_unique($out));
    }
}
