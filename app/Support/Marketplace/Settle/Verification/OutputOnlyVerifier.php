<?php

namespace App\Support\Marketplace\Settle\Verification;

/**
 * For tasks without a dated table to compare with.
 *  - $byDesign = true : the task can never be checked against data (e.g. sync:wayfair-l30-api only
 *    updates product-sheet columns). Reported as `limited_by_design`; it does not stop "Fully Settled".
 *  - $byDesign = false: a verifier SHOULD exist but none is defined (e.g. a newly added ledger task).
 *    Reported as `limited` (skipped), which shows as "Settled with Warnings".
 */
final class OutputOnlyVerifier implements Verifier
{
    public function __construct(
        private string $reason = 'No data-level verifier is defined for this task; verified from the command output only.',
        private bool $byDesign = false,
    ) {}

    public function verify(string $date, VerificationContext $context): VerificationResult
    {
        $status = $this->byDesign ? VerificationResult::LIMITED_BY_DESIGN : VerificationResult::LIMITED;

        return new VerificationResult($status, [VerificationResult::check($this->byDesign ? 'limited_by_design' : 'verifier_missing', $status, $this->reason)]);
    }
}
