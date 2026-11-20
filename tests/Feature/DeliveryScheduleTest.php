<?php

namespace Tests\Feature;

use App\Services\DeliveryScheduleService;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DeliveryScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday 10:00, lead time 3 hours, same-day cut-off 16:00, every day open.
        $this->travelTo(Carbon::parse('2026-11-18 10:00:00'));

        config([
            'floristsync.min_lead_hours' => 3,
            'floristsync.same_day_cutoff_hour' => 16,
            'floristsync.max_advance_days' => 60,
        ]);
    }

    private function check(array $input): ValidatorContract
    {
        return Validator::make($input, app(DeliveryScheduleService::class)->rules($input['delivery_slot'] ?? null));
    }

    public function test_a_valid_date_slot_and_card_message_are_accepted(): void
    {
        $validator = $this->check([
            'delivery_date' => now()->addDay()->toDateString(),
            'delivery_slot' => 'morning',
            'card_message' => str_repeat('a', 200),
        ]);

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->all()));
    }

    public function test_a_same_day_slot_exactly_at_the_lead_time_is_accepted(): void
    {
        $validator = $this->check([
            'delivery_date' => now()->toDateString(),
            'delivery_slot' => 'afternoon',
        ]);

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->all()));
    }

    public function test_the_fields_are_optional_together(): void
    {
        $this->assertFalse($this->check([])->fails());
    }

    public function test_a_date_in_the_past_is_refused(): void
    {
        $validator = $this->check([
            'delivery_date' => now()->subDay()->toDateString(),
            'delivery_slot' => 'morning',
        ]);

        $this->assertTrue($validator->errors()->has('delivery_date'));
    }

    public function test_a_slot_that_is_too_soon_is_refused(): void
    {
        $validator = $this->check([
            'delivery_date' => now()->toDateString(),
            'delivery_slot' => 'morning',
        ]);

        $this->assertTrue($validator->errors()->has('delivery_date'));
    }

    public function test_a_same_day_order_after_the_cut_off_hour_is_refused(): void
    {
        config(['floristsync.min_lead_hours' => 0]);
        $this->travelTo(Carbon::parse('2026-11-18 16:30:00'));

        $validator = $this->check([
            'delivery_date' => now()->toDateString(),
            'delivery_slot' => 'evening',
        ]);

        $this->assertTrue($validator->errors()->has('delivery_date'));
    }

    public function test_a_closed_day_is_refused(): void
    {
        config(['floristsync.opening_hours.thursday.closed' => true]);

        $validator = $this->check([
            'delivery_date' => '2026-11-19',
            'delivery_slot' => 'morning',
        ]);

        $this->assertTrue($validator->errors()->has('delivery_date'));
    }

    public function test_a_date_more_than_sixty_days_ahead_is_refused(): void
    {
        $last = $this->check([
            'delivery_date' => now()->addDays(60)->toDateString(),
            'delivery_slot' => 'morning',
        ]);

        $tooFar = $this->check([
            'delivery_date' => now()->addDays(61)->toDateString(),
            'delivery_slot' => 'morning',
        ]);

        $this->assertFalse($last->fails(), json_encode($last->errors()->all()));
        $this->assertTrue($tooFar->errors()->has('delivery_date'));
    }

    public function test_a_card_message_of_201_characters_is_refused(): void
    {
        $validator = $this->check([
            'delivery_date' => now()->addDay()->toDateString(),
            'delivery_slot' => 'morning',
            'card_message' => str_repeat('a', 201),
        ]);

        $this->assertTrue($validator->errors()->has('card_message'));
    }

    public function test_a_date_without_a_slot_and_an_unknown_slot_are_refused(): void
    {
        $noSlot = $this->check(['delivery_date' => now()->addDay()->toDateString()]);
        $badSlot = $this->check([
            'delivery_date' => now()->addDay()->toDateString(),
            'delivery_slot' => 'midnight',
        ]);

        $this->assertTrue($noSlot->errors()->has('delivery_slot'));
        $this->assertTrue($badSlot->errors()->has('delivery_slot'));
    }
}
