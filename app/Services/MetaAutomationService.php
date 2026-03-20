<?php

namespace App\Services;

use App\Models\MetaAutomationRule;
use App\Models\MetaAutomationRuleRun;
use App\Models\MetaCampaign;
use App\Models\MetaAdSet;
use App\Models\MetaAd;
use App\Models\MetaInsightDaily;
use App\Models\MetaActionLog;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MetaAutomationService
{
    protected $metaAdsService;

    public function __construct(MetaAdsService $metaAdsService)
    {
        $this->metaAdsService = $metaAdsService;
    }

    /**
     * Execute a specific automation rule
     * 
     * @param MetaAutomationRule $rule
     * @param bool $force Force execution even if rule is inactive
     * @return array Execution results
     */
    public function executeRule(MetaAutomationRule $rule, bool $force = false): array
    {
        if (!$rule->is_active && !$force) {
            return [
                'success' => false,
                'message' => 'Rule is not active',
            ];
        }

        $run = MetaAutomationRuleRun::create([
            'rule_id' => $rule->id,
            'user_id' => $rule->user_id,
            'status' => 'running',
            'started_at' => now(),
            'dry_run' => $rule->dry_run_mode,
            'entities_evaluated' => 0,
            'conditions_matched' => 0,
            'actions_executed' => 0,
        ]);

        try {
            $entities = $this->getEntitiesForRule($rule);
            $matchedEntities = [];
            $executionLog = [];

            foreach ($entities as $entity) {
                $run->increment('entities_evaluated');

                if ($this->evaluateConditions($entity, $rule->conditions)) {
                    $run->increment('conditions_matched');
                    $matchedEntities[] = $entity;

                    if (!$rule->dry_run_mode) {
                        $actionResults = $this->executeActions($entity, $rule->actions, $rule->user_id);
                        foreach ($actionResults as $result) {
                            if ($result['success']) {
                                $run->increment('actions_executed');
                            }
                        }
                        $executionLog[] = [
                            'entity_id' => $entity->meta_id ?? $entity->id,
                            'entity_name' => $entity->name ?? 'N/A',
                            'actions' => $actionResults,
                        ];
                    } else {
                        // Dry run - just log what would happen
                        $executionLog[] = [
                            'entity_id' => $entity->meta_id ?? $entity->id,
                            'entity_name' => $entity->name ?? 'N/A',
                            'actions' => $this->simulateActions($entity, $rule->actions),
                            'dry_run' => true,
                        ];
                    }
                }
            }

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
                'execution_log' => $executionLog,
            ]);

            $rule->update([
                'last_run_at' => now(),
                'total_runs' => $rule->total_runs + 1,
                'total_actions_taken' => $rule->total_actions_taken + $run->actions_executed,
            ]);

            return [
                'success' => true,
                'run_id' => $run->id,
                'entities_evaluated' => $run->entities_evaluated,
                'conditions_matched' => $run->conditions_matched,
                'actions_executed' => $run->actions_executed,
                'dry_run' => $rule->dry_run_mode,
            ];
        } catch (\Exception $e) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            Log::error('MetaAutomationService: Rule execution failed', [
                'rule_id' => $rule->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'run_id' => $run->id,
            ];
        }
    }

    /**
     * Get entities to evaluate based on rule entity type
     * 
     * @param MetaAutomationRule $rule
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getEntitiesForRule(MetaAutomationRule $rule)
    {
        $query = null;

        switch ($rule->entity_type) {
            case 'campaign':
                $query = MetaCampaign::where('user_id', $rule->user_id);
                break;
            case 'adset':
                $query = MetaAdSet::where('user_id', $rule->user_id);
                break;
            case 'ad':
                $query = MetaAd::where('user_id', $rule->user_id);
                break;
            default:
                return collect([]);
        }

        // Apply any filters from conditions (e.g., account_id, status)
        $conditions = $rule->conditions ?? [];
        foreach ($conditions as $condition) {
            if (($condition['type'] ?? null) === 'filter' && isset($condition['field'])) {
                $field = $condition['field'];
                $value = $condition['value'] ?? null;
                $operator = $condition['operator'] ?? '=';

                if ($field === 'account_id') {
                    // Filter by ad account
                    $query->where('ad_account_id', $value);
                } elseif ($field === 'status' && isset($value)) {
                    // Filter by status
                    switch ($operator) {
                        case '=':
                            $query->where($field, $value);
                            break;
                        case '!=':
                            $query->where($field, '!=', $value);
                            break;
                        case 'in':
                            $query->whereIn($field, (array)$value);
                            break;
                        case 'not_in':
                            $query->whereNotIn($field, (array)$value);
                            break;
                    }
                } elseif (isset($value)) {
                    switch ($operator) {
                        case '=':
                            $query->where($field, $value);
                            break;
                        case '!=':
                            $query->where($field, '!=', $value);
                            break;
                        case '>':
                            $query->where($field, '>', $value);
                            break;
                        case '<':
                            $query->where($field, '<', $value);
                            break;
                        case '>=':
                            $query->where($field, '>=', $value);
                            break;
                        case '<=':
                            $query->where($field, '<=', $value);
                            break;
                        case 'in':
                            $query->whereIn($field, (array)$value);
                            break;
                        case 'not_in':
                            $query->whereNotIn($field, (array)$value);
                            break;
                    }
                }
            }
        }

        return $query->get();
    }

    /**
     * Evaluate conditions for an entity
     * 
     * @param Model $entity
     * @param array $conditions
     * @return bool
     */
    protected function evaluateConditions($entity, array $conditions): bool
    {
        if (empty($conditions)) {
            return true;
        }

        $logicOperator = 'AND'; // Default: all conditions must match
        $evaluationResults = [];

        foreach ($conditions as $condition) {
            if ($condition['type'] === 'filter') {
                // Filter conditions are already applied in getEntitiesForRule
                continue;
            }

            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? null;
            $aggregation = $condition['aggregation'] ?? null; // e.g., 'last_7d', 'last_30d'

            if (!$field) {
                continue;
            }

            // Get field value from entity or insights
            $fieldValue = $this->getFieldValue($entity, $field, $aggregation);

            $matches = $this->compareValues($fieldValue, $operator, $value);
            $evaluationResults[] = $matches;

            if (isset($condition['logic'])) {
                $logicOperator = strtoupper($condition['logic']);
            }
        }

        if (empty($evaluationResults)) {
            return true;
        }

        // Apply logic operator
        if ($logicOperator === 'OR') {
            return in_array(true, $evaluationResults, true);
        } else {
            return !in_array(false, $evaluationResults, true);
        }
    }

    /**
     * Get field value from entity or insights
     * 
     * @param Model $entity
     * @param string $field
     * @param string|null $aggregation
     * @return mixed
     */
    protected function getFieldValue($entity, string $field, ?string $aggregation = null)
    {
        // Direct entity field
        if (isset($entity->$field)) {
            return $entity->$field;
        }

        // Access nested fields (e.g., status, daily_budget)
        if (strpos($field, '.') !== false) {
            $parts = explode('.', $field);
            $value = $entity;
            foreach ($parts as $part) {
                if (is_object($value) && isset($value->$part)) {
                    $value = $value->$part;
                } elseif (is_array($value) && isset($value[$part])) {
                    $value = $value[$part];
                } else {
                    return null;
                }
            }
            return $value;
        }

        // Get from insights if field is a metric
        if (in_array($field, ['spend', 'impressions', 'clicks', 'ctr', 'cpc', 'cpm', 'reach'])) {
            return $this->getInsightValue($entity, $field, $aggregation);
        }

        return null;
    }

    /**
     * Get insight value for an entity
     * 
     * @param Model $entity
     * @param string $metric
     * @param string|null $aggregation
     * @return float|int|null
     */
    protected function getInsightValue($entity, string $metric, ?string $aggregation = null)
    {
        if (!$aggregation) {
            $aggregation = 'last_7d'; // Default to last 7 days
        }

        $dateEnd = Carbon::now();
        $dateStart = match($aggregation) {
            'last_1d' => $dateEnd->copy()->subDay(),
            'last_7d' => $dateEnd->copy()->subDays(7),
            'last_30d' => $dateEnd->copy()->subDays(30),
            'last_90d' => $dateEnd->copy()->subDays(90),
            default => $dateEnd->copy()->subDays(7),
        };

        // Get the entity database ID, not meta_id
        $entityId = $entity->id ?? null;
        if (!$entityId) {
            return 0;
        }

        $insight = MetaInsightDaily::where('entity_type', $this->getEntityType($entity))
            ->where('entity_id', $entityId)
            ->whereBetween('date_start', [$dateStart->format('Y-m-d'), $dateEnd->format('Y-m-d')])
            ->sum($metric);

        return $insight ?? 0;
    }

    /**
     * Get entity type string from model
     * 
     * @param Model $entity
     * @return string
     */
    protected function getEntityType($entity): string
    {
        if ($entity instanceof MetaCampaign) {
            return 'campaign';
        } elseif ($entity instanceof MetaAdSet) {
            return 'adset';
        } elseif ($entity instanceof MetaAd) {
            return 'ad';
        }
        return 'campaign';
    }

    /**
     * Compare two values based on operator
     * 
     * @param mixed $fieldValue
     * @param string $operator
     * @param mixed $conditionValue
     * @return bool
     */
    protected function compareValues($fieldValue, string $operator, $conditionValue): bool
    {
        if ($fieldValue === null) {
            return false;
        }

        switch ($operator) {
            case '=':
            case '==':
                return $fieldValue == $conditionValue;
            case '!=':
            case '<>':
                return $fieldValue != $conditionValue;
            case '>':
                return (float)$fieldValue > (float)$conditionValue;
            case '<':
                return (float)$fieldValue < (float)$conditionValue;
            case '>=':
                return (float)$fieldValue >= (float)$conditionValue;
            case '<=':
                return (float)$fieldValue <= (float)$conditionValue;
            case 'contains':
                return stripos((string)$fieldValue, (string)$conditionValue) !== false;
            case 'starts_with':
                return stripos((string)$fieldValue, (string)$conditionValue) === 0;
            case 'ends_with':
                return substr_compare((string)$fieldValue, (string)$conditionValue, -strlen((string)$conditionValue)) === 0;
            case 'in':
                return in_array($fieldValue, (array)$conditionValue);
            default:
                return false;
        }
    }

    /**
     * Execute actions on an entity
     * 
     * @param Model $entity
     * @param array $actions
     * @param int|null $userId
     * @return array Action results
     */
    protected function executeActions($entity, array $actions, ?int $userId = null): array
    {
        $results = [];

        foreach ($actions as $action) {
            $actionType = $action['type'] ?? null;
            $actionValue = $action['value'] ?? null;

            try {
                $result = match($actionType) {
                    'pause' => $this->pauseEntity($entity, $userId),
                    'resume' => $this->resumeEntity($entity, $userId),
                    'update_budget' => $this->updateBudget($entity, $actionValue, $userId),
                    'update_bid' => $this->updateBid($entity, $actionValue, $userId),
                    'update_status' => $this->updateStatus($entity, $actionValue, $userId),
                    default => ['success' => false, 'message' => "Unknown action type: {$actionType}"],
                };

                $results[] = array_merge([
                    'type' => $actionType,
                    'value' => $actionValue,
                ], $result);
            } catch (\Exception $e) {
                $results[] = [
                    'type' => $actionType,
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Simulate actions (for dry run)
     * 
     * @param Model $entity
     * @param array $actions
     * @return array
     */
    protected function simulateActions($entity, array $actions): array
    {
        $results = [];

        foreach ($actions as $action) {
            $results[] = [
                'type' => $action['type'] ?? 'unknown',
                'value' => $action['value'] ?? null,
                'success' => true,
                'message' => "Would execute: {$action['type']}",
                'dry_run' => true,
            ];
        }

        return $results;
    }

    /**
     * Pause an entity
     * 
     * @param Model $entity
     * @param int|null $userId
     * @return array
     */
    protected function pauseEntity($entity, ?int $userId = null): array
    {
        $entityType = $this->getEntityType($entity);
        return $this->metaAdsService->updateStatus($entityType, $entity->meta_id, 'PAUSED', $userId);
    }

    /**
     * Resume an entity
     * 
     * @param Model $entity
     * @param int|null $userId
     * @return array
     */
    protected function resumeEntity($entity, ?int $userId = null): array
    {
        $entityType = $this->getEntityType($entity);
        return $this->metaAdsService->updateStatus($entityType, $entity->meta_id, 'ACTIVE', $userId);
    }

    /**
     * Update budget
     * 
     * @param Model $entity
     * @param mixed $budget Can be a number (daily) or array ['daily_budget' => X, 'lifetime_budget' => Y]
     * @param int|null $userId
     * @return array
     */
    protected function updateBudget($entity, $budget, ?int $userId = null): array
    {
        $entityType = $this->getEntityType($entity);
        
        // Convert single value to array format if needed
        if (!is_array($budget)) {
            // Assume it's daily budget in dollars, convert to cents
            $budgetData = ['daily_budget' => (int)($budget * 100)];
        } else {
            // Ensure budgets are in cents
            $budgetData = [];
            if (isset($budget['daily_budget'])) {
                $budgetData['daily_budget'] = is_int($budget['daily_budget']) 
                    ? $budget['daily_budget'] 
                    : (int)($budget['daily_budget'] * 100);
            }
            if (isset($budget['lifetime_budget'])) {
                $budgetData['lifetime_budget'] = is_int($budget['lifetime_budget']) 
                    ? $budget['lifetime_budget'] 
                    : (int)($budget['lifetime_budget'] * 100);
            }
        }
        
        if (empty($budgetData)) {
            return ['success' => false, 'message' => 'No budget data provided'];
        }
        
        return $this->metaAdsService->updateBudget($entityType, $entity->meta_id, $budgetData, $userId ?? $entity->user_id);
    }

    /**
     * Update bid (for adsets)
     * 
     * @param Model $entity
     * @param mixed $bid
     * @param int|null $userId
     * @return array
     */
    protected function updateBid($entity, $bid, ?int $userId = null): array
    {
        if (!($entity instanceof MetaAdSet)) {
            return ['success' => false, 'message' => 'Bid update only available for ad sets'];
        }

        // This would need to be implemented in MetaAdsService
        return ['success' => false, 'message' => 'Bid update not yet implemented'];
    }

    /**
     * Update status
     * 
     * @param Model $entity
     * @param string $status
     * @param int|null $userId
     * @return array
     */
    protected function updateStatus($entity, string $status, ?int $userId = null): array
    {
        $entityType = $this->getEntityType($entity);
        return $this->metaAdsService->updateStatus($entityType, $entity->meta_id, $status, $userId);
    }

    /**
     * Run all active automation rules
     * 
     * @param int|null $userId Optional user ID to filter rules
     * @return array Summary of execution
     */
    public function runAllActiveRules(?int $userId = null): array
    {
        $query = MetaAutomationRule::where('is_active', true);
        
        if ($userId) {
            $query->where('user_id', $userId);
        }

        $rules = $query->get();
        $summary = [
            'total_rules' => $rules->count(),
            'executed' => 0,
            'failed' => 0,
            'results' => [],
        ];

        foreach ($rules as $rule) {
            // Check if rule should run based on schedule
            if (!$this->shouldRunRule($rule)) {
                continue;
            }

            $result = $this->executeRule($rule);
            
            if ($result['success']) {
                $summary['executed']++;
            } else {
                $summary['failed']++;
            }

            $summary['results'][] = [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'result' => $result,
            ];
        }

        return $summary;
    }

    /**
     * Check if a rule should run based on its schedule
     * 
     * @param MetaAutomationRule $rule
     * @return bool
     */
    protected function shouldRunRule(MetaAutomationRule $rule): bool
    {
        if (!$rule->schedule) {
            return true; // Run immediately if no schedule
        }

        if (!$rule->last_run_at) {
            return true; // First run
        }

        $schedule = strtolower($rule->schedule);
        $lastRun = Carbon::parse($rule->last_run_at);
        $now = Carbon::now();

        return match($schedule) {
            'hourly' => $lastRun->diffInHours($now) >= 1,
            'daily' => $lastRun->diffInDays($now) >= 1,
            'weekly' => $lastRun->diffInWeeks($now) >= 1,
            default => $now->gte($lastRun->add($this->parseCronExpression($schedule))),
        };
    }

    /**
     * Parse cron expression (simple implementation)
     * 
     * @param string $expression
     * @return \DateInterval|null
     */
    protected function parseCronExpression(string $expression): ?\DateInterval
    {
        // Simple cron parser - can be enhanced
        if (preg_match('/^(\d+)m$/', $expression, $matches)) {
            return new \DateInterval("PT{$matches[1]}M");
        }
        if (preg_match('/^(\d+)h$/', $expression, $matches)) {
            return new \DateInterval("PT{$matches[1]}H");
        }
        if (preg_match('/^(\d+)d$/', $expression, $matches)) {
            return new \DateInterval("P{$matches[1]}D");
        }
        return null;
    }
}

