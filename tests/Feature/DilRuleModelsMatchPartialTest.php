<?php

namespace Tests\Feature;

use App\Support\DilRuleModels;
use Tests\TestCase;

/**
 * DilRuleModels must list exactly the pages whose Sprc Dil modal carries the same blocks. When a page gains or loses a
 * block in partials/ebay-sprc-dil.blade.php, this fails until the model list is updated.
 */
class DilRuleModelsMatchPartialTest extends TestCase
{
    private function blocksOf(string $channel): array
    {
        $script = view('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'script', 'ebaySprcDilChannel' => $channel])->render();
        $modals = view('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'modals', 'ebaySprcDilChannel' => $channel])->render();
        $on = static fn (string $const): bool => (bool) preg_match('/'.$const.' = true/', $script);
        $blocks = [];
        if ($on('EBAY_DIL_GROI_CVR_ADJ')) {
            $blocks[] = 'cvr_adj';
        }
        if ($on('EBAY_AGE_ADJ_ON')) {
            $blocks[] = 'age_adj';
        }
        if ($on('EBAY_DIFF_ADJ_ON')) {
            $blocks[] = 'diff_adj';
        }
        if (str_contains($modals, 'ebay-lmp-cap-table')) {
            $blocks[] = 'lmp_cap';
        }

        return $blocks;
    }

    public function test_each_model_lists_the_blocks_its_pages_really_have(): void
    {
        foreach (DilRuleModels::MODELS as $id => $model) {
            foreach ($model['channels'] as $channel) {
                if ($channel === 'amazon') {
                    continue; // own partial, checked below
                }
                $this->assertEqualsCanonicalizing($model['blocks'], $this->blocksOf($channel), "{$channel} is in model {$id} but its modal has other blocks");
            }
        }
    }

    public function test_amazon_modal_has_the_blocks_of_its_model(): void
    {
        $html = view('partials.amazon-pef-promo', ['amazonPefPromoPart' => 'modals'])->render();
        foreach (['cvr_adj' => 'amz-cvr-groi', 'age_adj' => 'amz-age-adj', 'diff_adj' => 'amz-diff-adj', 'lmp_cap' => 'amz-lmp-cap'] as $block => $marker) {
            $this->assertStringContainsString($marker, $html, "Amazon modal lost the {$block} block");
        }
        $this->assertEqualsCanonicalizing(DilRuleModels::modelFor('amazon')['blocks'], ['cvr_adj', 'age_adj', 'diff_adj', 'lmp_cap']);
        $this->assertStringContainsString('amz-dil-groi-save-all-btn', $html);
        $this->assertStringContainsString('amz-dil-groi-save-btn', $html);
    }

    public function test_every_page_that_includes_the_modal_is_in_exactly_one_model(): void
    {
        $seen = [];
        foreach (DilRuleModels::MODELS as $model) {
            foreach ($model['channels'] as $c) {
                $this->assertArrayNotHasKey($c, $seen, "{$c} is in two models");
                $seen[$c] = true;
            }
        }
        $missing = [];
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($iter as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }
            if (preg_match_all("/ebaySprcDilChannel'\s*=>\s*'([a-z0-9_]+)'/", (string) file_get_contents($file->getPathname()), $m)) {
                foreach ($m[1] as $channel) {
                    if (DilRuleModels::modelFor($channel) === null) {
                        $missing[$channel] = true;
                    }
                }
            }
        }
        $this->assertSame([], array_keys($missing), 'Pages with a Sprc Dil modal but no rule model');
    }
}
