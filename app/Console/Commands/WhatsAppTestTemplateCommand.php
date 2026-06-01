<?php

namespace App\Console\Commands;

use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class WhatsAppTestTemplateCommand extends Command
{
    protected $signature = 'whatsapp:test-template
                            {--to= : Destination phone (digits + country) }
                            {--key=task_assigned : Template key: task_assigned|task_done|task_rework|task_updated }
                            {--params= : Comma-separated placeholder values. If empty, sensible defaults are used. }';

    protected $description = 'Send the actual approved task template to a number and print Gupshup\'s raw response (to debug "submitted but not delivered" cases).';

    public function handle(WhatsAppService $wa): int
    {
        $to = $this->option('to') ?: config('services.whatsapp.gupshup.test_destination');
        if (!$to) {
            $this->warn('Provide --to=91XXXXXXXXXX or set GUPSHUP_TEST_DESTINATION in .env');
            return self::FAILURE;
        }

        $key = $this->option('key');
        $templateId = config("services.whatsapp.gupshup.template_id_{$key}");
        if (!$templateId) {
            $this->error("No template id configured for key '{$key}'. Set GUPSHUP_TEMPLATE_ID_* in .env.");
            return self::FAILURE;
        }

        $params = $this->resolveParams($key);
        if ($custom = $this->option('params')) {
            $params = array_map('trim', explode(',', $custom));
        }

        $this->info("Sending template '{$key}' (id {$templateId}) to {$to} with " . count($params) . ' params ...');
        $result = $wa->sendTemplateWithDebug($to, $templateId, $params);

        $this->table(
            ['Key', 'Value'],
            collect($result)->map(fn ($v, $k) => [
                $k,
                is_bool($v) ? ($v ? 'true' : 'false') : (is_string($v) && strlen($v) > 160 ? substr($v, 0, 160) . '...' : $v),
            ])->values()->all()
        );

        if (!empty($result['ok'])) {
            $this->info('Submitted. If body says "submitted" but nothing arrives on WhatsApp, the template likely has a different placeholder count/spec on Gupshup — open the Gupshup template manager and compare {{1}}..{{N}} count with --params count above.');
            return self::SUCCESS;
        }

        $this->error('Send failed. Check status/body above.');
        return self::FAILURE;
    }

    /** Default params matching TaskWhatsAppNotificationService order. */
    protected function resolveParams(string $key): array
    {
        return match ($key) {
            'task_assigned' => ['Test Assignee', 'Test Assignor', 'Test Task Title', '19 May 2026', '20 May 2026', '30'],
            'task_done'     => ['Test Assignor', 'Test Assignee', 'Test Task Title', '30', '28'],
            'task_rework'   => ['Test Assignee', 'Test Assignor', 'Test Task Title', '30', '28', 'Please redo the formatting.'],
            'task_updated'  => ['Test Assignee', 'Test Task Title', 'Test Assignor', '19 May 2026', '20 May 2026', '30'],
            default => [],
        };
    }
}
