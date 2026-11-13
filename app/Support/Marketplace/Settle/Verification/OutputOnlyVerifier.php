<?php

namespace App\Support\Marketplace\Settle\Verification;

/**
 * For tasks whose output has no dated table to compare with (e.g. sync:wayfair-l30-api writes
 * product-sheet L30/L60 columns). They are verified from the command output only and always
 * reported as "limited", never as a full pass.
 */
final class OutputOnlyVerifier implements Verifier
{
    public function __construct(private string $reason = 'No dated data source to compare with; verified from the command output only.') {}

    public function verify(string $date, VerificationContext $context): VerificationResult
    {
        return new VerificationResult(VerificationResult::LIMITED, [VerificationResult::check('output_only', 'limited', $this->reason)]);
    }
}
