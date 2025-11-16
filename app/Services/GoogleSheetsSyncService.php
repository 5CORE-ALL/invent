<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleSheetsSyncService
{
    private array $sheetEndpoints = [
        'ProductMaster' => 'https://script.google.com/macros/s/AKfycbzUsXOfhxZP49uTYfbxMBcx-NEr9TCPa8yQLJRUvQkxc10hZDzqM27XX0F_4EaDLHRlgQ/exec',
        'Amazon' => 'https://script.google.com/macros/s/AKfycbx51o_K6TjYvs-mYtXq1_B_OGu_ojxRCErdWD063GK5lCe1siZGwREvnNitTEK46vKh/exec',
        'Ebay' => 'https://script.google.com/macros/s/AKfycbzq7k35vKiGMfBrhO-7_gGNja1am_H0zBQ7xTjcfTfABaf4Q-lTVlui_-x8KzLHksX0sw/exec',
        'ShopifyB2C' => 'https://script.google.com/macros/s/AKfycbxAltEznYWjY5ULkbsGoi6RxAE5Tk8bLg_aqBhQ1dHvHZpaF3NstWt6xJgEfh00BjH-HQ/exec',
        'Mecy' => 'https://script.google.com/macros/s/AKfycbzEzP_1sM86PT512M3QFRYHDVJ-ZabnsczEh1_Eak8Eb1lZzZ4bX0yCGACxafEp8dbGng/exec',
        'NeweggB2C' => 'https://script.google.com/macros/s/AKfycbw9nqPPDupI2oD_JjD2UjpvSDVZlvIoPg7VjdsIlNu7udf0JnRjf29HTifUsRAfdexQ/exec'
    ];

    public function processSequentially(array $validatedData): array
    {
        $results = [];
        $operation = $validatedData['operation'];

        // 1. First process Product Master
        $postData = $this->prepareProductMasterData($validatedData);
        $postData['operation'] = $operation;

        // For update operations, include original values
        if ($operation === 'update') {
            $postData['original_sku'] = $validatedData['original_sku'] ?? '';
            $postData['original_parent'] = $validatedData['original_parent'] ?? '';

            // Check if SKU or Parent has changed
            if (
                ($postData['original_sku'] !== $postData['SKU']) ||
                ($postData['original_parent'] !== $postData['Parent'])
            ) {
                $postData['operation'] = 'updatesku'; // Special operation for SKU/Parent change
            }
        }

        $results['ProductMaster'] = $this->sendWithRetry(
            'ProductMaster',
            $postData,
            3 // Max retries
        );


        // Only continue if Product Master succeeded
        if ($results['ProductMaster']['success']) {

            $results['Amazon'] = $this->sendWithFallback(
                'Amazon',
                [
                    'task' => 'add_new_row', // ← change this value based on the required operation
                    'data' => $this->prepareBasicData($validatedData)
                ]
            );

            $results['Ebay'] = $this->sendToSheet(
                'Ebay',
                [
                    'task' => 'add_new_row', // ← change this value based on the required operation
                    'data' => $this->prepareBasicData($validatedData)
                ]
            );

            $results['ShopifyB2C'] = $this->sendToSheet(
                'ShopifyB2C',
                [
                    'task' => 'add_new_row', // ← change this value based on the required operation
                    'data' => $this->prepareBasicData($validatedData)
                ]
            );

            $results['Mecy'] = $this->sendToSheet(
                'Mecy',
                [
                    'task' => 'add_new_row', // ← change this value based on the required operation
                    'data' => $this->prepareBasicData($validatedData)
                ]
            );

            $results['NeweggB2C'] = $this->sendToSheet(
                'NeweggB2C',
                [
                    'task' => 'add_new_row', // ← change this value based on the required operation
                    'data' => $this->prepareBasicData($validatedData)
                ]
            );
        }

        return $results;
    }

    private function prepareProductMasterData(array $data): array
    {
        return [
            'Parent' => $data['Parent'] ?? '',
            'SKU' => $data['SKU'],
            'CP' => $data['CP'],
            'SHIP' => $data['SHIP'],
            'Label QTY' => $data['Label_QTY'],
            'WT ACT' => $data['WT_ACT'],
            'WT DECL' => $data['WT_DECL'],
            'L' => $data['L'],
            'W' => $data['W'],
            'H' => $data['H'],
            '5C' => $data['5C'] ?? '',
            'UPC' => $data['UPC'] ?? ''
        ];
    }

    private function prepareBasicData(array $data): array
    {
        return [
            'Parent' => $data['Parent'] ?? '',
            '(Child) sku' => $data['SKU']
        ];
    }

    private function sendWithRetry(string $sheetName, array $data, int $maxRetries): array
    {
        $attempt = 0;
        $lastError = null;

        while ($attempt < $maxRetries) {
            $result = $this->sendToSheet($sheetName, $data);

            if ($result['success']) {
                return $result;
            }

            $lastError = $result['message'];
            $attempt++;
            usleep(500000 * $attempt); // Exponential backoff
        }

        return [
            'success' => false,
            'message' => "Failed after $maxRetries attempts: $lastError"
        ];
    }

    private function sendWithFallback(string $sheetName, array $data): array
    {
        // First attempt with normal timeout
        $attempt = $this->sendToSheet($sheetName, $data);

        if ($attempt['success']) {
            return $attempt;
        }

        // Second attempt with fire-and-forget
        return $this->sendFireAndForget($sheetName, $data);
    }

    private function sendFireAndForget(string $sheetName, array $data): array
    {
        try {
            Http::timeout(5) // Short timeout for fire-and-forget
                ->async()
                ->post($this->sheetEndpoints[$sheetName], $data);

            return [
                'success' => true,
                'message' => 'Request sent asynchronously'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to send async request'
            ];
        }
    }

    private function sendToSheet(string $sheetName, array $data): array
    {
        try {
            $response = Http::timeout(15) // Reduced from 20
                ->connectTimeout(5)
                ->post($this->sheetEndpoints[$sheetName], $data);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'sheet' => $sheetName,
                    'response' => $response->json()
                ];
            }

            throw new \Exception($response->body());

        } catch (\Exception $e) {
            Log::error("Sheet [$sheetName] update failed", [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'sheet' => $sheetName,
                'message' => $e->getMessage()
            ];
        }
    }


}