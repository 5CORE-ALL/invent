<?php

namespace App\Support\Marketplace\Settle\Verification;

/**
 * Outcome of verifying one settle task against the data it should have produced.
 *
 * Status precedence (worst first): fail > error > pending > warn > limited > pass.
 *  - pass    : every check passed.
 *  - warn    : verified, but something looks unusual (shown, does not block Settled).
 *  - limited : verified only as far as possible (no dated source to compare with).
 *  - pending : waiting on another task; NOT verified yet.
 *  - error   : the verifier could not run (missing table / exception); NOT verified.
 *  - fail    : the data is missing / incomplete / suspicious; NOT verified.
 */
final class VerificationResult
{
    public const PASS = 'pass';

    public const WARN = 'warn';

    public const LIMITED = 'limited';

    public const PENDING = 'pending';

    public const ERROR = 'error';

    public const FAIL = 'fail';

    private const RANK = [
        self::PASS => 0,
        self::LIMITED => 1,
        self::WARN => 2,
        self::PENDING => 3,
        self::ERROR => 4,
        self::FAIL => 5,
    ];

    /**
     * @param  list<array{code: string, status: string, message: string, data?: array<string, mixed>}>  $checks
     * @param  array<string, mixed>  $metrics
     */
    public function __construct(
        public string $status,
        public array $checks = [],
        public array $metrics = [],
    ) {}

    /**
     * @param  list<array{code: string, status: string, message: string, data?: array<string, mixed>}>  $checks
     * @param  array<string, mixed>  $metrics
     */
    public static function fromChecks(array $checks, array $metrics = []): self
    {
        $status = self::PASS;
        foreach ($checks as $c) {
            if ((self::RANK[$c['status']] ?? 0) > self::RANK[$status]) {
                $status = $c['status'];
            }
        }

        return new self($status, $checks, $metrics);
    }

    public static function error(string $code, string $message): self
    {
        return new self(self::ERROR, [['code' => $code, 'status' => self::ERROR, 'message' => $message, 'data' => []]]);
    }

    public static function check(string $code, string $status, string $message, array $data = []): array
    {
        return ['code' => $code, 'status' => $status, 'message' => $message, 'data' => $data];
    }

    /** Adds checks (e.g. command-output checks) and re-computes the overall status. */
    public function withChecks(array $extra): self
    {
        return self::fromChecks(array_merge($this->checks, $extra), $this->metrics);
    }

    /** Verified = safe to count towards "Settled". */
    public function isVerified(): bool
    {
        return in_array($this->status, [self::PASS, self::WARN, self::LIMITED], true);
    }

    public function isFailure(): bool
    {
        return in_array($this->status, [self::FAIL, self::ERROR], true);
    }

    /**
     * Messages of every check that is not a plain pass.
     *
     * @return list<string>
     */
    public function problemMessages(): array
    {
        $out = [];
        foreach ($this->checks as $c) {
            if ($c['status'] !== self::PASS) {
                $out[] = $c['code'].': '.$c['message'];
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public function failedCodes(): array
    {
        $codes = [];
        foreach ($this->checks as $c) {
            if (in_array($c['status'], [self::FAIL, self::ERROR], true)) {
                $codes[] = $c['code'];
            }
        }

        return $codes;
    }

    /**
     * @return array{status: string, checks: list<array<string, mixed>>, metrics: array<string, mixed>}
     */
    public function toArray(): array
    {
        return ['status' => $this->status, 'checks' => $this->checks, 'metrics' => $this->metrics];
    }
}
