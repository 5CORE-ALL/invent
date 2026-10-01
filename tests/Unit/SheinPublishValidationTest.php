<?php

namespace Tests\Unit;

use App\Services\SheinApiService;
use Tests\TestCase;

class SheinPublishValidationTest extends TestCase
{
    public function test_validation_messages_are_flattened_with_module_and_form(): void
    {
        $info = [
            'success' => false,
            'spu_name' => null,
            'pre_valid_result' => [
                ['module' => 'basic_info', 'form' => 'brand_code', 'messages' => ['Current shop of the brand is not authorized.']],
                ['module' => 'sales_info', 'form' => 'publish_site', 'messages' => ['Launch site cannot be empty.']],
            ],
            'mcc_valid_result' => null,
        ];

        $messages = SheinApiService::publishValidationMessages($info);

        $this->assertSame([
            'basic_info/brand_code: Current shop of the brand is not authorized.',
            'sales_info/publish_site: Launch site cannot be empty.',
        ], $messages);
    }

    public function test_publish_or_edit_treats_info_success_false_as_failure(): void
    {
        $api = new class extends SheinApiService
        {
            public array $json = [];

            protected function sheinApiPost(string $endpoint, array $payload): array
            {
                return $this->json;
            }
        };

        $api->json = ['code' => '0', 'msg' => 'OK', 'info' => [
            'success' => false, 'spu_name' => null, 'skc_list' => null,
            'pre_valid_result' => [['module' => 'basic_info', 'form' => 'category', 'messages' => ['Current shop of the product category is not available.']]],
        ]];
        $res = $api->publishOrEditProduct(['brand_code' => 'x']);
        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Shein did not create the product', $res['message']);
        $this->assertStringContainsString('category: Current shop of the product category is not available.', $res['message']);

        $api->json = ['code' => '0', 'msg' => 'OK', 'info' => ['success' => true, 'spu_name' => 'z24080989888',
            'skc_list' => [['skc_name' => 'sz2408098988881576', 'sku_list' => [['sku_code' => 'I725t0bki4n5', 'supplier_sku' => 'LS 100-6 RED']]]]]];
        $ok = $api->publishOrEditProduct(['brand_code' => 'x']);
        $this->assertTrue($ok['success']);
        $this->assertSame('z24080989888', $ok['spu_name']);

        $api->json = ['code' => '0', 'msg' => 'OK', 'info' => ['success' => true, 'spu_name' => null, 'skc_list' => null]];
        $empty = $api->publishOrEditProduct(['brand_code' => 'x']);
        $this->assertFalse($empty['success']);
        $this->assertStringContainsString('no SPU/SKU code', $empty['message']);
    }
}
