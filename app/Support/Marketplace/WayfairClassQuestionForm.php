<?php

namespace App\Support\Marketplace;

use App\Services\WayfairApiService;

/**
 * Flatten Partner Home product-addition questions for the listing-manager form.
 */
class WayfairClassQuestionForm
{
    /**
     * @return array{success: bool, questions: list<array<string, mixed>>, required_ids: list<string>, message: string}
     */
    public static function forClass(int $classId): array
    {
        $res = app(WayfairApiService::class)->getProductAdditionQuestions($classId);
        $flat = self::flatten(is_array($res['questions'] ?? null) ? $res['questions'] : []);
        $required = [];
        foreach ($flat as $row) {
            if (($row['required'] ?? false) && empty($row['group'])) {
                $required[] = (string) $row['id'];
            }
        }

        return [
            'success' => ($flat !== [] || trim((string) ($res['message'] ?? '')) === ''),
            'questions' => $flat,
            'required_ids' => $required,
            'message' => (string) ($res['message'] ?? ''),
        ];
    }

    /**
     * @param  list<mixed>  $questions
     * @return list<array<string, mixed>>
     */
    public static function flatten(array $questions, int $depth = 0): array
    {
        $out = [];
        foreach ($questions as $question) {
            if (! is_array($question)) {
                continue;
            }
            if (($question['isActive'] ?? true) === false) {
                continue;
            }
            $id = trim((string) ($question['id'] ?? ''));
            $children = is_array($question['childQuestions'] ?? null) ? $question['childQuestions'] : [];
            if ($children !== []) {
                $label = trim((string) ($question['displayName'] ?? $question['internalName'] ?? ''));
                if ($label !== '') {
                    $out[] = [
                        'id' => $id,
                        'label' => $label,
                        'group' => true,
                        'required' => false,
                        'depth' => $depth,
                    ];
                }
                $out = array_merge($out, self::flatten($children, $depth + 1));
                continue;
            }
            if ($id === '' || self::isCoreAutoQuestion($id)) {
                continue;
            }
            $out[] = self::present($question, $depth);
        }

        return $out;
    }

    public static function isCoreAutoQuestion(string $id): bool
    {
        $id = strtolower(trim($id));

        return in_array($id, [
            'core::amazonstandardidentificationnumber',
            'core::collectionname',
            'core::manufacturerpartnumber',
            'core::manufacturerproducturl',
            'core::productname',
            'core::universalproductcode',
            'featuredescription::genericfeatures',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    public static function present(array $question, int $depth = 0): array
    {
        $id = trim((string) ($question['id'] ?? ''));
        $label = trim((string) ($question['displayName'] ?? $question['internalName'] ?? $id));
        $importance = strtoupper(trim((string) ($question['importanceType'] ?? '')));
        $options = [];
        foreach ($question['possibleAnswers'] ?? [] as $row) {
            $value = is_array($row) ? trim((string) ($row['value'] ?? $row['key'] ?? '')) : trim((string) $row);
            if ($value !== '') {
                $options[] = $value;
            }
        }

        return [
            'id' => $id,
            'label' => $label !== '' ? $label : $id,
            'help' => trim((string) ($question['description'] ?? '')),
            'type' => strtoupper(trim((string) ($question['answerType'] ?? 'STRING'))),
            'required' => $importance === 'REQUIRED',
            'recommended' => $importance === 'RECOMMENDED',
            'importance' => $importance !== '' ? $importance : 'OPTIONAL',
            'multi' => (bool) ($question['isMultiValue'] ?? false),
            'options' => $options,
            'na' => (bool) ($question['isNotApplicableEligible'] ?? false),
            'unavailable' => (bool) ($question['isUnavailableEligible'] ?? false),
            'group' => false,
            'depth' => $depth,
        ];
    }
}
