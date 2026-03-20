<?php

namespace App\Console\Commands;

use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class WhatsAppCheckCommand extends Command
{
    protected $signature = 'whatsapp:check';
    protected $description = 'Verify Task WhatsApp config (env, Gupshup wa API) and remind about users.phone';

    public function handle(WhatsAppService $wa): int
    {
        $this->info('Task WhatsApp — config check');
        $this->newLine();

        $enabled = config('services.whatsapp.enabled');
        $this->line('WHATSAPP_ENABLED: ' . ($enabled ? 'true' : 'false'));

        $apiKey = config('services.whatsapp.gupshup.api_key');
        $source = config('services.whatsapp.gupshup.source');
        $appId = config('services.whatsapp.gupshup.app_id');
        $token = config('services.whatsapp.gupshup.access_token');

        $usesWa = $wa->usesWaApi();
        $this->line('Gupshup mode: ' . ($usesWa ? 'sm/wa API (apikey + source) → ' . config('services.whatsapp.gupshup.wa_api_base', 'sm/api/v1') : 'Partner v3 (app_id + access_token)'));

        if ($usesWa) {
            $this->line('GUPSHUP_API_KEY: ' . ($apiKey ? '[set]' : '[missing]'));
            $this->line('GUPSHUP_SOURCE: ' . ($source ?: '[missing]'));
        } else {
            $this->line('GUPSHUP_APP_ID: ' . ($appId ? '[set]' : '[missing]'));
            $this->line('GUPSHUP_ACCESS_TOKEN: ' . ($token ? '[set]' : '[missing]'));
            $this->line('GUPSHUP_SOURCE: ' . ($source ?: '[missing]'));
        }

        $this->newLine();
        $ok = $wa->isEnabled();
        $this->line('WhatsApp send enabled: ' . ($ok ? 'yes' : 'no'));
        if (!$ok) {
            $this->warn('Enable via WHATSAPP_ENABLED=true and Gupshup keys (see above).');
            return self::FAILURE;
        }

        $this->newLine();
        $useTemplate = config('services.whatsapp.use_template_for_tasks', true);
        $this->line('Use template for tasks: ' . ($useTemplate ? 'yes' : 'no'));
        $this->newLine();
        $this->comment('Assignees need users.phone (digits + country code). Check Laravel log for "assignee has no phone" if messages are not received.');
        $this->comment('Templates deliver outside 24h. Ensure Gupshup template param order matches TaskWhatsAppNotificationService.');
        return self::SUCCESS;
    }
}
