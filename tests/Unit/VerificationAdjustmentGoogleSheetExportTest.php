<?php

namespace Tests\Unit;

use App\Http\Controllers\InventoryManagement\VerificationAdjustmentController;
use PHPUnit\Framework\TestCase;

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
}
