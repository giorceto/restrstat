<?php

namespace Tests\Unit;

use App\Services\Filters;
use PHPUnit\Framework\TestCase;

class FiltersTest extends TestCase
{
    public function test_parses_both_date_formats(): void
    {
        $this->assertSame('2026-07-05', Filters::date('05.07.2026')?->toDateString());
        $this->assertSame('2026-07-05', Filters::date('5.7.2026')?->toDateString());
        $this->assertSame('2026-07-05', Filters::date('2026-07-05')?->toDateString());
        $this->assertNull(Filters::date('31.02.2026'));
        $this->assertNull(Filters::date('07/05/2026'));
        $this->assertNull(Filters::date(null));
    }
}
