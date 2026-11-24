<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ComparisonQuoteRequest;
use App\Models\Supplier;
use App\Services\ComparisonSheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * QR form on the Comparison page.
 *
 *  Part 1 (read only): the item image + the Critical specifications of the sheet.
 *  Part 2: the form a supplier fills in through a personal link; what they enter is written
 *  straight into that supplier's column of the comparison sheet.
 */
class ComparisonQuoteRequestController extends Controller
{
    public function __construct(
        private ComparisonSheetService $sheetService,
        private ComparisonController $comparison
    ) {
    }

    // ── Internal (5Core users) ────────────────────────────────────────────

    public function data(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessInternal()) {
            return $deny;
        }
        $sku = trim((string) $request->query('sku', ''));
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 422);
        }

        return response()->json(['success' => true] + $this->context($sku));
    }

    /**
     * Suppliers related to this SKU's Mfr category (for the Send column), with their QR form status.
     */
    public function suppliers(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessInternal()) {
            return $deny;
        }
        $sku = trim((string) $request->query('sku', ''));
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 422);
        }

        $sheet = $this->comparison->qrSheet($sku);
        $criticalCount = $sheet['cells'] === [] ? 0 : count($this->sheetService->criticalSpecRows($sheet['cells']));
        $onSheet = [];
        if ($sheet['cells'] !== []) {
            foreach ($this->sheetService->supplierColumns($sheet['cells']) as $sc) {
                $onSheet[$sc['key']] = true;
            }
        }
        $requests = ComparisonQuoteRequest::where('sku', $sku)->get()->keyBy('supplier_key');
        $found = $this->comparison->qrCategorySuppliers($sku);

        $suppliers = [];
        foreach ($found['suppliers'] as $s) {
            $key = $this->sheetService->supplierKey($s['name']);
            $req = $requests->get($key);
            $emails = $this->extractEmails($s['email']);
            $suppliers[] = [
                'id' => $s['id'],
                'name' => $s['name'],
                'company' => $s['company'],
                // Same source as the Suppliers page: its email wins; a past per-send override is only a fallback.
                'email' => ($emails[0] ?? '') ?: ($req?->supplier_email ?: ''),
                'platform_links' => $s['platform_links'] ?? [],
                'on_sheet' => isset($onSheet[$key]),
                'url' => $req ? route('comparison.qr.form', $req->token) : null,
                'sent_at' => $req?->sent_at?->format('d M Y, H:i'),
                'submitted_at' => $req?->submitted_at?->format('d M Y, H:i'),
                'last_error' => $req?->last_error,
            ];
        }

        return response()->json([
            'success' => true,
            'sku' => $sku,
            'has_sheet' => $sheet['cells'] !== [],
            'critical_count' => $criticalCount,
            'categories' => $found['categories'],
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Draft: the email a supplier will receive (before pressing Send).
     */
    public function draft(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessInternal()) {
            return $deny;
        }

        $data = $request->validate([
            'sku' => 'required|string|max:255',
            'supplier' => 'nullable|string|max:255',
            'email' => 'nullable|string|max:500',
        ]);
        $sku = trim($data['sku']);
        $supplier = trim((string) ($data['supplier'] ?? ''));

        $sheet = $this->comparison->qrSheet($sku);
        $rows = $sheet['cells'] === [] ? [] : $this->sheetService->criticalSpecRows($sheet['cells']);

        $emails = [];
        $url = '[personal-link-created-on-send]';
        if ($supplier !== '') {
            $key = $this->sheetService->supplierKey($supplier);
            $emails = $this->extractEmails((string) ($data['email'] ?? '')) ?: ($this->supplierEmailMap()[$key] ?? []);
            $req = ComparisonQuoteRequest::where('sku', $sku)->where('supplier_key', $key)->first();
            if ($req) {
                $url = route('comparison.qr.form', $req->token);
                $emails = $emails ?: $this->extractEmails((string) $req->supplier_email);
            }
        }

        $sender = $this->mailSender();
        $mail = $this->renderMail($sender, $supplier !== '' ? $supplier : '[Supplier name]', $sku, $this->comparison->qrProductImage($sku), $rows, $url);

        return response()->json([
            'success' => true,
            'from' => $sender['name'].' <'.$sender['email'].'>',
            'to' => implode(', ', $emails),
            'subject' => $mail['subject'],
            'html' => $mail['html'],
            'has_sheet' => $sheet['cells'] !== [],
            'critical_count' => count($rows),
        ]);
    }

    /**
     * Edit a supplier's email from the Send QR form modal. It is written to the Suppliers page record
     * (the single source of the email), so both places always show the same value.
     */
    public function saveSupplierEmail(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessInternal()) {
            return $deny;
        }
        $data = $request->validate([
            'supplier_id' => 'required|integer',
            'email' => 'nullable|string|max:500',
        ]);

        $supplier = Supplier::whereNull('deleted_at')->find($data['supplier_id']);
        if (! $supplier) {
            return response()->json(['success' => false, 'message' => 'Supplier not found.'], 404);
        }

        $raw = trim((string) ($data['email'] ?? ''));
        $emails = $this->extractEmails($raw);
        if ($raw !== '' && ! $emails) {
            return response()->json(['success' => false, 'message' => 'That is not a valid email address.'], 422);
        }

        $supplier->email = $emails ? implode(', ', $emails) : null;
        $supplier->save();

        // Quote requests remember the address they were sent to; keep them on the same value.
        if (Schema::hasColumn('comparison_quote_requests', 'supplier_email')) {
            ComparisonQuoteRequest::where('supplier_key', $this->sheetService->supplierKey((string) $supplier->name))
                ->whereNull('submitted_at')
                ->update(['supplier_email' => $emails[0] ?? null]);
        }

        return response()->json([
            'success' => true,
            'email' => $emails[0] ?? '',
            'message' => $emails ? 'Email saved to the Suppliers page.' : 'Email cleared.',
        ]);
    }

    /**
     * Create (and optionally email) a personal QR form link for each chosen supplier.
     */
    public function send(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessInternal()) {
            return $deny;
        }

        $data = $request->validate([
            'sku' => 'required|string|max:255',
            'suppliers' => 'required|array|min:1|max:50',
            'suppliers.*' => 'string|max:255',
            'emails' => 'nullable|array',
            'emails.*' => 'nullable|string|max:500',
            'send_email' => 'nullable|boolean',
            'add_missing' => 'nullable|boolean',
        ]);

        $sku = trim($data['sku']);
        $sendEmail = (bool) ($data['send_email'] ?? false);
        $addMissing = (bool) ($data['add_missing'] ?? false);
        $sheet = $this->comparison->qrSheet($sku);
        if ($sheet['cells'] === []) {
            return response()->json(['success' => false, 'message' => 'This product has no comparison sheet yet.'], 422);
        }
        if ($this->sheetService->criticalSpecRows($sheet['cells']) === []) {
            return response()->json(['success' => false, 'message' => 'Mark at least one spec row as Critical first.'], 422);
        }

        $columnsOf = function (array $cells): array {
            $out = [];
            foreach ($this->sheetService->supplierColumns($cells) as $sc) {
                $out[$sc['key']] = $sc;
            }

            return $out;
        };
        $columns = $columnsOf($sheet['cells']);
        $supplierEmails = $this->supplierEmailMap();
        $supplierCompanies = $this->supplierCompanyMap();
        $user = trim((string) (Auth::user()->name ?? '')) ?: 'System';

        $mailer = null;
        if ($sendEmail) {
            $mailer = $this->mailSender();
            if (str_starts_with($mailer['mailer'], 'quotes') && trim((string) config('mail.mailers.'.$mailer['mailer'].'.password')) === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'The mail server login is not connected yet: QUOTE_MAIL_PASSWORD is missing in .env.',
                ], 422);
            }
        }

        $image = $this->comparison->qrProductImage($sku);
        $criticalRows = $this->sheetService->criticalSpecRows($sheet['cells']);
        $results = [];
        foreach ($data['suppliers'] as $name) {
            $key = $this->sheetService->supplierKey($name);
            $col = $columns[$key] ?? null;
            if (! $col && $addMissing) {
                // Supplier from the Mfr category list that has no column yet: add one for their answers.
                if ($this->comparison->qrEnsureSupplierColumn($sku, trim($name, " \t\n\r\0\x0B\"'"), $supplierCompanies[$key] ?? '', $user)) {
                    $sheet = $this->comparison->qrSheet($sku);
                    $columns = $columnsOf($sheet['cells']);
                    $col = $columns[$key] ?? null;
                }
            }
            if (! $col) {
                $results[] = ['supplier' => $name, 'ok' => false, 'message' => 'Supplier column not found on the sheet.'];
                continue;
            }

            $typed = $this->extractEmails((string) ($data['emails'][$name] ?? ''));
            $emails = $typed ?: ($supplierEmails[$key] ?? []);

            $req = ComparisonQuoteRequest::firstOrNew(['sku' => $sku, 'supplier_key' => $key]);
            if (! $req->exists) {
                $req->token = Str::random(48);
            }
            $req->parent = $sheet['parent'] !== '' ? $sheet['parent'] : null;
            $req->supplier_name = $col['name'];
            $req->supplier_email = $emails[0] ?? $req->supplier_email;
            $req->save();

            $url = route('comparison.qr.form', $req->token);
            if (! $sendEmail) {
                $results[] = ['supplier' => $col['name'], 'ok' => true, 'url' => $url, 'message' => 'Link ready.'];
                continue;
            }
            if (! $emails) {
                $results[] = [
                    'supplier' => $col['name'],
                    'ok' => false,
                    'url' => $url,
                    'message' => 'No email address — type one in the Email box (or save it on the Suppliers page).',
                ];
                continue;
            }

            try {
                $this->mailLink($mailer, $emails, $col['name'], $sku, $image, $criticalRows, $url);
                $req->update(['sent_at' => now(), 'sent_by' => $user, 'last_error' => null]);
                $results[] = ['supplier' => $col['name'], 'ok' => true, 'url' => $url, 'message' => 'Sent to '.implode(', ', $emails)];
            } catch (\Throwable $e) {
                Log::warning('Comparison QR mail failed: '.$e->getMessage());
                $req->update(['last_error' => mb_substr($e->getMessage(), 0, 500)]);
                $results[] = [
                    'supplier' => $col['name'],
                    'ok' => false,
                    'url' => $url,
                    'message' => 'Could not send: '.mb_substr($e->getMessage(), 0, 200),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'results' => $results,
        ] + $this->context($sku));
    }

    // ── Public (supplier, no login) ───────────────────────────────────────

    public function publicForm(string $token)
    {
        $req = ComparisonQuoteRequest::where('token', $token)->first();
        if (! $req) {
            abort(404);
        }

        return view('purchase-master.comparison.qr-public-form', $this->publicPayload($req) + [
            'saved' => session('qr_saved', false),
        ]);
    }

    public function publicSubmit(Request $request, string $token)
    {
        $req = ComparisonQuoteRequest::where('token', $token)->first();
        if (! $req) {
            abort(404);
        }

        $payload = $this->publicPayload($req);
        if (! $payload['open']) {
            abort(410, 'This quotation request is closed.');
        }

        $request->validate([
            'labels' => 'required|array|max:100',
            'labels.*' => 'string|max:255',
            'values' => 'required|array|max:100',
            'values.*' => 'nullable|string|max:500',
        ]);

        $allowed = array_column($payload['rows'], 'label');
        $byLabel = [];
        foreach ((array) $request->input('labels') as $i => $label) {
            $value = trim((string) ($request->input('values')[$i] ?? ''));
            if (in_array($label, $allowed, true) && $value !== '') {
                $byLabel[$label] = $value;
            }
        }
        if ($byLabel === []) {
            return back()->withInput()->withErrors(['quote' => 'Please fill in at least one value.']);
        }

        $result = $this->comparison->qrApplyValues($req->sku, $req->supplier_name, $byLabel, $req->supplier_name.' (supplier)');
        if (! $result['ok']) {
            return back()->withInput()->withErrors(['quote' => $result['message']]);
        }

        $previous = json_decode((string) $req->submitted_values, true);
        $req->update([
            'submitted_at' => now(),
            'submitted_values' => json_encode(array_merge(is_array($previous) ? $previous : [], $byLabel), JSON_UNESCAPED_UNICODE),
        ]);

        return redirect()->route('comparison.qr.form', $token)->with('qr_saved', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function context(string $sku): array
    {
        $sheet = $this->comparison->qrSheet($sku);
        $cells = $sheet['cells'];
        $rows = $cells === [] ? [] : $this->sheetService->criticalSpecRows($cells);
        $columns = $cells === [] ? [] : $this->sheetService->supplierColumns($cells);

        $requests = ComparisonQuoteRequest::where('sku', $sku)->get()->keyBy('supplier_key');
        $emails = $this->supplierEmailMap();

        $suppliers = [];
        foreach ($columns as $sc) {
            $req = $requests->get($sc['key']);
            $suppliers[] = [
                'name' => $sc['name'],
                'email' => ($emails[$sc['key']][0] ?? '') ?: ($req?->supplier_email ?: ''),
                'url' => $req ? route('comparison.qr.form', $req->token) : null,
                'sent_at' => $req?->sent_at?->format('d M Y, H:i'),
                'sent_by' => $req?->sent_by,
                'submitted_at' => $req?->submitted_at?->format('d M Y, H:i'),
                'last_error' => $req?->last_error,
            ];
        }

        return [
            'sku' => $sku,
            'has_sheet' => $cells !== [],
            'image' => $this->comparison->qrProductImage($sku),
            'rows' => $rows,
            'suppliers' => $suppliers,
        ];
    }

    /** @return array<string, mixed> */
    private function publicPayload(ComparisonQuoteRequest $req): array
    {
        $sheet = $this->comparison->qrSheet($req->sku);
        $cells = $sheet['cells'];
        $rows = $cells === [] ? [] : $this->sheetService->criticalSpecRows($cells);

        $col = null;
        foreach ($cells === [] ? [] : $this->sheetService->supplierColumns($cells) as $sc) {
            if ($sc['key'] === $req->supplier_key) {
                $col = $sc['col'];
                break;
            }
        }

        $plain = function (string $v): string {
            $v = trim($v);

            return ($v === '' || str_starts_with($v, 'data:image/') || str_starts_with($v, '[cmp-photo:') || str_starts_with($v, '[embedded-image:')) ? '' : $v;
        };
        foreach ($rows as &$row) {
            $row['current'] = $col === null ? '' : $plain((string) ($cells[$row['row']][$col] ?? ''));
        }
        unset($row);

        return [
            'req' => $req,
            'image' => $this->comparison->qrProductImage($req->sku),
            'rows' => $rows,
            'open' => $col !== null && $rows !== [],
        ];
    }

    /** @return array<string, array<int, string>> supplier key => emails (from the Suppliers page) */
    private function supplierEmailMap(): array
    {
        $map = [];
        foreach (Supplier::whereNull('deleted_at')->get(['name', 'email']) as $s) {
            $key = $this->sheetService->supplierKey((string) $s->name);
            $emails = $this->extractEmails((string) $s->email);
            if ($key !== '' && $emails && ! isset($map[$key])) {
                $map[$key] = $emails;
            }
        }

        return $map;
    }

    /** @return array<string, string> supplier key => company (from the Suppliers page) */
    private function supplierCompanyMap(): array
    {
        $map = [];
        foreach (Supplier::whereNull('deleted_at')->get(['name', 'company']) as $s) {
            $key = $this->sheetService->supplierKey((string) $s->name);
            $company = trim((string) $s->company);
            if ($key !== '' && $company !== '' && ! isset($map[$key])) {
                $map[$key] = $company;
            }
        }

        return $map;
    }

    /** @return array<int, string> */
    private function extractEmails(string $raw): array
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

    /**
     * Subject + HTML body exactly as it goes out (used by both Send and the Draft preview).
     *
     * @param  array<int, array{label: string}>  $rows  Critical spec rows the supplier has to quote
     * @return array{subject: string, html: string}
     */
    private function renderMail(array $sender, string $toName, string $sku, ?string $image, array $rows, string $url): array
    {
        $specs = '';
        foreach ($rows as $r) {
            $specs .= '<li>'.e($r['label']).'</li>';
        }

        // Purchase description (Description button on the sheet, max 180 chars) goes above the greeting.
        $desc = '';
        if (Schema::hasTable('comparison_purchase_descriptions')) {
            $desc = trim((string) (DB::table('comparison_purchase_descriptions')
                ->whereRaw('TRIM(UPPER(sku)) = ?', [strtoupper(trim($sku))])
                ->value('description') ?? ''));
        }
        $header = '<p><strong>Subject: Product description required'.($desc !== '' ? ' - '.e($desc) : '').'</strong><br>'
            .'Required in Our Own brand Name</p>';

        $html = $header
            .'<p>Dear '.e($toName).',</p>'
            .'<p>Please share your best quotation for the following product:</p>'
            .'<p><strong>Product: '.e($sku).'</strong></p>'
            .($image ? '<p><img src="'.e($image).'" alt="Product" style="max-width:260px;border-radius:8px"></p>' : '')
            .($specs !== '' ? '<p>Kindly fill in these specifications:</p><ul>'.$specs.'</ul>' : '')
            .'<p>Please use your personal quotation form:<br><a href="'.e($url).'">'.e($url).'</a></p>'
            .'<p>Thanks &amp; regards,<br>'.e($sender['name']).'</p>';

        return ['subject' => 'Quotation request - '.$sku, 'html' => $html];
    }

    private function mailLink(array $sender, array $toEmails, string $toName, string $sku, ?string $image, array $rows, string $url): void
    {
        ['subject' => $subject, 'html' => $html] = $this->renderMail($sender, $toName, $sku, $image, $rows, $url);

        Mail::mailer($sender['mailer'])->html($html, function ($m) use ($sender, $toEmails, $toName, $subject) {
            $m->from($sender['email'], $sender['name'])->subject($subject);
            if (! empty($sender['reply_to'])) {
                $m->replyTo($sender['reply_to'], $sender['name']);
            }
            // Delivered through this mailbox (SMTP login) when the visible sender is the user's own address.
            if (! empty($sender['smtp_email']) && strcasecmp($sender['smtp_email'], $sender['email']) !== 0) {
                $m->sender($sender['smtp_email']);
            }
            foreach ($toEmails as $i => $addr) {
                $m->to($addr, $i === 0 ? $toName : null);
            }
        });
    }

    /**
     * Supplier names that were typed on a comparison sheet but are not on the Supplier page yet are
     * added there. A name that already exists (case / quote / spacing insensitive) is never duplicated.
     */
    public function ensureSuppliers(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessInternal()) {
            return $deny;
        }
        $data = $request->validate([
            'sku' => ['nullable', 'string', 'max:191'],
            'suppliers' => ['required', 'array', 'max:60'],
            'suppliers.*.name' => ['required', 'string', 'max:150'],
            'suppliers.*.company' => ['nullable', 'string', 'max:255'],
            'suppliers.*.link' => ['nullable', 'string', 'max:1000'],
        ]);

        $existing = [];
        Supplier::query()
            ->whereNull('deleted_at')
            ->whereNotNull('name')
            ->get(['id', 'name'])
            ->each(function ($s) use (&$existing) {
                $existing[$this->sheetService->supplierKey((string) $s->name)] = (int) $s->id;
            });

        $categoryIds = trim((string) ($data['sku'] ?? '')) !== ''
            ? $this->comparison->qrSupplierCategoryIds((string) $data['sku'])
            : [];

        $created = [];
        $already = [];
        foreach ($data['suppliers'] as $row) {
            $name = trim((string) $row['name'], " \t\n\r\0\x0B\"'");
            $name = trim((string) preg_replace('/\s+/u', ' ', $name));
            $key = $this->sheetService->supplierKey($name);
            if ($key === '' || mb_strlen($name) < 2) {
                continue;
            }
            if (isset($existing[$key])) {
                $already[] = $name;

                continue;
            }

            $link = trim((string) ($row['link'] ?? ''));
            $supplier = Supplier::create([
                'type' => 'Supplier',
                'category_id' => implode(',', $categoryIds),
                'name' => $name,
                'company' => trim((string) ($row['company'] ?? '')) ?: null,
                'alibaba' => $link !== '' && stripos($link, 'alibaba') !== false ? $link : null,
                'link_1688' => $link !== '' && stripos($link, '1688.com') !== false ? $link : null,
            ]);
            $existing[$key] = (int) $supplier->id;
            $created[] = ['id' => (int) $supplier->id, 'name' => $name];
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'existing' => $already,
        ]);
    }

    /** Every logged-in user may use the QR form / draft / send tools. */
    private function denyUnlessInternal(): ?JsonResponse
    {
        if (Auth::check()) {
            return null;
        }

        return response()->json(['success' => false, 'message' => 'Please log in.'], 401);
    }

    /**
     * Who the mail comes from: the logged-in user (their name + email). Replies go to the user's email.
     * A user with their own connected mailbox (config mail.quote_senders) sends straight from it. Everybody
     * else goes out through the shared sourcing mailbox (that is the SMTP login) with the user's name and
     * email on From / Reply-To; Gmail may show the shared address as the technical sender unless the user's
     * address is added there as a "Send mail as" alias, but replies always reach the user.
     *
     * @return array{mailer: string, email: string, name: string, reply_to: string, smtp_email: string}
     */
    private function mailSender(): array
    {
        $base = app(ContainerQuoteRequestController::class)->sender();
        $user = Auth::user();
        $userEmail = trim((string) ($user->email ?? ''));
        $userName = trim((string) ($user->name ?? ''));
        $validEmail = $userEmail !== '' && filter_var($userEmail, FILTER_VALIDATE_EMAIL);

        $personal = $base['mailer'] !== (string) config('mail.quote_mailer', 'quotes');
        if ($personal) {
            // Own mailbox: from that mailbox, replies to the same mailbox (or the user's login email).
            return $base + ['reply_to' => $validEmail ? $userEmail : $base['email'], 'smtp_email' => $base['email']];
        }
        if (! $validEmail) {
            return $base + ['reply_to' => $base['email'], 'smtp_email' => $base['email']];
        }

        return [
            'mailer' => $base['mailer'],
            'email' => $userEmail,
            'name' => $userName !== '' ? $userName : $userEmail,
            'reply_to' => $userEmail,
            'smtp_email' => $base['email'],
        ];
    }
}
