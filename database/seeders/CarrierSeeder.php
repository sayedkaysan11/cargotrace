<?php

namespace Database\Seeders;

use App\Models\Carrier;
use Illuminate\Database\Seeder;

class CarrierSeeder extends Seeder
{
    /** Fictional carriers (demo data only). */
    public function run(): void
    {
        $carriers = [
            ['Nusantara Line', 'NUSL', 'ops@nusantaraline.example'],
            ['Straits Maritime', 'STRM', 'ops@straitsmaritime.example'],
            ['Orient Meridian Freight', 'ORMF', 'ops@orientmeridian.example'],
            ['Indochina Express Cargo', 'IDEC', 'ops@indochinaexpress.example'],
        ];

        foreach ($carriers as [$name, $code, $email]) {
            Carrier::updateOrCreate(['code' => $code], [
                'name' => $name,
                'contact_email' => $email,
                'is_active' => true,
            ]);
        }
    }
}
