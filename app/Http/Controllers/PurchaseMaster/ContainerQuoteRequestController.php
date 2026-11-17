<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ContainerQuotation;
use App\Models\ContainerQuoteReceived;
use App\Models\ContainerQuoteRequest;
use App\Models\Supplier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Forwarder quote requests for the Quotations Container page.
 *
 *  - 5Core users manage forwarders, edit the email text, and send a separate
 *    email (with a personal secure link) to every selected forwarder.
 *  - The forwarder opens the link (no login), sees the container details
 *    read-only, fills in the quote, and it is saved straight into the app.
 */
class ContainerQuoteRequestController extends Controller
{
    private const FORWARDER_TYPE = 'Forwarders';

    private const TYPES = ['Supplier', 'Forwarders', 'Photographer'];

    private const SENDER_EMAIL = 'sourcing1@5core.com';

    private const SENDER_NAME = '5Core Sourcing';

    private const DEFAULT_SUBJECT = 'Quotation request - {container} ({loading_port} to {discharge_port})';

    private const DEFAULT_BODY = "Dear {forwarder},\n\n"
        ."Please share your best all-inclusive quotation for the following shipment:\n\n"
        ."Container: {container}\n"
        ."Container size: {container_size}\n"
        ."Pickup address: {pickup_address}\n"
        ."Loading port: {loading_port}\n"
        ."Via port: {via_port}\n"
        ."Discharge port: {discharge_port}\n"
        ."Gross weight: {gross_weight}\n\n"
        ."Kindly fill in your quote (shipping line, estimated sailing date, pickup charges and freight) using this secure form:\n"
        ."{link}\n\n"
        ."Thanks & regards,\n"
        .'5Core Sourcing Team';

    private const PLACEHOLDERS = [
        'forwarder', 'container', 'container_size', 'pickup_address', 'loading_port',
        'via_port', 'discharge_port', 'gross_weight', 'link',
    ];

    // ── Internal (5Core users) ────────────────────────────────────────────

    /**
     * Forwarder pool for the "Forwarder" button. Names come from the Suppliers page
     * (same `suppliers` table), filtered by type — Forwarders by default.
     */
    public function forwarders(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessInternal()) {
            return $deny;
        }

        $type = trim((string) $request->query('type', self::FORWARDER_TYPE));

