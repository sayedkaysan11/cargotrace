<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    public const TYPES = ['port', 'airport', 'warehouse', 'city'];

    public const REGIONS = ['SEA', 'EAS', 'EUR', 'NAM', 'AUS', 'TRANSIT'];

    protected $fillable = [
        'name', 'code', 'type', 'city', 'country', 'country_code',
        'region', 'latitude', 'longitude',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }
}
