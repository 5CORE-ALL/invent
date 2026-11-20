<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PurchaseMaster\ContainerQuoteRequestController;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * "Complaints / Resolution" email to a supplier, built from the supplier's open QC / Packaging issues
 * (issues marked "Ignore - Not supplier fault" are left out).
 */
class QcIssuesComplaintController extends Controller
{
    /** Issues listed in the email / sent to the AI at most. */
    private const MAX_ISSUES = 60;

    /** Polite draft listing the supplier's issues, plus recipient / sender details. */
    public function draft(Request $request): JsonResponse
    {
        $supplier = trim((string) $request->input('supplier', ''));
        if ($supplier === '' || mb_strtoupper($supplier) === mb_strtoupper('(No Supplier)')) {
            return response()->json(['message' => 'Open a supplier first.'], 422);
        }

        $issues = $this->issuesFor($supplier, QcIssuesSupplierWiseController::periodKey($request));
        $sender = app(ContainerQuoteRequestController::class)->sender();
        $userName = trim((string) ($request->user()?->name ?? '')) ?: $sender['name'];

        $raw = (string) Supplier::query()->whereRaw('TRIM(UPPER(name)) = ?', [mb_strtoupper($supplier)])->value('email');

        return response()->json([
            'supplier' => $supplier,
            'to' => implode(', ', $this->splitEmails($raw)),
            'from' => $sender['email'],
            'subject' => 'Quality / packaging issues - request for resolution',
            'body' => $this->composeBody($supplier, $issues, $userName),
            'issue_count' => count($issues),
            'ai_available' => $this->aiKey() !== '',
        ]);
    }

