<?php

namespace Tests\Unit;

use App\Services\BatchCooStampService;
use Tests\TestCase;

class BatchCooStampServiceTest extends TestCase
{
    public function test_presets_map_common_country_codes(): void
    {
        $svc = new BatchCooStampService();

        $this->assertSame('MADE IN CHINA', $svc->label('CN'));
        $this->assertSame('MADE IN CHINA', $svc->label('china'));
        $this->assertSame('MADE IN INDIA', $svc->label('India'));
        $this->assertSame('MADE IN USA', $svc->label('United States'));
        $this->assertSame('MADE IN VIETNAM', $svc->label('VN'));
        $this->assertSame('MADE IN CHINA', $svc->label(null));
        $this->assertSame('MADE IN JAPAN', $svc->label('ignored', 'Made in Japan'));
    }

    public function test_stamp_returns_jpeg_with_coo_bar(): void
    {
        $svc = new BatchCooStampService();
        $src = imagecreatetruecolor(200, 200);
        $red = imagecolorallocate($src, 180, 40, 40);
        imagefilledrectangle($src, 0, 0, 199, 199, $red);
        ob_start();
        imagejpeg($src, null, 90);
        $bytes = (string) ob_get_clean();
        imagedestroy($src);

        $stamped = $svc->stamp($bytes, $svc->label('china'), 'BATCH 99');
        $info = getimagesizefromstring($stamped);

        $this->assertNotFalse($info);
        $this->assertSame('image/jpeg', $info['mime']);
        $this->assertGreaterThan(400, $info[0]);
        $this->assertSame($info[0], $info[1]);
        $this->assertNotSame($bytes, $stamped);
    }
}
