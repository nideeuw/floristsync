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
        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                $this->set($key, $value);
            }
        });
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
            'hours' => $this->openingHours(),
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