    /** Rewrites the current draft following the user's prompt (the pen icon). */
    public function refresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier' => 'required|string|max:191',
            'prompt' => 'required|string|max:1000',
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:20000',
        ]);

        $key = $this->aiKey();
        if ($key === '') {
            return response()->json(['message' => 'AI is not configured. Add ANTHROPIC_API_KEY to .env, or edit the draft manually.'], 422);
        }

        $supplier = trim($validated['supplier']);
        $issues = $this->issuesFor($supplier, QcIssuesSupplierWiseController::periodKey($request));
        $userName = trim((string) ($request->user()?->name ?? '')) ?: '5Core';

        $facts = $this->composeBody($supplier, $issues, $userName);
        $current = trim((string) ($validated['body'] ?? '')) !== '' ? $validated['body'] : $facts;

        $system = 'You write business emails for 5Core (an e-commerce company) to its suppliers. '
            .'The tone is polite, professional and constructive; never accusatory. The goal is to summarise quality / packaging issues '
            .'and ask the supplier for a resolution. Use only the facts supplied: do not invent SKUs, dates, quantities or promises. '
            .'Reply with ONLY a JSON object {"subject": "...", "body": "..."} (body is plain text with \n line breaks).';

        $user = "Supplier: {$supplier}\n\nIssue facts (reference):\n{$facts}\n\nCurrent draft:\nSubject: ".($validated['subject'] ?? '')."\n{$current}\n\n"
            ."Instruction from the user (apply it when rewriting the draft):\n{$validated['prompt']}";

        try {
            $res = Http::timeout(60)
                ->withHeaders([
                    'x-api-key' => $key,
                    'anthropic-version' => (string) config('services.anthropic.version', '2023-06-01'),
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => (string) config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
                    'max_tokens' => 2000,
                    'temperature' => 0.4,
                    'system' => $system,
                    'messages' => [['role' => 'user', 'content' => $user]],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Complaint email AI exception', ['msg' => $e->getMessage()]);

            return response()->json(['message' => 'AI request failed: '.$e->getMessage()], 502);
        }

        if (! $res->successful()) {
            Log::warning('Complaint email AI HTTP '.$res->status(), ['body' => mb_substr($res->body(), 0, 500)]);

            return response()->json(['message' => 'AI request failed (HTTP '.$res->status().').'], 502);
        }

        $text = trim((string) $res->json('content.0.text'));
        $parsed = null;
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $parsed = json_decode($m[0], true);
        }
        if (! is_array($parsed) || trim((string) ($parsed['body'] ?? '')) === '') {
            return response()->json(['message' => 'AI returned an unexpected reply. Try again.'], 502);
        }

        return response()->json([
            'subject' => trim((string) ($parsed['subject'] ?? '')) ?: ($validated['subject'] ?? ''),
            'body' => trim((string) $parsed['body']),
        ]);
    }

    /** Sends the (edited) email from the app. */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to' => 'required|string|max:1000',
            'subject' => 'required|string|max:255',
            'body' => 'required|string|max:20000',
            'supplier' => 'nullable|string|max:191',
        ]);

        $to = $this->splitEmails($validated['to']);
        if ($to === []) {
            return response()->json(['message' => 'Enter a valid supplier email address.', 'errors' => ['to' => ['Enter a valid supplier email address.']]], 422);
        }

        $sender = app(ContainerQuoteRequestController::class)->sender();
        $html = nl2br(e($validated['body']));
        $user = $request->user();

        try {
            Mail::mailer($sender['mailer'])->html($html, function ($m) use ($sender, $to, $validated, $user) {
                $m->from($sender['email'], $sender['name'])->subject($validated['subject']);
                $replyTo = trim((string) ($user?->email ?? ''));
                if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                    $m->replyTo($replyTo, $user?->name);
                }
                foreach ($to as $addr) {
                    $m->to($addr);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Complaint email failed: '.$e->getMessage());

            return response()->json(['message' => 'Could not send the email: '.$e->getMessage()], 500);
        }

        Log::info('Supplier complaint email sent', ['supplier' => $validated['supplier'] ?? null, 'to' => $to, 'from' => $sender['email'], 'by' => $user?->id]);

        return response()->json(['success' => true, 'message' => 'Email sent to '.implode(', ', $to).'.']);
    }

    /**
     * Open, counted issues (not ignored) of one supplier for the period.
     *
     * @return list<array<string, mixed>>
     */
    private function issuesFor(string $supplier, string $period): array
    {
        $groups = app(QcIssuesSupplierWiseController::class)->groupsBySupplier(QcIssuesSupplierWiseController::PERIODS[$period]);
        $group = $groups[mb_strtoupper($supplier)] ?? null;
        if (! $group) {
            return [];
        }

        return array_values(array_filter($group['issues'], static fn ($i) => empty($i['ignored'])));
    }

    /** Polite plain-text email listing the issues grouped by SKU. */
    private function composeBody(string $supplier, array $issues, string $userName): string
    {
        $lines = [];
        $lines[] = 'Dear '.$supplier.',';
        $lines[] = '';
        $lines[] = 'I hope you are doing well. Thank you for your continued support.';
        $lines[] = '';

        if ($issues === []) {
            $lines[] = 'We are writing to follow up on the quality of the items supplied by you. We currently have no open issues recorded, but would like to stay in touch on this.';
        } else {
            $lines[] = 'We would like to bring the following quality / packaging issues, reported on items supplied by you, to your attention:';
            $lines[] = '';

            $bySku = [];
            foreach (array_slice($issues, 0, self::MAX_ISSUES) as $i) {
                $bySku[$i['sku'] !== '' ? $i['sku'] : '(no SKU)'][] = $i;
            }
            $n = 1;
            foreach ($bySku as $sku => $list) {
                $lines[] = $n++.'. SKU: '.$sku;
                foreach ($list as $i) {
                    $what = trim(implode(' - ', array_filter([$i['what_happened'] ?? '', $i['issue'] ?? ''])));
                    $line = '   - '.($i['date'] ?? '').($i['department'] ? ' ['.$i['department'].']' : '').': '.($what !== '' ? $what : 'Issue reported');
                    if (trim((string) ($i['issue_remark'] ?? '')) !== '') {
                        $line .= ' ('.trim($i['issue_remark']).')';
                    }
                    $lines[] = $line;
                }
                $lines[] = '';
            }
            if (count($issues) > self::MAX_ISSUES) {
                $lines[] = '...and '.(count($issues) - self::MAX_ISSUES).' more; we can share the full list on request.';
                $lines[] = '';
            }
            $lines[] = 'We understand that issues can happen, and we would like to work with you to resolve them.';
        }

        $lines[] = 'We would be grateful if you could:';
        $lines[] = '  1. Review the above and let us know the root cause;';
        $lines[] = '  2. Share the corrective action you will take to prevent a repeat;';
        $lines[] = '  3. Advise how you propose to resolve the affected orders (replacement, credit or refund), with a timeline.';
        $lines[] = '';
        $lines[] = 'Please let us know if you need photos or further details. We look forward to your reply.';
        $lines[] = '';
        $lines[] = 'Thank you for your understanding and cooperation.';
        $lines[] = '';
        $lines[] = 'Best regards,';
        $lines[] = $userName;
        $lines[] = '5Core';

        return implode("\n", $lines);
    }

    private function aiKey(): string
    {
        return trim((string) config('services.anthropic.key'));
    }

    /** @return list<string> */
    private function splitEmails(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $part) {
            $part = trim($part, " \t\n\r\0\x0B<>");
            if ($part !== '' && filter_var($part, FILTER_VALIDATE_EMAIL)) {
                $out[strtolower($part)] = $part;
            }
        }

        return array_values($out);
    }
}
