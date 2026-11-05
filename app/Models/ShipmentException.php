<?php

namespace App\Models;

use App\Enums\ExceptionSeverity;
use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentException extends Model
{
    protected $table = 'shipment_exceptions';

    protected $fillable = [
        'shipment_id', 'tracking_event_id', 'reported_by', 'resolved_by',
        'type', 'severity', 'status', 'description', 'detected_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExceptionType::class,
            'severity' => ExceptionSeverity::class,
            'status' => ExceptionStatus::class,
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function trackingEvent(): BelongsTo
    {
        return $this->belongsTo(TrackingEvent::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
