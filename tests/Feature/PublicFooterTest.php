<?php

namespace Tests\Feature;

use App\Services\SettingService;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFooterTest extends TestCase
{
    use RefreshDatabase;

    private const ADDRESS = 'Jl. Mawar Indah No. 42, Malang';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);
    }

    private function saveSettings(array $values): void
    {
        $settings = app(SettingService::class);

        foreach ($values as $key => $value) {
            $settings->set($key, $value);
        }
    }

    public function test_the_footer_shows_the_address_and_the_map_when_coordinates_are_saved(): void
    {
        $this->saveSettings([
            'address' => self::ADDRESS,
            'store_latitude' => '-7.9666',
            'store_longitude' => '112.6326',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(self::ADDRESS)
            ->assertSee('<iframe', false)
            ->assertSee('loading="lazy"', false)
            ->assertSee('maps.google.com/maps?q=-7.966600%2C112.632600', false)
            ->assertDontSee('key=', false);
    }

    public function test_the_footer_has_no_map_without_coordinates_and_without_an_address(): void
    {
        config(['floristsync.store_address' => '']);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Store location map')
            ->assertDontSee('maps.google.com', false)
            ->assertDontSee('Buka di Google Maps');
    }

    public function test_the_map_uses_the_address_when_there_are_no_coordinates(): void
    {
        $this->saveSettings(['address' => self::ADDRESS]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('maps.google.com/maps?q='.rawurlencode(self::ADDRESS), false);
    }

    public function test_out_of_range_coordinates_fall_back_to_the_address(): void
    {
        $this->saveSettings([
            'address' => self::ADDRESS,
            'store_latitude' => '999',
            'store_longitude' => '112.6326',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('maps.google.com/maps?q='.rawurlencode(self::ADDRESS), false);
    }

    public function test_the_google_maps_link_is_correct(): void
    {
        $this->saveSettings([
            'address' => self::ADDRESS,
            'store_latitude' => '-7.9666',
            'store_longitude' => '112.6326',
        ]);

        $expected = 'https://www.google.com/maps/search/?api=1&query='.rawurlencode('-7.966600,112.632600');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Buka di Google Maps')
            ->assertSee('href="'.e($expected).'"', false);
    }
}
