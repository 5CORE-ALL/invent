<?php

namespace App\Support;

/**
 * Rebuild the Yes/No ticks that were saved when a task was marked Done.
 * New completions store answers on the submission row. Older ones only
 * kept the flattened report text, which this parser turns back into ticks.
 */
class TaskDoneChecklistReview
{
    /**
     * @param  array<int, array<string, mixed>>  $questions
     * @return array<string, mixed>
     */
    public static function answersFromReport(array $questions, string $report): array
    {
        $report = trim($report);
        if ($report === '' || ! str_starts_with($report, 'Checklist Form #')) {
            return [];
        }

        $unused = [];
        foreach (preg_split("/\r\n|\n|\r/", $report) ?: [] as $line) {
            $line = trim($line);
            if (preg_match('/^\d+\.\s+(.*)$/', $line, $m)) {
                $unused[] = $m[1];
            }
        }

        $normalized = [];
        foreach ($questions as $q) {
            $qid = (string) ($q['id'] ?? '');
            if ($qid === '') {
                continue;
            }
            $label = (string) ($q['label'] ?? $qid);
            $prefix = $label.': ';
            $matchAt = null;
            foreach ($unused as $idx => $body) {
                if (str_starts_with($body, $prefix)) {
                    $matchAt = $idx;
                    break;
                }
            }
            if ($matchAt === null) {
                continue;
            }
            $rest = substr($unused[$matchAt], strlen($prefix));
            unset($unused[$matchAt]);

            if (($q['type'] ?? '') === 'checkbox') {
                $parsed = self::parseCheckboxRest($rest);
                if ($parsed !== null) {
                    $normalized[$qid] = $parsed;
                }
            } else {
                $normalized[$qid] = $rest;
            }
        }

        return $normalized;
    }

    /**
     * @return array{answer: bool, actions: list<string>, action: string, corrective_action: string}|null
     */
    private static function parseCheckboxRest(string $rest): ?array
    {
        $yes = str_starts_with($rest, 'Yes');
        $no = str_starts_with($rest, 'No');
        if (! $yes && ! $no) {
            return null;
        }

        $actions = [];
        $corrective = '';
        if ($no) {
            $parts = explode(' | ', $rest);
            foreach (array_slice($parts, 1) as $part) {
                if (preg_match('/^Action(?:\s+\d+)?:\s*(.*)$/', $part, $actionMatch)) {
                    $act = trim($actionMatch[1]);
                    if ($act !== '') {
                        $actions[] = $act;
                    }
                } elseif (preg_match('/^Corrective action:\s*(.*)$/', $part, $correctiveMatch)) {
                    $corrective = trim($correctiveMatch[1]);
                }
            }
        }

        return [
            'answer' => $yes,
            'actions' => $actions,
            'action' => $actions[0] ?? '',
            'corrective_action' => $corrective,
        ];
    }
}
