<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;

/**
 * Auto-discovered by Laravel (App\Models\Shipment -> App\Policies\ShipmentPolicy).
 * Controllers call $this->authorize('view', $shipment) etc.
 */
class ShipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // the list itself is filtered with Shipment::visibleTo($user)
    }

    public function view(User $user, Shipment $shipment): bool
    {
        if ($user->isCustomer()) {
            return $shipment->customer_id === $user->id;
        }

        return $user->isAdmin() || $user->isOperator();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function update(User $user, Shipment $shipment): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function addTrackingEvent(User $user, Shipment $shipment): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function delete(User $user, Shipment $shipment): bool
    {
        return $user->isAdmin();
    }
}
