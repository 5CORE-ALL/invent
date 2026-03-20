<?php

namespace App\Console\Commands;

use App\Models\MetaAutomationRule;
use App\Services\MetaAutomationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MetaAdsAutomationCommand extends Command
{
    protected $signature = 'meta-ads:run-automation 
                            {--rule-id= : Run a specific rule by ID}
                            {--user-id= : Run rules for a specific user}
                            {--force : Force execution even if rule is inactive}
                            {--dry-run : Run in dry-run mode (no actual changes)}';

    protected $description = 'Execute Meta Ads automation rules';

    public function handle(MetaAutomationService $automationService)
    {
        $ruleId = $this->option('rule-id');
        $userId = $this->option('user-id') ? (int)$this->option('user-id') : null;
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $this->info('Starting Meta Ads automation rules execution...');

        if ($ruleId) {
            // Run a specific rule
            $rule = MetaAutomationRule::find($ruleId);
            
            if (!$rule) {
                $this->error("Rule with ID {$ruleId} not found.");
                return 1;
            }

            if ($dryRun) {
                $rule->dry_run_mode = true;
            }

            $this->line("Executing rule: {$rule->name}");
            $result = $automationService->executeRule($rule, $force);

            if ($result['success']) {
                $this->info("✓ Rule executed successfully!");
                $this->line("  - Entities evaluated: {$result['entities_evaluated']}");
                $this->line("  - Conditions matched: {$result['conditions_matched']}");
                $this->line("  - Actions executed: {$result['actions_executed']}");
                if ($result['dry_run'] ?? false) {
                    $this->warn("  - Mode: DRY RUN (no actual changes made)");
                }
            } else {
                $this->error("✗ Rule execution failed: " . ($result['error'] ?? $result['message'] ?? 'Unknown error'));
                return 1;
            }
        } else {
            // Run all active rules
            if ($dryRun) {
                $this->warn("Running in DRY RUN mode - no actual changes will be made");
            }

            $summary = $automationService->runAllActiveRules($userId);

            $this->info("Automation execution summary:");
            $this->line("  - Total rules: {$summary['total_rules']}");
            $this->line("  - Executed: {$summary['executed']}");
            $this->line("  - Failed: {$summary['failed']}");

            if (!empty($summary['results'])) {
                $this->newLine();
                $this->table(
                    ['Rule ID', 'Rule Name', 'Status', 'Entities', 'Matched', 'Actions'],
                    array_map(function ($r) {
                        return [
                            $r['rule_id'],
                            $r['rule_name'],
                            $r['result']['success'] ? '✓ Success' : '✗ Failed',
                            $r['result']['entities_evaluated'] ?? 0,
                            $r['result']['conditions_matched'] ?? 0,
                            $r['result']['actions_executed'] ?? 0,
                        ];
                    }, $summary['results'])
                );
            }

            if ($summary['failed'] > 0) {
                $this->warn("Some rules failed. Check logs for details.");
                return 1;
            }
        }

        $this->info('✓ Automation execution completed!');
        return 0;
    }
}

