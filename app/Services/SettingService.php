<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SettingService
{
    public const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    private const CACHE_KEY = 'settings.all';

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->all()[$key] ?? null;

        return filled($value) ? $value : $default;
    }

    public function set(string $key, mixed $value): void
    {
        if (is_array($value)) {
            $value = json_encode($value);
        }

        Setting::updateOrCreate(
            ['setting_key' => $key],
            [
                'setting_value' => $value === null ? null : (string) $value,
                'is_active' => true,
            ]
        );

        Cache::forget(self::CACHE_KEY);
    }

    public function all(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => Setting::active()->pluck('setting_value', 'setting_key')->all()
        );
    }

    public function save(array $data): void
    {
        $before = array_intersect_key($this->all(), $data);

        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                $this->set($key, $value);
            }
        });

        app(AuditService::class)->logChanges('settings.updated', 'settings', null, $before, $data);
    }

    public function form(): array
    {
        return [
            'store_name' => $this->get('store_name', config('app.name')),
            'phone' => $this->get('phone'),
            'whatsapp_number' => $this->whatsappNumber(),
            'address' => $this->get('address'),
            'shipping_origin_id' => $this->get('shipping_origin_id', config('services.rajaongkir.origin_id')),
            'shipping_origin_label' => $this->get('shipping_origin_label'),
            'store_latitude' => $this->get('store_latitude'),
            'store_longitude' => $this->get('store_longitude'),
            'min_lead_hours' => $this->get('min_lead_hours', config('floristsync.min_lead_hours')),
            'same_day_cutoff_hour' => $this->get('same_day_cutoff_hour', config('floristsync.same_day_cutoff_hour')),
            'hours' => $this->openingHours(),
        ];
    }

    public function storeLocation(): array
    {
        $address = trim((string) $this->get('address', config('floristsync.store_address', '')));
        $latitude = $this->get('store_latitude');
        $longitude = $this->get('store_longitude');

        $hasCoordinates = is_numeric($latitude) && is_numeric($longitude)
            && abs((float) $latitude) <= 90 && abs((float) $longitude) <= 180;

        $query = $hasCoordinates
            ? number_format((float) $latitude, 6, '.', '').','.number_format((float) $longitude, 6, '.', '')
            : ($address !== '' ? $address : null);

        return [
            'address' => $address,
            'maps_url' => $query ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($query) : null,
            'embed_url' => $query ? 'https://maps.google.com/maps?q='.rawurlencode($query).'&z=16&output=embed' : null,
        ];
    }

    public function whatsappNumber(): string
    {
        return (string) $this->get('whatsapp_number', config('floristsync.whatsapp_number'));
    }

    public function openingHours(): array
    {
        $defaults = config('floristsync.opening_hours', []);
        $saved = json_decode((string) $this->get('opening_hours', ''), true);
        $saved = is_array($saved) ? $saved : [];

        $hours = [];

        foreach (self::DAYS as $day) {
            $row = $saved[$day] ?? $defaults[$day] ?? [];

            $hours[$day] = [
                'closed' => (bool) ($row['closed'] ?? false),
                'open' => $row['open'] ?? $defaults[$day]['open'] ?? '08:00',
                'close' => $row['close'] ?? $defaults[$day]['close'] ?? '20:00',
            ];
        }

        return $hours;
    }

    public function openingHoursSummary(): array
    {
        $groups = [];

        foreach ($this->openingHours() as $day => $row) {
            $label = ucfirst(substr($day, 0, 3));
            $text = $row['closed']
                ? 'Closed'
                : str_replace(':', '.', $row['open']) . ' - ' . str_replace(':', '.', $row['close']) . ' WIB';

            $last = array_key_last($groups);

            if ($last !== null && $groups[$last]['text'] === $text) {
                $groups[$last]['to'] = $label;
            } else {
                $groups[] = ['from' => $label, 'to' => $label, 'text' => $text];
            }
        }

        return array_map(
            fn (array $g) => ($g['from'] === $g['to'] ? $g['from'] : $g['from'] . ' - ' . $g['to']) . ': ' . $g['text'],
            $groups
        );
    }
}
