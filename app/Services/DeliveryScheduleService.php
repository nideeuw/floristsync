<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DeliveryScheduleService
{
    /** Slot key => label and the hour the slot starts (used for the lead time check). */
    public const SLOTS = [
        'morning' => ['label' => 'Morning (09:00 - 12:00)', 'start' => '09:00'],
        'afternoon' => ['label' => 'Afternoon (13:00 - 17:00)', 'start' => '13:00'],
        'evening' => ['label' => 'Evening (17:00 - 20:00)', 'start' => '17:00'],
    ];

    public const CARD_MESSAGE_MAX = 200;

    public function __construct(private SettingService $settings) {}

    public static function slotLabel(?string $slot): ?string
    {
        if ($slot === null || $slot === '') {
            return null;
        }

        return self::SLOTS[$slot]['label'] ?? ucfirst($slot);
    }

    /** Validation rules for the three checkout fields. The slot is needed to check the lead time. */
    public function rules(?string $slot = null): array
    {
        return [
            'delivery_date' => [
                'nullable',
                'bail',
                'date_format:Y-m-d',
                'required_with:delivery_slot',
                function (string $attribute, mixed $value, Closure $fail) use ($slot) {
                    $message = $this->dateError((string) $value, $slot);

                    if ($message !== null) {
                        $fail($message);
                    }
                },
            ],
            'delivery_slot' => ['nullable', 'required_with:delivery_date', 'in:'.implode(',', array_keys(self::SLOTS))],
            'card_message' => ['nullable', 'string', 'max:'.self::CARD_MESSAGE_MAX],
        ];
    }

    /** Returns the first reason the date cannot be used, or null when it is fine. */
    public function dateError(string $date, ?string $slot = null): ?string
    {
        $now = Carbon::now();
        $today = $now->copy()->startOfDay();
        $day = Carbon::parse($date)->startOfDay();

        if ($day->lt($today)) {
            return 'The delivery date cannot be in the past.';
        }

        $maxDays = $this->maxAdvanceDays();

        if ($day->gt($today->copy()->addDays($maxDays))) {
            return "Please choose a date within the next {$maxDays} days.";
        }

        if ($this->isClosed($day)) {
            return "We are closed on {$day->englishDayOfWeek}s. Please choose another date.";
        }

        $cutoff = $this->cutoffHour();

        if ($day->equalTo($today) && $now->hour >= $cutoff) {
            return sprintf('Orders for today must be placed before %02d:00. Please choose another date.', $cutoff);
        }

        $lead = $this->minLeadHours();
        $start = $day->copy()->setTimeFromTimeString(self::SLOTS[(string) $slot]['start'] ?? '00:00');

        if ($start->lt($now->copy()->addHours($lead))) {
            return "We need at least {$lead} ".Str::plural('hour', $lead).' to prepare your order. Please choose a later time slot or date.';
        }

        return null;
    }

    public function minLeadHours(): int
    {
        return max(0, (int) $this->settings->get('min_lead_hours', config('floristsync.min_lead_hours')));
    }

    public function cutoffHour(): int
    {
        return min(23, max(0, (int) $this->settings->get('same_day_cutoff_hour', config('floristsync.same_day_cutoff_hour'))));
    }

    public function maxAdvanceDays(): int
    {
        return max(1, (int) config('floristsync.max_advance_days'));
    }

    /** Data the checkout form needs to draw the picker and its hints. */
    public function formConfig(): array
    {
        $today = Carbon::today();
        $closed = array_keys(array_filter($this->settings->openingHours(), fn (array $row) => $row['closed']));

        return [
            'slots' => array_map(fn (array $slot) => $slot['label'], self::SLOTS),
            'min' => $today->toDateString(),
            'max' => $today->copy()->addDays($this->maxAdvanceDays())->toDateString(),
            'lead_hours' => $this->minLeadHours(),
            'cutoff_hour' => $this->cutoffHour(),
            'closed_days' => array_map('ucfirst', $closed),
            // JavaScript getDay() numbers: 0 = Sunday ... 6 = Saturday.
            'closed_weekdays' => array_map(
                fn (string $day) => (array_search($day, SettingService::DAYS, true) + 1) % 7,
                $closed
            ),
            'card_max' => self::CARD_MESSAGE_MAX,
        ];
    }

    private function isClosed(Carbon $day): bool
    {
        $row = $this->settings->openingHours()[strtolower($day->englishDayOfWeek)] ?? null;

        return (bool) ($row['closed'] ?? false);
    }
}
