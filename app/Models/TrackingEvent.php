<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use App\Enums\TrackingEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingEvent extends Model
{
    protected $fillable = [
        'shipment_id', 'location_id', 'created_by', 'event_type', 'status',
        'is_exception', 'occurred_at', 'latitude', 'longitude', 'description',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => TrackingEventType::class,
            'status' => ShipmentStatus::class,
            'is_exception' => 'boolean',
            'occurred_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
