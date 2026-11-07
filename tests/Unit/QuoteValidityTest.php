<?php

namespace Tests\Unit;

use App\Support\QuoteValidity;
use Tests\TestCase;

/** Ported from tests/unit/date.test.ts. */
class QuoteValidityTest extends TestCase
{
    public function test_gives_the_full_30_days_when_no_plan_ends_sooner(): void
    {
        $v = QuoteValidity::for('2026-11-06', [null, '2027-12-31']);
        $this->assertSame(['2026-12-06', 30, false], [$v['until']->toDateString(), $v['days'], $v['capped']]);
    }

    public function test_stops_at_the_earliest_plan_end_date(): void
    {
        $v = QuoteValidity::for('2026-11-06', ['2026-11-20', '2026-11-15']);
        $this->assertSame(['2026-11-15', 9, true], [$v['until']->toDateString(), $v['days'], $v['capped']]);
    }

    public function test_is_zero_on_the_last_day_and_negative_once_expired(): void
    {
        $this->assertSame(0, QuoteValidity::for('2026-11-06', ['2026-11-06'])['days']);
        $this->assertSame(-1, QuoteValidity::for('2026-11-06', ['2026-11-05'])['days']);
    }
}
