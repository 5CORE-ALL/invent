<?php

namespace App\Support\Marketplace\Settle\Verification;

use Carbon\Carbon;

/**
 * Everything a verifier may need about the task it is verifying. Plain data, no behaviour.
 */
final class VerificationContext
{
    /**
     * @param  array{command: string, lane: string, passes: list<int>, timeout: int}  $task
     * @param  callable(string): bool  $taskDone  true when the other task is done in the ledger for every due pass
     */
    public function __construct(
        public string $taskKey,
        public array $task,
        public bool $finalPassDone,
        public ?string $doneAt,
        public Carbon $now,
        private $taskDone,
    ) {}

    public function otherTaskDone(string $key): bool
    {
        return (bool) ($this->taskDone)($key);
    }
}
