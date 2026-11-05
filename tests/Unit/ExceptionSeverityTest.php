<?php

namespace Tests\Unit;

use App\Enums\ExceptionSeverity;
use Tests\TestCase;

class ExceptionSeverityTest extends TestCase
{
    public function test_group_a_bands_with_12h_threshold(): void
    {
        config(['cargotrace.groups.A.delay_threshold_hours' => 12]);

        $this->assertSame(ExceptionSeverity::Low, ExceptionSeverity::fromDelayHours(6, 'A'));
        $this->assertSame(ExceptionSeverity::Medium, ExceptionSeverity::fromDelayHours(7, 'A'));
        $this->assertSame(ExceptionSeverity::Medium, ExceptionSeverity::fromDelayHours(24, 'A'));
        $this->assertSame(ExceptionSeverity::High, ExceptionSeverity::fromDelayHours(25, 'A'));
    }

    public function test_group_c_uses_its_own_24h_threshold(): void
    {
        config(['cargotrace.groups.C.delay_threshold_hours' => 24]);

        $this->assertSame(ExceptionSeverity::Low, ExceptionSeverity::fromDelayHours(12, 'C'));
        $this->assertSame(ExceptionSeverity::Medium, ExceptionSeverity::fromDelayHours(48, 'C'));
        $this->assertSame(ExceptionSeverity::High, ExceptionSeverity::fromDelayHours(49, 'C'));
    }
}