        return response()->json($this->forwarderPayload($type));
    }

    /** "+ Add" — create a new party on the Suppliers page data (default type Forwarders). */
    public function saveForwarder(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessInternal()) {
            return $deny;
        }

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'company' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:100',
            'type' => 'required|string|in:'.implode(',', self::TYPES),
        ]);

        $name = trim($data['name']);
        $exists = Supplier::whereNull('deleted_at')
            ->where('type', $data['type'])->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists();
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A '.$data['type'].' with this name already exists.',
            ], 422);
        }

        $supplier = new Supplier();
        $supplier->type = $data['type'];
        $supplier->category_id = '';
        $supplier->name = $name;
        $supplier->company = ! empty($data['company']) ? trim($data['company']) : null;
        $supplier->email = ! empty($data['email']) ? trim($data['email']) : null;
        try {
            $supplier->save();
        } catch (\Illuminate\Database\QueryException $e) {
            // Some databases (e.g. local dumps) have an id column without AUTO_INCREMENT.
            if (! str_contains($e->getMessage(), "'id' doesn't have a default value")) {
                throw $e;
            }
            $newId = (int) Supplier::max('id') + 1;
            $supplier->id = $newId;
            $supplier->save();
            $supplier->id = $newId; // lastInsertId is 0 without AUTO_INCREMENT
        }

        // Newly added party is selected straight away.
        $ids = $this->selectedIds();
        $ids[] = (int) $supplier->id;
        $this->storeSelectedIds($ids);

        return response()->json($this->forwarderPayload($data['type']));
    }

    /** Save which forwarders are selected (used by the Send button). */
    public function saveSelection(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessInternal()) {
            return $deny;
        }

        $data = $request->validate([
            'ids' => 'nullable|array|max:500',
            'ids.*' => 'integer',
        ]);

        $this->storeSelectedIds($data['ids'] ?? []);

        return response()->json(['success' => true, 'selected' => $this->selectedIds(), 'selected_list' => $this->selectedList()]);
    }

    /** Selected forwarders (resolved), for the Send modal. */
    public function selectedForwarders(): JsonResponse
    {
        if ($deny = $this->denyUnlessInternal()) {
            return $deny;
        }

        return response()->json(['success' => true, 'selected_list' => $this->selectedList()]);
    }
    public function getText(): JsonResponse
    {
        if ($deny = $this->denyUnlessInternal()) {
            return $deny;
        }

        return response()->json(['success' => true] + $this->mailText() + [
            'placeholders' => self::PLACEHOLDERS,
        ]);
    }

    public function saveText(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessInternal()) {
            return $deny;
        }

        if ($request->boolean('reset')) {
            DB::table('container_quote_settings')->whereIn('key', ['mail_subject', 'mail_body'])->delete();

            return response()->json(['success' => true] + $this->mailText());
        }

        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string|max:10000',
        ]);

        foreach (['mail_subject' => $data['subject'], 'mail_body' => $data['body']] as $key => $value) {
            DB::table('container_quote_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        return response()->json(['success' => true] + $this->mailText());
    }

    /**
     * Data for the combined "QR Form" preview: the container's Q details plus
     * every request sent for it and the quotes that came back.
     */
    public function formData(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessInternal()) {
            return $deny;
        }

        $container = trim((string) $request->query('container', ''));

        return response()->json([
            'success' => true,
            'container_number' => $container,
            'details' => $this->containerDetails($container),
            'requests' => $this->requestRows($container, true),
        ]);
    }

    /**
     * Preview of the email each selected forwarder will receive (before pressing Send).
     * Uses the saved email text + the container's Q details.
     */
    public function preview(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessInternal()) {
            return $deny;
        }

        $data = $request->validate([
            'container' => 'required|string|max:255',
            'forwarder_id' => 'nullable|integer',
        ]);

        $container = trim($data['container']);
        $fwd = ! empty($data['forwarder_id']) ? Supplier::whereNull('deleted_at')->find($data['forwarder_id']) : null;
        if (! empty($data['forwarder_id']) && ! $fwd) {
            return response()->json(['success' => false, 'message' => 'Forwarder not found.'], 404);
        }
        // No forwarder chosen yet: show the draft with a generic name.
        $fwdName = $fwd ? $fwd->name : '[Forwarder name]';

        $emails = $fwd ? $this->extractEmails((string) $fwd->email) : [];
        $existing = ContainerQuoteRequest::where('container_number', $container)
            ->where('forwarder_email', $emails[0] ?? '')->first();
        // A personal link is only created on Send; show the existing one if this forwarder already has it.
        $url = $existing
            ? route('container.quote.form', $existing->token)
            : str_replace('PERSONALLINKPERSONALLINK', '[personal-link-created-on-send]', route('container.quote.form', 'PERSONALLINKPERSONALLINK'));

        $mail = $this->renderMail($fwdName, $container, $this->containerDetails($container), $this->mailText(), $url);

        return response()->json([
            'success' => true,
            'from' => self::SENDER_NAME.' <'.self::SENDER_EMAIL.'>',
            'to' => $emails ? implode(', ', $emails) : '',
            'forwarder' => $fwdName,
            'subject' => $mail['subject'],
            'html' => $mail['html'].'<br><br><small>Attached: quotation form (PDF) with the container details filled in.</small>',
            'attachment' => 'Quotation-Form-'.(preg_replace('/[^A-Za-z0-9_-]+/', '-', $container) ?: 'container').'.pdf',
            'has_details' => (bool) ContainerQuotation::where('container_number', $container)->exists(),
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessInternal()) {
            return $deny;
        }

        $data = $request->validate([
            'container_number' => 'required|string|max:255',
            'forwarder_ids' => 'required|array|min:1|max:50',
            'forwarder_ids.*' => 'integer',
        ]);

        $container = trim($data['container_number']);
        if (! in_array($container, app(ContainerQuotationController::class)->readyToShipContainers(), true)) {
            return response()->json(['success' => false, 'message' => 'Unknown container.'], 422);
        }

        $quotation = ContainerQuotation::where('container_number', $container)->first();
        if (! $quotation) {
            return response()->json([
                'success' => false,
                'message' => 'Please fill in the Q details for this container before sending.',
            ], 422);
        }

        $mailer = (string) config('mail.quote_mailer', 'quotes');
        if ($mailer === 'quotes' && trim((string) config('mail.mailers.quotes.password')) === '') {
            return response()->json([
                'success' => false,
                'message' => 'The sourcing1@5core.com mailbox is not connected yet: QUOTE_MAIL_PASSWORD is missing in .env.',
            ], 422);
        }

        $text = $this->mailText();
        $details = $this->containerDetails($container);
        $user = trim((string) (Auth::user()->name ?? '')) ?: 'System';
        $results = [];

        foreach (Supplier::whereNull('deleted_at')->whereIn('id', $data['forwarder_ids'])->orderBy('name')->get() as $fwd) {
            $emails = $this->extractEmails((string) $fwd->email);
            if (! $emails) {
                $results[] = [
                    'forwarder' => $fwd->name,
                    'email' => '',
                    'ok' => false,
                    'message' => 'No email address saved for this forwarder — add it on the Suppliers page.',
                ];
                continue;
            }

            $req = ContainerQuoteRequest::firstOrNew([
                'container_number' => $container,
                'forwarder_email' => $emails[0],
            ]);
            if (! $req->exists) {
                $req->token = Str::random(48);
            }
            $req->forwarder_id = $fwd->id;
            $req->forwarder_name = $fwd->name;
            $req->save();

            $url = route('container.quote.form', $req->token);

            try {
                $this->sendOne($mailer, $emails, $fwd->name, $container, $details, $text, $url);
                $req->update(['sent_at' => now(), 'sent_by' => $user, 'last_error' => null]);
                $results[] = ['forwarder' => $fwd->name, 'email' => implode(', ', $emails), 'ok' => true];
            } catch (\Throwable $e) {
                Log::warning('Quote request mail failed: '.$e->getMessage());
                $req->update(['last_error' => mb_substr($e->getMessage(), 0, 500)]);
                $results[] = [
                    'forwarder' => $fwd->name,
                    'email' => implode(', ', $emails),
                    'ok' => false,
                    'message' => 'Could not send: '.mb_substr($e->getMessage(), 0, 200),
                ];
            }
        }
        return response()->json([
            'success' => true,
            'results' => $results,
            'requests' => $this->requestRows($container, true),
        ]);
    }

    // ── Public (forwarder, no login) ──────────────────────────────────────

    public function publicForm(string $token)
    {
        $req = ContainerQuoteRequest::where('token', $token)->first();
        if (! $req) {
            abort(404);
        }

        $open = in_array($req->container_number, app(ContainerQuotationController::class)->readyToShipContainers(), true);
        $existing = ContainerQuoteReceived::where('request_id', $req->id)->first();

        return view('purchase-master.container_quotations.public-form', [
            'req' => $req,
            'details' => $this->containerDetails($req->container_number),
            'existing' => $existing,
            'open' => $open,
            'saved' => session('quote_saved', false),
        ]);
    }

    public function publicSubmit(Request $request, string $token)
    {
        $req = ContainerQuoteRequest::where('token', $token)->first();
        if (! $req) {
            abort(404);
        }
        if (! in_array($req->container_number, app(ContainerQuotationController::class)->readyToShipContainers(), true)) {
            abort(410, 'This quotation request is closed.');
        }

        $data = $request->validate([
            'shipping_line' => 'required|string|max:255',
            'est_dt_sail' => 'required|date',
            'pickup_charges' => 'required|numeric|min:0|max:99999999',
            'freight' => 'required|numeric|min:0|max:99999999',
            'total' => 'required|numeric|min:0|max:99999999',
        ]);

        $pickup = (float) $data['pickup_charges'];
        $freight = (float) $data['freight'];
        $total = round((float) $data['total'], 2);
        if ($total < round($pickup + $freight, 2)) {
            return back()->withInput()->withErrors([
                'total' => 'Total must include all charges (at least Pickup Charges + Freight).',
            ]);
        }
        $line = trim((string) $data['shipping_line']);
        $sail = trim((string) $data['est_dt_sail']);

        $values = [
            'forwarder' => $req->forwarder_name,
            'shipping_line' => $line === '' ? null : $line,
            'est_dt_sail' => $sail === '' ? null : date('Y-m-d', strtotime($sail)),
            'pickup_charges' => $pickup,
            'freight' => $freight,
            'total' => $total,
            'source' => 'forwarder',
            'updated_by' => $req->forwarder_name.' (forwarder)',
        ];

        DB::transaction(function () use ($req, $values) {
            $row = ContainerQuoteReceived::where('request_id', $req->id)->first();
            if ($row) {
                $this->flagChanges($req, $row, $values);
                $row->update($values);
            } else {
                ContainerQuoteReceived::create($values + [
                    'container_number' => $req->container_number,
                    'request_id' => $req->id,
                ]);
            }
            $req->update(['submitted_at' => now()]);
        });

        return redirect()->route('container.quote.form', $token)->with('quote_saved', true);
    }

    /**
     * Acknowledge a forwarder's changed quote (clears the red triangle).
     */
    public function acknowledgeChange(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessInternal()) {
            return $deny;
        }

        $data = $request->validate(['request_id' => 'required|integer']);
        $req = ContainerQuoteRequest::find($data['request_id']);
        if (! $req) {
            return response()->json(['success' => false, 'message' => 'Request not found.'], 404);
        }

        $req->update([
            'quote_changed_at' => null,
            'quote_changes' => null,
            'change_ack_by' => trim((string) (Auth::user()->name ?? '')) ?: 'System',
            'change_ack_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'requests' => $this->requestRows($req->container_number, true),
        ]);
    }

    /**
     * When a forwarder re-submits a quote that differs from what was saved, record the
     * difference so 5Core sees a red alert. Unacknowledged changes accumulate: the
     * original "old" value is kept and the newest "new" value wins.
     */
    private function flagChanges(ContainerQuoteRequest $req, ContainerQuoteReceived $row, array $values): void
    {
        $labels = [
            'shipping_line' => 'Shipping Line',
            'est_dt_sail' => 'Est Dt Sail',
            'pickup_charges' => 'Pickup Charges',
            'freight' => 'Freight',
            'total' => 'Total',
        ];

        $pending = [];
        if ($req->quote_changed_at && $req->quote_changes) {
            $decoded = json_decode((string) $req->quote_changes, true);
            $pending = is_array($decoded) ? $decoded : [];
        }

        $changes = $pending;
        foreach ($labels as $field => $label) {
            $old = $row->{$field};
            $new = $values[$field] ?? null;
            $oldNorm = $this->normalizeValue($field, $old);
            $newNorm = $this->normalizeValue($field, $new);

            $original = array_key_exists($field, $pending) ? $pending[$field]['old'] : $oldNorm;
            if ($original === $newNorm) {
                unset($changes[$field]);
                continue;
            }
            if ($oldNorm !== $newNorm || isset($pending[$field])) {
                $changes[$field] = ['label' => $label, 'old' => $original, 'new' => $newNorm];
            }
        }

        if ($changes) {
            $req->quote_changes = json_encode($changes, JSON_UNESCAPED_UNICODE);
            $req->quote_changed_at = now();
        } else {
            $req->quote_changes = null;
            $req->quote_changed_at = null;
        }
        $req->save();
    }

    private function normalizeValue(string $field, $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        if (in_array($field, ['pickup_charges', 'freight', 'total'], true)) {
            return number_format((float) $value, 2, '.', '');
        }
        if ($field === 'est_dt_sail') {
            return substr((string) $value, 0, 10);
        }

        return trim((string) $value);
    }
    // ── Helpers ───────────────────────────────────────────────────────────

    private function denyUnlessInternal(): ?JsonResponse
    {
        $user = Auth::user();
        if ($user && method_exists($user, 'is5CoreMember') && $user->is5CoreMember()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Only 5Core users can do this.',
        ], 403);
    }

    /** @return array<int, int> */
    private function selectedIds(): array
    {
        $raw = DB::table('container_quote_settings')->where('key', 'selected_forwarders')->value('value');
        $ids = json_decode((string) $raw, true);

        return is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
    }

    private function storeSelectedIds(array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        DB::table('container_quote_settings')->updateOrInsert(
            ['key' => 'selected_forwarders'],
            ['value' => json_encode($ids), 'updated_at' => now(), 'created_at' => now()]
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function partyRows($query): array
    {
        return $query->orderBy('name')->get(['id', 'name', 'company', 'email', 'type'])->map(fn ($s) => [
            'id' => (int) $s->id,
            'name' => $s->name,
            'company' => $s->company,
            'email' => $s->email,
            'has_email' => count($this->extractEmails((string) $s->email)) > 0,
            'type' => $s->type,
        ])->all();
    }

    private function forwarderPayload(string $type): array
    {
        $type = in_array($type, self::TYPES, true) ? $type : self::FORWARDER_TYPE;

        return [
            'success' => true,
            'type' => $type,
            'types' => self::TYPES,
            'forwarders' => $this->partyRows(Supplier::whereNull('deleted_at')->where('type', $type)),
            'selected' => $this->selectedIds(),
            'selected_list' => $this->selectedList(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function selectedList(): array
    {
        $ids = $this->selectedIds();
        if (! $ids) {
            return [];
        }

        return $this->partyRows(Supplier::whereNull('deleted_at')->whereIn('id', $ids));
    }

    /** @return array<int, string> valid email addresses found in a free-text field */
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
    /** @return array{subject: string, body: string} */
    private function mailText(): array
    {
        $rows = DB::table('container_quote_settings')
            ->whereIn('key', ['mail_subject', 'mail_body'])->pluck('value', 'key');

        return [
            'subject' => (string) ($rows['mail_subject'] ?? self::DEFAULT_SUBJECT),
            'body' => (string) ($rows['mail_body'] ?? self::DEFAULT_BODY),
        ];
    }

    /** Q details shown (read-only) on the combined form. Supplier is internal and not shared. */
    private function containerDetails(string $container): array
    {
        $q = ContainerQuotation::where('container_number', $container)->first();

        return [
            'container_number' => $container,
            'has_details' => (bool) $q,
            'container_size' => $q->container_size ?? null,
            'pickup_address' => $q->pickup_address ?? null,
            'loading_port' => $q->loading_port ?? null,
            'via_port' => $q->via_port ?? null,
            'discharge_port' => $q->discharge_port ?? null,
            'gross_weight' => $q->gross_weight ?? null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function requestRows(string $container, bool $withLinks): array
    {
        $quotes = ContainerQuoteReceived::where('container_number', $container)
            ->whereNotNull('request_id')->get()->keyBy('request_id');

        return ContainerQuoteRequest::where('container_number', $container)
            ->orderBy('id')->get()->map(function ($r) use ($quotes, $withLinks) {
                $q = $quotes->get($r->id);

                return [
                    'id' => $r->id,
                    'forwarder' => $r->forwarder_name,
                    'email' => $r->forwarder_email,
                    'sent_at' => $r->sent_at ? $r->sent_at->format('d M Y, H:i') : null,
                    'sent_by' => $r->sent_by,
                    'submitted_at' => $r->submitted_at ? $r->submitted_at->format('d M Y, H:i') : null,
                    'last_error' => $r->last_error,
                    'total' => $q && $q->total !== null ? (float) $q->total : null,
                    'changed' => (bool) $r->quote_changed_at,
                    'changed_at' => $r->quote_changed_at ? $r->quote_changed_at->format('d M Y, H:i') : null,
                    'changes' => $r->quote_changed_at && $r->quote_changes
                        ? array_values((array) json_decode((string) $r->quote_changes, true)) : [],
                    'url' => $withLinks ? route('container.quote.form', $r->token) : null,
                ];
            })->values()->all();
    }

    /**
     * Render the subject + HTML body exactly as it goes out (used by both send and preview).
     *
     * @return array{subject: string, html: string}
     */
    private function renderMail(string $toName, string $container, array $d, array $text, string $url): array
    {
        $vals = [
            'forwarder' => $toName,
            'container' => $container,
            'container_size' => $d['container_size'],
            'pickup_address' => $d['pickup_address'],
            'loading_port' => $d['loading_port'],
            'via_port' => $d['via_port'],
            'discharge_port' => $d['discharge_port'],
            'gross_weight' => $d['gross_weight'],
            'link' => $url,
        ];
        $map = [];
        foreach ($vals as $k => $v) {
            $map['{'.$k.'}'] = trim((string) $v) === '' ? '-' : trim((string) $v);
        }

        $subject = strtr($text['subject'], $map);
        $body = strtr($text['body'], $map);

        $html = nl2br(e($body));
        $html = str_replace(e($url), '<a href="'.e($url).'">'.e($url).'</a>', $html);

        return ['subject' => $subject, 'html' => $html];
    }

    private function sendOne(string $mailer, array $toEmails, string $toName, string $container, array $d, array $text, string $url): void
    {
        ['subject' => $subject, 'html' => $html] = $this->renderMail($toName, $container, $d, $text, $url);

        // The quotation form (container details filled in from our Quotations page) goes along as a PDF.
        $pdf = null;
        $filename = 'Quotation-Form-'.(preg_replace('/[^A-Za-z0-9_-]+/', '-', $container) ?: 'container').'.pdf';
        try {
            $logoPath = public_path('images/5core_logo_tight.png');
            $logo = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath)) : '';
            $pdf = Pdf::loadView('purchase-master.container_quotations.form-pdf', [
                'container' => $container,
                'forwarder' => $toName,
                'd' => $d,
                'url' => $url,
                'logo' => $logo,
                'date' => now()->format('d M Y'),
            ])->setPaper('a4')->output();
            $html .= '<br><br><small>Attached: quotation form (PDF) with the container details filled in.</small>';
        } catch (\Throwable $e) {
            Log::warning('Quote form PDF could not be generated: '.$e->getMessage());
            $pdf = null;
        }

        Mail::mailer($mailer)->html($html, function ($m) use ($toEmails, $toName, $subject, $pdf, $filename) {
            $m->from(self::SENDER_EMAIL, self::SENDER_NAME)->subject($subject);
            if ($pdf !== null) {
                $m->attachData($pdf, $filename, ['mime' => 'application/pdf']);
            }
            foreach ($toEmails as $i => $addr) {
                $m->to($addr, $i === 0 ? $toName : null);
            }
        });
    }
}