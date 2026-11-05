<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        // [code, name, city, country, cc, region, lat, lng]
        $locations = [
            // Southeast Asia
            ['IDJKT', 'Tanjung Priok Port', 'Jakarta', 'Indonesia', 'ID', 'SEA', -6.1066, 106.8806],
            ['IDSUB', 'Tanjung Perak Port', 'Surabaya', 'Indonesia', 'ID', 'SEA', -7.2054, 112.7330],
            ['SGSIN', 'Port of Singapore', 'Singapore', 'Singapore', 'SG', 'SEA', 1.2644, 103.8200],
            ['MYPKG', 'Port Klang', 'Klang', 'Malaysia', 'MY', 'SEA', 3.0000, 101.3900],
            ['THLCH', 'Laem Chabang Port', 'Chonburi', 'Thailand', 'TH', 'SEA', 13.0827, 100.8830],
            ['VNSGN', 'Cat Lai Port', 'Ho Chi Minh City', 'Vietnam', 'VN', 'SEA', 10.7625, 106.7900],
            // East Asia
            ['CNSHA', 'Port of Shanghai', 'Shanghai', 'China', 'CN', 'EAS', 31.2304, 121.4737],
            ['CNSZX', 'Port of Shenzhen', 'Shenzhen', 'China', 'CN', 'EAS', 22.5431, 114.0579],
            ['HKHKG', 'Port of Hong Kong', 'Hong Kong', 'Hong Kong', 'HK', 'EAS', 22.3000, 114.1700],
            ['KRPUS', 'Port of Busan', 'Busan', 'South Korea', 'KR', 'EAS', 35.1028, 129.0403],
            ['JPYOK', 'Port of Yokohama', 'Yokohama', 'Japan', 'JP', 'EAS', 35.4437, 139.6380],
            ['TWKHH', 'Port of Kaohsiung', 'Kaohsiung', 'Taiwan', 'TW', 'EAS', 22.6163, 120.3000],
            // Transit waypoints
            ['LKCMB', 'Port of Colombo', 'Colombo', 'Sri Lanka', 'LK', 'TRANSIT', 6.9497, 79.8428],
            ['EGPSD', 'Port Said', 'Port Said', 'Egypt', 'EG', 'TRANSIT', 31.2653, 32.3019],
            ['USHNL', 'Port of Honolulu', 'Honolulu', 'United States', 'US', 'TRANSIT', 21.3069, -157.8583],
            ['AUDRW', 'Port of Darwin', 'Darwin', 'Australia', 'AU', 'TRANSIT', -12.4634, 130.8456],
            // Europe
            ['NLRTM', 'Port of Rotterdam', 'Rotterdam', 'Netherlands', 'NL', 'EUR', 51.9496, 4.1420],
            ['DEHAM', 'Port of Hamburg', 'Hamburg', 'Germany', 'DE', 'EUR', 53.5400, 9.9700],
            ['BEANR', 'Port of Antwerp', 'Antwerp', 'Belgium', 'BE', 'EUR', 51.2650, 4.3900],
            ['GBFXT', 'Port of Felixstowe', 'Felixstowe', 'United Kingdom', 'GB', 'EUR', 51.9500, 1.3100],
            ['FRLEH', 'Port of Le Havre', 'Le Havre', 'France', 'FR', 'EUR', 49.4800, 0.1100],
            // North America
            ['USLAX', 'Port of Los Angeles', 'Los Angeles', 'United States', 'US', 'NAM', 33.7292, -118.2620],
            ['USOAK', 'Port of Oakland', 'Oakland', 'United States', 'US', 'NAM', 37.8044, -122.2712],
            ['USSEA', 'Port of Seattle', 'Seattle', 'United States', 'US', 'NAM', 47.6062, -122.3321],
            ['CAVAN', 'Port of Vancouver', 'Vancouver', 'Canada', 'CA', 'NAM', 49.2827, -123.1207],
            ['USNYC', 'Port of New York and New Jersey', 'New York', 'United States', 'US', 'NAM', 40.6683, -74.0450],
            // Australia
            ['AUSYD', 'Port Botany', 'Sydney', 'Australia', 'AU', 'AUS', -33.9710, 151.2210],
            ['AUMEL', 'Port of Melbourne', 'Melbourne', 'Australia', 'AU', 'AUS', -37.8136, 144.9631],
            ['AUBNE', 'Port of Brisbane', 'Brisbane', 'Australia', 'AU', 'AUS', -27.3840, 153.1700],
            ['AUFRE', 'Fremantle Port', 'Fremantle', 'Australia', 'AU', 'AUS', -32.0569, 115.7439],
        ];

        foreach ($locations as [$code, $name, $city, $country, $cc, $region, $lat, $lng]) {
            Location::updateOrCreate(['code' => $code], [
                'name' => $name,
                'type' => 'port',
                'city' => $city,
                'country' => $country,
                'country_code' => $cc,
                'region' => $region,
                'latitude' => $lat,
                'longitude' => $lng,
            ]);
        }
    }
}
