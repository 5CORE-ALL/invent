<?php

namespace Tests\Unit;

use App\Support\Marketplace\WayfairClassQuestionForm;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WayfairClassQuestionFormTest extends TestCase
{
    #[Test]
    public function it_flattens_required_class_questions_and_skips_core_auto_fields(): void
    {
        $rows = WayfairClassQuestionForm::flatten([
            [
                'id' => 'core::productname',
                'displayName' => 'Product Name',
                'importanceType' => 'REQUIRED',
                'isActive' => true,
            ],
            [
                'id' => 'group::shipping',
                'displayName' => 'Shipping & Fulfillment',
                'isActive' => true,
                'childQuestions' => [
                    [
                        'id' => 'q::origin',
                        'displayName' => 'Country of Origin',
                        'importanceType' => 'REQUIRED',
                        'answerType' => 'STRING',
                        'isActive' => true,
                        'possibleAnswers' => [['value' => 'China'], ['value' => 'United States']],
                    ],
                    [
                        'id' => 'q::color',
                        'displayName' => 'Color',
                        'importanceType' => 'RECOMMENDED',
                        'answerType' => 'STRING',
                        'isActive' => true,
                    ],
                ],
            ],
        ]);

        $ids = array_column($rows, 'id');
        $this->assertNotContains('core::productname', $ids);
        $this->assertContains('q::origin', $ids);
        $this->assertContains('q::color', $ids);

        $origin = collect($rows)->firstWhere('id', 'q::origin');
        $this->assertTrue($origin['required']);
        $this->assertSame(['China', 'United States'], $origin['options']);
    }
}
