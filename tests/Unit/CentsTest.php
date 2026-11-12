<?php

namespace Tests\Unit;

use App\Services\Samo\Cents;
use PHPUnit\Framework\TestCase;

class CentsTest extends TestCase
{
    public function test_parses_samo_money_strings(): void
    {
        $this->assertSame(112113, Cents::from('1121.1300'));
        $this->assertSame(45, Cents::from('0.45'));
        $this->assertSame(-1050, Cents::from('-10.5'));
        $this->assertSame(1, Cents::from('0.005'));
        $this->assertSame(0, Cents::from(null));
        $this->assertSame('-10.50', Cents::str(-1050));
        $this->assertSame('0.05', Cents::str(5));
    }
}
