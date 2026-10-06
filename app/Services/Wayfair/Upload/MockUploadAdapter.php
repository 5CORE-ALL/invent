<?php

namespace App\Services\Wayfair\Upload;

class MockUploadAdapter implements WayfairUploadAdapter
{
    public function upload(string $absolutePath, int $uploadId): WayfairUploadResult
    {
        return $this->fromConfig($uploadId);
    }

    public function checkStatus(?string $reference, array $context = []): WayfairUploadResult
    {
        $result = (string) config('wayfair_upload.mock_result', 'success');
        if ($result === 'processing') {
            $ref = $reference ?: ('MOCK-'.($context['upload_id'] ?? '0'));

            return WayfairUploadResult::processing($ref, 'Still processing');
        }

        return WayfairUploadResult::success($reference ?: 'MOCK', 'Mock processing succeeded');
    }

    private function fromConfig(int $uploadId): WayfairUploadResult
    {
        return match ((string) config('wayfair_upload.mock_result', 'success')) {
            'fail' => WayfairUploadResult::failed('Mock transient failure', true),
            'permanent' => WayfairUploadResult::failed('Invalid template', false),
            'auth' => WayfairUploadResult::authRequired('Wayfair requires manual authentication (MFA/CAPTCHA). Unattended upload was not attempted.'),
            'processing' => WayfairUploadResult::processing('MOCK-'.$uploadId, 'Mock accepted the file'),
            default => WayfairUploadResult::success('MOCK-'.$uploadId, 'Mock upload succeeded'),
        };
    }
}
