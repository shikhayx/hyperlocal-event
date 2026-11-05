<?php

declare(strict_types=1);

namespace HiEvents\Console\Commands\Hyperlocal;

final class CityConfig
{
    public const CITY = 'Mumbai';

    public const CENTER_LAT = 19.0760;

    public const CENTER_LNG = 72.8777;

    public const TIMEZONE = 'Asia/Kolkata';

    public const CURRENCY = 'INR';

    public const AREAS = [
        'Bandra West' => [
            'name' => 'Bandra West',
            'lat' => 19.0596,
            'lng' => 72.8295,
            'locality' => 'Bandra West, Mumbai',
            'postal_code' => '400050',
        ],
        'BKC' => [
            'name' => 'Bandra Kurla Complex (BKC)',
            'lat' => 19.0688,
            'lng' => 72.8703,
            'locality' => 'G Block BKC, Bandra East, Mumbai',
            'postal_code' => '400051',
        ],
        'Lower Parel' => [
            'name' => 'Lower Parel',
            'lat' => 19.0016,
            'lng' => 72.8302,
            'locality' => 'Lower Parel, Mumbai',
            'postal_code' => '400013',
        ],
        'Andheri West' => [
            'name' => 'Andheri West',
            'lat' => 19.1136,
            'lng' => 72.8292,
            'locality' => 'Lokhandwala, Andheri West, Mumbai',
            'postal_code' => '400053',
        ],
        'Powai' => [
            'name' => 'Powai',
            'lat' => 19.1176,
            'lng' => 72.9060,
            'locality' => 'Hiranandani Gardens, Powai, Mumbai',
            'postal_code' => '400076',
        ],
        'Dadar' => [
            'name' => 'Dadar',
            'lat' => 19.0178,
            'lng' => 72.8478,
            'locality' => 'Shivaji Park, Dadar, Mumbai',
            'postal_code' => '400028',
        ],
        'Juhu' => [
            'name' => 'Juhu',
            'lat' => 19.1075,
            'lng' => 72.8263,
            'locality' => 'Juhu Tara Road, Juhu, Mumbai',
            'postal_code' => '400049',
        ],
        'Colaba' => [
            'name' => 'Colaba',
            'lat' => 18.9220,
            'lng' => 72.8346,
            'locality' => 'Colaba Causeway, South Mumbai',
            'postal_code' => '400005',
        ],
        'Ghatkopar' => [
            'name' => 'Ghatkopar East',
            'lat' => 19.0860,
            'lng' => 72.9090,
            'locality' => 'Vallabh Baug Lane, Ghatkopar East, Mumbai',
            'postal_code' => '400077',
        ],
        'Goregaon East' => [
            'name' => 'Goregaon East',
            'lat' => 19.1663,
            'lng' => 72.8593,
            'locality' => 'Aarey Colony, Goregaon East, Mumbai',
            'postal_code' => '400065',
        ],
    ];

    public const DISTANT_AREAS = [
        'Kalyan' => [
            'name' => 'Kalyan West',
            'lat' => 19.2437,
            'lng' => 73.1355,
            'locality' => 'Kalyan West, Mumbai Metropolitan Region',
            'postal_code' => '421301',
        ],
        'Kharghar' => [
            'name' => 'Kharghar & Panvel Hills',
            'lat' => 18.9300,
            'lng' => 73.1200,
            'locality' => 'Panvel-Kharghar Foothills, Navi Mumbai',
            'postal_code' => '410206',
        ],
        'Virar' => [
            'name' => 'Virar Coastal Edge',
            'lat' => 19.4674,
            'lng' => 72.8041,
            'locality' => 'Arnala Beach Road, Virar West',
            'postal_code' => '401302',
        ],
        'Karjat' => [
            'name' => 'Karjat Valley Camp',
            'lat' => 18.9100,
            'lng' => 73.3280,
            'locality' => 'Karjat Foothills, Raigad District',
            'postal_code' => '410201',
        ],
    ];

    public static function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusKm * $c, 2);
    }
}
