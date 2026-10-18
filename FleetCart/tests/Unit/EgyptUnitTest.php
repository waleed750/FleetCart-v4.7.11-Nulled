<?php

namespace Tests\Unit;

use Modules\Egypt\Services\GovernorateList;
use Modules\Egypt\Services\MetaConversionsApi;
use Modules\Egypt\Services\Tracking;
use PHPUnit\Framework\TestCase;

class EgyptUnitTest extends TestCase
{
    public function test_there_are_27_governorates_with_arabic_and_english_names(): void
    {
        $this->assertCount(27, GovernorateList::ALL);

        foreach (GovernorateList::ALL as $code => [$en, $ar, $cost]) {
            $this->assertMatchesRegularExpression('/^[A-Z]{1,3}$/', $code);
            $this->assertNotSame('', $en);
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $ar, "{$code} needs an Arabic name");
            $this->assertGreaterThan(0, $cost);
        }

        $this->assertSame('القاهرة', GovernorateList::names('ar')['C']);
        $this->assertSame('Cairo', GovernorateList::names('en')['C']);
    }


    public function test_phone_numbers_are_normalised_for_meta(): void
    {
        $capi = new MetaConversionsApi(new Tracking());

        $this->assertSame('201012345678', $capi->normalisePhone('01012345678'));
        $this->assertSame('201012345678', $capi->normalisePhone('+20 101 234 5678'));
        $this->assertSame('201012345678', $capi->normalisePhone('00201012345678'));
        $this->assertSame('201112345678', $capi->normalisePhone('0111-234-5678'));
    }
}
