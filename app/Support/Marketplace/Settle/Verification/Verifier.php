<?php

namespace App\Support\Marketplace\Settle\Verification;

/**
 * Read-only verification of one settle task. Implementations may only SELECT from the existing
 * marketplace tables; they must never write to them.
 */
interface Verifier
{
    /** @param  string  $date  Pacific calendar date (Y-m-d) that was settled */
    public function verify(string $date, VerificationContext $context): VerificationResult;
}
