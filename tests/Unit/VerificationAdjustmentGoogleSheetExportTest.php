<?php

namespace Tests\Unit;

use App\Http\Controllers\InventoryManagement\VerificationAdjustmentController;
use Tests\TestCase;

class VerificationAdjustmentGoogleSheetExportTest extends TestCase
{
    public function test_share_emails_include_current_user_and_small_fallback_list(): void
    {
        $emails = VerificationAdjustmentController::shareEmailsForExport('Tester@5core.com', '5core.com');

        $this->assertSame([
            'tester@5core.com',
            'inventory@5core.com',
            'president@5core.com',
        ], $emails);
        $this->assertLessThanOrEqual(5, count($emails));
    }

    public function test_share_emails_ignore_non_domain_current_user(): void
    {
        $emails = VerificationAdjustmentController::shareEmailsForExport('guest@gmail.com', '5core.com');

        $this->assertSame([
            'inventory@5core.com',
            'president@5core.com',
        ], $emails);
        $this->assertNotContains('guest@gmail.com', $emails);
    }

    public function test_excel_payload_is_a_real_xlsx_file(): void
    {
        $payload = VerificationAdjustmentController::excelPayloadFromRows([
            ['Parent' => 'P1', 'SKU' => 'SKU-1', 'Main-INV' => 2],
            ['Parent' => 'P1', 'SKU' => 'SKU-2', 'Main-INV' => 4],
        ]);

        $this->assertStringEndsWith('.xlsx', $payload['filename']);
        $bytes = base64_decode($payload['base64'], true);
        $this->assertNotFalse($bytes);
        $this->assertStringStartsWith('PK', $bytes);
    }
}
