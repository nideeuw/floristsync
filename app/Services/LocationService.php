<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * Thin proxy to OpenStreetMap Nominatim (address search and reverse geocoding).
 * The public Nominatim server allows 1 request per second in total, so results are
 * cached and live calls are rate limited globally.
 */
class LocationService
{
    public function search(string $keyword): array
    {
        $keyword = trim($keyword);

        return Cache::remember('osm_search_v2_' . md5(mb_strtolower($keyword)), now()->addDay(), function () use ($keyword) {
            $rows = $this->request('search', [
                'q' => $keyword,
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'limit' => 6,
                'countrycodes' => 'id',
                'accept-language' => 'id,en',
            ]);

            return collect($rows)
                ->map(fn ($row) => $this->format((array) $row))
                ->filter()
                ->values()
                ->all();
        });
    }

    public function reverse(float $lat, float $lng): array
    {
        $key = 'osm_reverse_v2_' . number_format($lat, 5, '.', '') . '_' . number_format($lng, 5, '.', '');

        return Cache::remember($key, now()->addDay(), function () use ($lat, $lng) {
            $row = $this->request('reverse', [
                'lat' => $lat,
                'lon' => $lng,
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'zoom' => 18,
                'accept-language' => 'id,en',
            ]);

            if ($row === [] || isset($row['error'])) {
                return [];
            }

            return $this->format($row) ?? [];
        });
    }

    private function format(array $row): ?array
    {
        if (! isset($row['lat'], $row['lon'], $row['display_name'])) {
            return null;
        }

        $address = is_array($row['address'] ?? null) ? $row['address'] : [];

        $postcode = null;

        if (preg_match('/\b\d{5}\b/', (string) ($address['postcode'] ?? ''), $match)) {
            $postcode = $match[0];
        }

        $street = trim(implode(' ', array_filter([
            (string) ($address['road'] ?? ''),
            (string) ($address['house_number'] ?? ''),
        ])));

        return [
            'label' => mb_substr((string) $row['display_name'], 0, 500),
            'street' => mb_substr($street, 0, 255),
            'postcode' => $postcode,
            'lat' => round((float) $row['lat'], 7),
            'lng' => round((float) $row['lon'], 7),
        ];
    }

    private function request(string $path, array $query): array
    {
        foreach (['base_url', 'user_agent'] as $key) {
            if (blank(config("services.nominatim.$key"))) {
                Log::error("Nominatim {$key} is not configured");
                throw new RuntimeException('Location service is not configured. Please contact the store.');
            }
        }

        // Nominatim usage policy: at most 1 request per second in total.
        if (! RateLimiter::attempt('nominatim-global', 1, fn () => true, 1)) {
            throw new RuntimeException('Map search is busy. Please try again in a moment.');
        }

        try {
            $res = Http::baseUrl((string) config('services.nominatim.base_url'))
                ->withUserAgent((string) config('services.nominatim.user_agent'))
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->get($path, $query);
        } catch (ConnectionException $e) {
            Log::error('Nominatim connection error', ['message' => $e->getMessage()]);
            throw new RuntimeException('Location service is unavailable. Please try again later.', 0, $e);
        }

        if ($res->failed()) {
            Log::error('Nominatim error', ['status' => $res->status(), 'body' => $res->body()]);

            throw new RuntimeException(
                $res->status() === 429
                    ? 'Map search is busy. Please try again in a moment.'
                    : 'Location service is unavailable. Please try again later.'
            );
        }

        $data = $res->json();

        return is_array($data) ? $data : [];
    }
}
