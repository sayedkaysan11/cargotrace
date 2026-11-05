<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carrier extends Model
{
    protected $fillable = ['name', 'code', 'contact_email', 'phone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
