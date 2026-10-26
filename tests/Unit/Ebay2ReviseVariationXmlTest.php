<?php

namespace Tests\Unit;

use App\Services\Ebay2ApiService;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

class Ebay2ReviseVariationXmlTest extends TestCase
{
    public function test_ampersand_in_variation_specifics_reaches_the_request_unchanged(): void
    {
        $xml = new SimpleXMLElement('<Item/>');
        $nvl = $xml->addChild('NameValueList');
        $nvl->addChild('Name', Ebay2ApiService::xmlText('Version'));
        $nvl->addChild('Value', Ebay2ApiService::xmlText('Guitar Holder ABS Made Black & Red 1PC'));

        $parsed = new SimpleXMLElement((string) $xml->asXML());
        $this->assertSame('Guitar Holder ABS Made Black & Red 1PC', (string) $parsed->NameValueList->Value);
        $this->assertStringContainsString('Black &amp; Red', (string) $xml->asXML());
    }

    public function test_plain_values_and_markup_characters_are_escaped_once(): void
    {
        $this->assertSame('Chrome Black', Ebay2ApiService::xmlText('Chrome Black'));
        $this->assertSame('A &amp; B &lt;x&gt;', Ebay2ApiService::xmlText('A & B <x>'));
        $this->assertSame('5"', Ebay2ApiService::xmlText('5"'));
    }
}
