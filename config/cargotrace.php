<?php

/*
|--------------------------------------------------------------------------
| CargoTrace global scenarios (Group A, B, C)
|--------------------------------------------------------------------------
| Every shipment carries a `scenario` (A/B/C). Delay rules follow the
| scenario's threshold. Severity bands are derived from the threshold:
|   LOW    : delay <= threshold / 2   (A: 0-6h,  B: 0-9h,  C: 0-12h)
|   MEDIUM : delay <= threshold * 2   (A: <=24h, B: <=36h, C: <=48h)
|   HIGH   : delay >  threshold * 2
| waypoints: [location code, fraction of the voyage at which the ship passes].
*/
return [
    'default_scenario' => env('CARGOTRACE_SCENARIO', 'A'),

    'groups' => [
        'A' => [
            'name' => 'Southeast Asia -> Europe',
            'origin_regions' => ['SEA'],
            'destination_region' => 'EUR',
            'waypoints' => [['LKCMB', 0.3], ['EGPSD', 0.6]],
            'transit_days' => [26, 34],
            'delay_threshold_hours' => (int) env('DELAY_THRESHOLD_A', 12),
            'primary_exception' => 'CUSTOMS_HOLD',
            'kpi' => 'on_time_delivery_rate',
        ],
        'B' => [
            'name' => 'Asia -> North America',
            'origin_regions' => ['EAS', 'SEA'],
            'destination_region' => 'NAM',
            'waypoints' => [['USHNL', 0.5]],
            'transit_days' => [14, 20],
            'delay_threshold_hours' => (int) env('DELAY_THRESHOLD_B', 18),
            'primary_exception' => 'WEATHER_DELAY',
            'kpi' => 'average_delay_duration',
        ],
        'C' => [
            'name' => 'Southeast Asia -> Australia',
            'origin_regions' => ['SEA'],
            'destination_region' => 'AUS',
            'waypoints' => [['AUDRW', 0.5]],
            'transit_days' => [8, 14],
            'delay_threshold_hours' => (int) env('DELAY_THRESHOLD_C', 24),
            'primary_exception' => 'DOCUMENTATION_ISSUE',
            'kpi' => 'exception_resolution_time',
        ],
    ],
];
