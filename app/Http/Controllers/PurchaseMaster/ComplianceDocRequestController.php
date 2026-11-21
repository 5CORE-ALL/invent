<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Support\Badges\ComplianceMasterBadgeCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

    /** POST: send the e-mail to the parent's supplier. */
    public function send(Request $request): JsonResponse
    {
        $request->validate(['note' => 'nullable|string|max:2000']);
        $built = $this->build($request);
        if ($built instanceof JsonResponse) {
            return $built;
        }
        if ($built['to_emails'] === []) {
            return response()->json([
                'success' => false,
                'message' => 'No email address saved for supplier "'.($built['supplier'] ?: 'unknown').'". Add it on the Suppliers page first.',
            ], 422);
        }

        $sender = $built['sender'];
        if (str_starts_with($sender['mailer'], 'quotes') && trim((string) config('mail.mailers.'.$sender['mailer'].'.password')) === '') {
            return response()->json([
                'success' => false,
                'message' => 'The sending mailbox is not connected yet (mail password is missing in .env).',
            ], 422);
        }

        $user = Auth::user();
        try {
            Mail::mailer($sender['mailer'])->html($built['html'], function ($m) use ($built, $sender, $user) {
                $m->from($sender['email'], $built['from_name'])->subject($built['subject']);
                $userEmail = trim((string) ($user->email ?? ''));
                if ($userEmail !== '' && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                    $m->replyTo($userEmail, $built['from_name']);
                    $m->cc($userEmail); // a copy for the person who asked
                }
                foreach ($built['to_emails'] as $i => $addr) {
                    $m->to($addr, $i === 0 ? $built['supplier'] : null);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Compliance document request mail failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Could not send: '.mb_substr($e->getMessage(), 0, 200)], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Mail sent to '.($built['supplier'] ?: 'supplier').' ('.implode(', ', $built['to_emails']).').',
        ]);
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
            'skus' => $skus,
            'docs' => $docs,
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
