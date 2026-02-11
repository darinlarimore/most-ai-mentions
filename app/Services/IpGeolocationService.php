<?php

namespace App\Services;

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

        try {
            $response = Http::timeout(5)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'status,lat,lon',
            ]);

            if ($response->successful() && $response->json('status') === 'success') {
                return [
                    'latitude' => (float) $response->json('lat'),
                    'longitude' => (float) $response->json('lon'),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning("IP geolocation failed for {$ip}: {$e->getMessage()}");
        }

        return null;
    }
}
