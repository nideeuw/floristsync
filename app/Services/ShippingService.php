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

    public const COURIER_NAMES = [
        'jne' => 'JNE',
        'tiki' => 'TIKI',
        'pos' => 'POS Indonesia',
        'sicepat' => 'SiCepat',
        'jnt' => 'J&T',
    ];

    public const SERVICE_NAMES = [
        'jne' => [
            'ctc' => 'City Courier',
            'ctcyes' => 'City Courier YES',
            'reg' => 'Reguler',
            'yes' => 'Yakin Esok Sampai',
            'oke' => 'Ongkos Kirim Ekonomis',
            'jtr' => 'Trucking',
            'jtr<130' => 'Trucking (<130kg)',
            'jtr>130' => 'Trucking (>130kg)',
            'jtr250' => 'Trucking (250kg)',
            'sps' => 'Super Speed',
            'pelik' => 'Amplop Pra Bayar',
        ],
        'tiki' => [
            'reg' => 'Reguler',
            'eco' => 'Economy',
            'ons' => 'Over Night Service',
            'tds' => 'Two Day Service',
            'hds' => 'Holiday Delivery Service',
            'sds' => 'Same Day Service',
            'trc' => 'Trucking',
        ],
        'pos' => [
            'pos reguler' => 'Reguler',
            'pos nextday' => 'Nextday',
            'pos sameday' => 'Sameday',
            'pos kargo' => 'Kargo',
            'pos kilat khusus' => 'Kilat Khusus',
            'paket kilat khusus' => 'Kilat Khusus',
            'express next day' => 'Express Next Day',
            'reguler' => 'Reguler',
            'nextday' => 'Nextday',
            'sameday' => 'Sameday',
            'kargo' => 'Kargo',
            'kilat khusus' => 'Kilat Khusus',
        ],
        'sicepat' => [
            'siunt' => 'SiUntung',
            'best' => 'Besok Sampai Tujuan',
            'gokil' => 'Cargo Kilat',
            'halo' => 'HALO',
            'hebat' => 'Hemat Banget',
            'cargo' => 'Cargo Kilat',
            'reg' => 'Reguler',
        ],
        'jnt' => [
            'ez' => 'Reguler',
            'j&t super' => 'Super',
            'super' => 'Super',
            'j&t eco' => 'Economy',
            'eco' => 'Economy',
            'j&t cargo' => 'Cargo',
            'cargo' => 'Cargo',
            'reg' => 'Reguler',
        ],
    ];

    public static function formatCourierName(?string $courier, ?string $rawName = null): string
    {
        $key = strtolower(trim((string) $courier));

        if (isset(self::COURIER_NAMES[$key])) {
            return self::COURIER_NAMES[$key];
        }

        if (! empty($rawName)) {
            $trimmed = trim($rawName);
            if (preg_match('/\(([^)]+)\)/', $trimmed, $m)) {
                $candidate = strtolower(trim($m[1]));
                if (isset(self::COURIER_NAMES[$candidate])) {
                    return self::COURIER_NAMES[$candidate];
                }
            }

            return $trimmed;
        }

        return strtoupper($key);
    }

    public static function formatEstimate(?string $etd): ?string
    {
        if ($etd === null) {
            return null;
        }

        $raw = trim($etd);
        if ($raw === '') {
            return null;
        }

        $cleaned = trim((string) preg_replace('/\b(hari|days?|hours?|jam)\b/i', '', $raw));
        $cleaned = trim($cleaned, "- \t\n\r\0\x0B");

        if ($cleaned === '') {
            return null;
        }

        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $cleaned, $matches)) {
            $min = (int) $matches[1];
            $max = (int) $matches[2];

            if ($min === 1 && $max === 1) {
                return '1 day';
            }

            if ($min === 0 && $max === 0) {
                return 'Same day';
            }

            return "{$min}-{$max} days";
        }

        if (preg_match('/^\d+$/', $cleaned)) {
            $num = (int) $cleaned;
            if ($num === 1) {
                return '1 day';
            }
            if ($num === 0) {
                return 'Same day';
            }

            return "{$num} days";
        }

        return $raw;
    }

    public static function formatServiceLabel(
        ?string $courier,
        ?string $service,
        ?string $description = null,
        ?string $etd = null,
        ?string $courierName = null
    ): string {
        $courierKey = strtolower(trim((string) $courier));
        $serviceCode = trim((string) $service);
        $serviceKey = strtolower($serviceCode);
        $courierTitle = self::formatCourierName($courier, $courierName);

        $desc = trim((string) $description);

        if ($desc === '' || strcasecmp($desc, $serviceCode) === 0) {
            $desc = self::SERVICE_NAMES[$courierKey][$serviceKey] ?? $serviceCode;
        }

        $name = $desc;

        if ($courierTitle !== '') {
            $lowerName = strtolower($name);
            $lowerCourier = strtolower($courierTitle);
            $shortCourier = strtolower(self::COURIER_NAMES[$courierKey] ?? $courierKey);

            if (strcasecmp($name, $courierTitle) === 0 || ($shortCourier !== '' && strcasecmp($name, $shortCourier) === 0)) {
                $name = '';
            } elseif ($lowerCourier !== '' && str_starts_with($lowerName, $lowerCourier . ' ')) {
                $name = trim(substr($name, strlen($lowerCourier)));
            } elseif ($shortCourier !== '' && str_starts_with($lowerName, $shortCourier . ' ')) {
                $name = trim(substr($name, strlen($shortCourier)));
            } elseif ($courierKey !== '' && str_starts_with($lowerName, $courierKey . ' ')) {
                $name = trim(substr($name, strlen($courierKey)));
            }

            $name = $name !== '' ? "{$courierTitle} {$name}" : $courierTitle;
        }

        if ($name === '') {
            $name = $courierTitle ?: $serviceCode;
        }

        $formattedEtd = self::formatEstimate($etd);
        if ($formattedEtd !== null && $formattedEtd !== '') {
            $name .= " ({$formattedEtd})";
        }

        return trim($name);
    }

    public function calculateCost(int $destinationId, int $weightGram, string $courier = 'jne'): array
    {
        $origin = (int) app(SettingService::class)->get('shipping_origin_id', config('services.rajaongkir.origin_id'));

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
            ->map(function ($row) use ($courier) {
                $code = (string) ($row['code'] ?? $courier);
                $service = (string) $row['service'];
                $description = (string) ($row['description'] ?? '');
                $etd = (string) ($row['etd'] ?? '');
                $courierRaw = (string) ($row['name'] ?? '');

                return [
                    'courier' => $courierRaw,
                    'code' => $code,
                    'service' => $service,
                    'description' => $description,
                    'cost' => (int) $row['cost'],
                    'etd' => $etd,
                    'label' => self::formatServiceLabel($code, $service, $description, $etd, $courierRaw),
                ];
            })
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
