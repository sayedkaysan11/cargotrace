<?php

namespace App\Models;

use App\Enums\ExceptionSeverity;
use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'shipment_number', 'scenario', 'customer_id', 'carrier_id',
        'origin_location_id', 'destination_location_id', 'created_by',
        'cargo_type', 'total_weight_kg', 'total_volume_cbm', 'current_status',
        'expected_departure_at', 'expected_arrival_at', 'estimated_arrival_at',
        'actual_departure_at', 'actual_arrival_at',
        'is_delayed', 'delay_minutes', 'delay_severity',
    ];

    protected function casts(): array
    {
        return [
            'current_status' => ShipmentStatus::class,
            'delay_severity' => ExceptionSeverity::class,
            'expected_departure_at' => 'datetime',
            'expected_arrival_at' => 'datetime',
            'estimated_arrival_at' => 'datetime',
            'actual_departure_at' => 'datetime',
            'actual_arrival_at' => 'datetime',
            'is_delayed' => 'boolean',
            'delay_minutes' => 'integer',
        ];
    }

    public function delayThresholdHours(): int
    {
        return (int) config("cargotrace.groups.{$this->scenario}.delay_threshold_hours", 12);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(TrackingEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    /** Latest event that has coordinates = current location. */
    public function latestLocatedEvent(): HasOne
    {
        return $this->hasOne(TrackingEvent::class)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->latestOfMany('occurred_at');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ShipmentException::class);
    }

    /** Server-side authorization scope: customers only see their own shipments. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isCustomer() ? $query->where('customer_id', $user->id) : $query;
    }

    public function scopeDelayed(Builder $query): Builder
    {
        return $query->where('is_delayed', true);
    }
}
