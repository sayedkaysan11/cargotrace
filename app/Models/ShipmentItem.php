<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentItem extends Model
{
    protected $fillable = ['shipment_id', 'description', 'hs_code', 'quantity', 'weight_kg', 'volume_cbm'];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
