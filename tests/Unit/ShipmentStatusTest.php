<?php

namespace Tests\Unit;

use App\Enums\ShipmentStatus;
use PHPUnit\Framework\TestCase;

class ShipmentStatusTest extends TestCase
{
    public function test_valid_forward_transition(): void
    {
        $this->assertTrue(ShipmentStatus::Created->canTransitionTo(ShipmentStatus::Booked));
        $this->assertTrue(ShipmentStatus::InTransit->canTransitionTo(ShipmentStatus::Delivered));
    }

    public function test_cannot_skip_or_go_backwards(): void
    {
        $this->assertFalse(ShipmentStatus::Created->canTransitionTo(ShipmentStatus::InTransit));
        $this->assertFalse(ShipmentStatus::Delivered->canTransitionTo(ShipmentStatus::Created));
        $this->assertFalse(ShipmentStatus::InTransit->canTransitionTo(ShipmentStatus::Booked));
    }

    public function test_terminal_statuses(): void
    {
        $this->assertTrue(ShipmentStatus::Delivered->isTerminal());
        $this->assertTrue(ShipmentStatus::Cancelled->isTerminal());
        $this->assertFalse(ShipmentStatus::Booked->isTerminal());
    }
}
