<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IpGeolocationService
{
    /**
     * Resolve an IP address to geographic coordinates.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function geolocate(string $ip): ?array
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        /** @var array{hit: true, result: ?array}|null $cached */
        $cached = Cache::get("geo:{$ip}");

        if ($cached !== null) {
            return $cached['result'];
        }

        $result = null;

        try {
            $response = Http::timeout(5)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'status,lat,lon',
            ]);

            if ($response->successful() && $response->json('status') === 'success') {
                $result = [
                    'latitude' => (float) $response->json('lat'),
                    'longitude' => (float) $response->json('lon'),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning("IP geolocation failed for {$ip}: {$e->getMessage()}");
        }

        Cache::put("geo:{$ip}", ['hit' => true, 'result' => $result], 86400);

        return $result;
    }
}
