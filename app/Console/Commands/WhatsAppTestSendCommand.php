<?php

namespace App\Console\Commands;

use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class WhatsAppTestSendCommand extends Command
{
    protected $signature = 'whatsapp:test-send {--to= : Destination phone (digits+country) }';
    protected $description = 'Send a test WhatsApp message. Set --to or GUPSHUP_TEST_DESTINATION.';

    public function handle(WhatsAppService $wa): int
    {
        $to = $this->option('to') ?: env('GUPSHUP_TEST_DESTINATION');
        if (!$to) {
            $this->warn('Provide --to=91XXXXXXXXXX or set GUPSHUP_TEST_DESTINATION in .env');
            return self::FAILURE;
        }

        $this->info('Sending test message to ' . $to . ' ...');
        $result = $wa->sendTextWithDebug($to, 'Test from Task Manager. If you receive this, WhatsApp config is OK.');

        $this->table(
            ['Key', 'Value'],
            collect($result)->map(fn ($v, $k) => [
                $k,
                is_bool($v) ? ($v ? 'true' : 'false') : (is_string($v) && strlen($v) > 120 ? substr($v, 0, 120) . '...' : $v),
            ])->values()->all()
        );

        if (!empty($result['ok'])) {
            $this->info('Message submitted. Check WhatsApp.');
            return self::SUCCESS;
        }

        $this->error('Send failed. Check status/body above and Laravel log.');
        return self::FAILURE;
    }
}
