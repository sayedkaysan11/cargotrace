<?php

namespace Tests\Unit;

use App\Enums\ExceptionSeverity;
use App\Services\EtaService;
use Carbon\Carbon;
use Tests\TestCase;

class EtaServiceTest extends TestCase
{
    private function eval(int $lateMinutes, string $scenario): array
    {
        $expected = Carbon::parse('2026-06-01 12:00:00');

        return (new EtaService())->evaluate($expected, $expected->copy()->addMinutes($lateMinutes), $scenario);
    }

    public function test_on_time_or_early_is_not_delayed(): void
    {
        $this->assertFalse($this->eval(0, 'A')['is_delayed']);
        $this->assertFalse($this->eval(-300, 'A')['is_delayed']);
        $this->assertNull($this->eval(0, 'A')['delay_severity']);
    }

    public function test_any_positive_delay_is_flagged(): void
    {
        $r = $this->eval(5, 'A');

        $this->assertTrue($r['is_delayed']);
        $this->assertSame(5, $r['delay_minutes']);
        $this->assertSame(ExceptionSeverity::Low, $r['delay_severity']);
    }

    public function test_group_a_bands(): void
    {
        config(['cargotrace.groups.A.delay_threshold_hours' => 12]);

        $this->assertSame(ExceptionSeverity::Low, $this->eval(6 * 60, 'A')['delay_severity']);
        $this->assertSame(ExceptionSeverity::Medium, $this->eval(6 * 60 + 1, 'A')['delay_severity']);
        $this->assertSame(ExceptionSeverity::Medium, $this->eval(24 * 60, 'A')['delay_severity']);
        $this->assertSame(ExceptionSeverity::High, $this->eval(24 * 60 + 1, 'A')['delay_severity']);
    }

    public function test_same_delay_has_different_severity_per_scenario(): void
    {
        config([
            'cargotrace.groups.A.delay_threshold_hours' => 12,
            'cargotrace.groups.C.delay_threshold_hours' => 24,
        ]);

        // 30 hours late: HIGH for group A (limit 24h) but only MEDIUM for group C (limit 48h)
        $this->assertSame(ExceptionSeverity::High, $this->eval(30 * 60, 'A')['delay_severity']);
        $this->assertSame(ExceptionSeverity::Medium, $this->eval(30 * 60, 'C')['delay_severity']);
    }
}
