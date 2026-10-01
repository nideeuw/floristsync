<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ShippingService
{
    /** Search destination city or district. */
    public function searchDestination(string $keyword): array
    {
        return Cache::remember('ro_destination_' . md5($keyword), now()->addDay(), function () use ($keyword) {
            $res = $this->client()->get('destination/domestic-destination', [
                'search' => $keyword,
                'limit' => 10,
                'offset' => 0,
            ]);

            return $this->extractData($res);
        });
    }

    /** Calculate shipping options. Weight in grams. */
    public function calculateCost(int $destinationId, int $weightGram, string $courier = 'jne'): array
    {
        $res = $this->client()->asForm()->post('calculate/domestic-cost', [
            'origin' => config('services.rajaongkir.origin_id'),
            'destination' => $destinationId,
            'weight' => max(1, $weightGram),
            'courier' => $courier,
        ]);

        return $this->extractData($res);
    }

    /**
     * Price of one service (e.g. "REG") for a courier.
     * Adjust the array keys ('service', 'cost') if the API response differs.
     */
    public function getServiceCost(int $destinationId, int $weightGram, string $courier, string $service): int
    {
        foreach ($this->calculateCost($destinationId, $weightGram, $courier) as $option) {
            if (strcasecmp((string) ($option['service'] ?? ''), $service) === 0) {
                return (int) $option['cost'];
            }
        }

        throw new RuntimeException('The selected shipping service is not available.');
    }

    /** Track a waybill. Check the docs for the parameters each courier needs. */
    public function trackWaybill(string $trackingNumber, string $courier): array
    {
        $res = $this->client()->asForm()->post('track/waybill', [
            'awb' => $trackingNumber,
            'courier' => $courier,
        ]);

        return $this->extractData($res);
    }

    /** Estimated parcel weight (the ERD has no weight column). */
    public function calculateWeight(array $items): int
    {
        $totalQty = (int) collect($items)->sum(fn ($i) => (int) $i['qty']);

        return max(1, $totalQty * (int) config('floristsync.default_item_weight_gram'));
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('services.rajaongkir.base_url'))
            ->withHeaders(['key' => config('services.rajaongkir.api_key')])
            ->acceptJson()
            ->timeout(15);
    }

    private function extractData(Response $res): array
    {
        if ($res->failed()) {
            Log::error('RajaOngkir error', ['status' => $res->status(), 'body' => $res->body()]);
            throw new RuntimeException('Shipping service is unavailable. Please try again later.');
        }

        return $res->json('data') ?? [];
    }
}
