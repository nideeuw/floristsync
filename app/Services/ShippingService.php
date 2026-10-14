<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ShippingService
{
    private const DESTINATION_FIELDS = [
        'id',
        'label',
        'province_name',
        'city_name',
        'district_name',
        'subdistrict_name',
        'zip_code',
    ];

    private const TRACKING_UNAVAILABLE = 'Tracking information is not available for this shipment yet.';

    public function searchDestination(string $keyword): array
    {
        $keyword = trim($keyword);

        return Cache::remember('ro_destination_v2_' . md5(mb_strtolower($keyword)), now()->addDay(), function () use ($keyword) {
            $rows = $this->request(
                fn(PendingRequest $http) => $http->get('destination/domestic-destination', [
                    'search' => $keyword,
                    'limit' => 10,
                    'offset' => 0,
                ]),
                emptyOnStatus: [400, 404],
            );

            return collect($rows)
                ->map(fn($row) => array_intersect_key((array) $row, array_flip(self::DESTINATION_FIELDS)))
                ->values()
                ->all();
        });
    }

    public function calculateCost(int $destinationId, int $weightGram, string $courier = 'jne'): array
    {
        $origin = (int) config('services.rajaongkir.origin_id');

        if ($origin <= 0) {
            Log::error('RajaOngkir origin_id is not configured');
            throw new RuntimeException('Shipping origin is not configured. Please contact the store.');
        }

        $weight = max(1, $weightGram);
        $courier = strtolower($courier);

        $cacheKey = 'ro_cost_' . md5(implode('|', [$origin, $destinationId, $weight, $courier]));

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $rows = $this->request(
            fn(PendingRequest $http) => $http->asForm()->post('calculate/domestic-cost', [
                'origin' => $origin,
                'destination' => $destinationId,
                'weight' => $weight,
                'courier' => $courier,
            ]),
            emptyOnStatus: [400, 404],
        );

        $options = collect($rows)
            ->filter(fn($row) => isset($row['service'], $row['cost']))
            ->map(fn($row) => [
                'courier' => (string) ($row['name'] ?? ''),
                'code' => (string) ($row['code'] ?? $courier),
                'service' => (string) $row['service'],
                'description' => (string) ($row['description'] ?? ''),
                'cost' => (int) $row['cost'],
                'etd' => (string) ($row['etd'] ?? ''),
            ])
            ->sortBy('cost')
            ->values()
            ->all();

        if ($options !== []) {
            Cache::put($cacheKey, $options, now()->addMinutes(10));
        }

        return $options;
    }

    public function getServiceCost(int $destinationId, int $weightGram, string $courier, string $service): int
    {
        foreach ($this->calculateCost($destinationId, $weightGram, $courier) as $option) {
            if (strcasecmp($option['service'], $service) === 0) {
                return $option['cost'];
            }
        }

        throw new RuntimeException('The selected shipping service is not available.');
    }

    public function trackWaybill(string $trackingNumber, string $courier, ?string $receiverPhone = null): array
    {
        $params = [
            'awb' => trim($trackingNumber),
            'courier' => strtolower($courier),
        ];

        $lastDigits = substr(preg_replace('/\D+/', '', (string) $receiverPhone), -5);

        if ($lastDigits !== '') {
            $params['last_phone_number'] = $lastDigits;
        }

        return Cache::remember('ro_waybill_' . md5(implode('|', $params)), now()->addMinutes(5), function () use ($params) {
            $data = $this->request(
                fn(PendingRequest $http) => $http->asForm()->withQueryParameters($params)->post('track/waybill', $params),
                unavailableMessage: self::TRACKING_UNAVAILABLE,
            );

            if ($data === []) {
                throw new RuntimeException(self::TRACKING_UNAVAILABLE);
            }

            return $data;
        });
    }

    public function calculateWeight(array $items): int
    {
        $totalQty = (int) collect($items)->sum(fn($i) => (int) $i['qty']);

        return max(1, $totalQty * (int) config('floristsync.default_item_weight_gram'));
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.rajaongkir.base_url'))
            ->withHeaders(['key' => (string) config('services.rajaongkir.api_key')])
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15);
    }

    /**
     * Send one request and return the "data" part of the response.
     *
     * @param  callable(PendingRequest): Response  $call
     * @param  int[]  $emptyOnStatus  Statuses that mean "no result" and return an empty array.
     * @param  string|null  $unavailableMessage  Message for 400/404/422 when the status is not in $emptyOnStatus.
     */
    private function request(callable $call, array $emptyOnStatus = [], ?string $unavailableMessage = null): array
    {
        foreach (['base_url', 'api_key'] as $key) {
            if (blank(config("services.rajaongkir.$key"))) {
                Log::error("RajaOngkir {$key} is not configured");
                throw new RuntimeException('Shipping service is not configured. Please contact the store.');
            }
        }

        try {
            $res = $call($this->client());
        } catch (ConnectionException $e) {
            Log::error('RajaOngkir connection error', ['message' => $e->getMessage()]);
            throw new RuntimeException('Shipping service is unavailable. Please try again later.', 0, $e);
        }

        $status = $res->failed() ? $res->status() : (int) $res->json('meta.code', 200);

        if ($status < 400) {
            $data = $res->json('data');

            return is_array($data) ? $data : [];
        }

        Log::error('RajaOngkir error', ['status' => $status, 'body' => $res->body()]);

        if (in_array($status, $emptyOnStatus, true)) {
            return [];
        }

        if ($unavailableMessage !== null && in_array($status, [400, 404, 422], true)) {
            throw new RuntimeException($unavailableMessage);
        }

        throw new RuntimeException(match (true) {
            in_array($status, [401, 403], true) => 'Shipping service is not configured correctly. Please contact the store.',
            $status === 429 => 'Shipping service is busy. Please try again in a moment.',
            default => 'Shipping service is unavailable. Please try again later.',
        });
    }
}
