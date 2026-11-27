<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransService;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class CheckoutValidationMessagesTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        $this->member = User::factory()->create([
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'is_active' => true,
        ]);

        $category = Category::factory()->create();

        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'product_name' => 'Peony Bouquet',
            'price' => 80000,
            'stock' => 10,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token-test')->byDefault();
            $mock->shouldReceive('cancelInvoice')->andReturnNull()->byDefault();
            $mock->shouldReceive('getStatus')->andReturn(null)->byDefault();
        });

        $this->actingAs($this->member)
            ->post(route('cart.add', $this->product), ['qty' => 1])
            ->assertRedirect();
    }

    private function checkoutUrl(): string
    {
        return route('checkout', ['selected' => [$this->product->id]]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Member Customer',
            'phone' => '081234567890',
            'fulfillment' => 'pickup',
            'selected' => [$this->product->id],
        ], $override);
    }

    private function submit(array $override = [])
    {
        return $this->actingAs($this->member)
            ->from($this->checkoutUrl())
            ->post(route('checkout.store'), $this->payload($override));
    }

    protected function followRedirects($response)
    {
        $cookies = [];
        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        while ($response->isRedirect()) {
            $response = $this->withUnencryptedCookies($cookies)->get($response->headers->get('Location'));
            foreach ($response->headers->getCookies() as $cookie) {
                $cookies[$cookie->getName()] = $cookie->getValue();
            }
        }

        return $response;
    }

    public function test_delivery_without_courier_and_service_shows_the_message(): void
    {
        $response = $this->submit([
            'fulfillment' => 'delivery',
            'address' => 'Jl. Melati No. 1',
            'destination_id' => 23,
        ]);

        $response->assertSessionHasErrors(['courier', 'service']);
        $response->assertRedirect($this->checkoutUrl());

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Please choose a courier and a service.')
            ->assertSee('id="checkout-errors"', false);

        $this->assertSame(0, Transaction::where('user_id', $this->member->id)->count());
    }

    public function test_delivery_with_service_missing_only_shows_the_message(): void
    {
        $response = $this->submit([
            'fulfillment' => 'delivery',
            'address' => 'Jl. Melati No. 1',
            'destination_id' => 23,
            'courier' => 'jne',
        ]);

        $response->assertSessionHasErrors(['service']);
        $response->assertSessionDoesntHaveErrors(['courier']);

        $this->followRedirects($response)
            ->assertSee('Please choose a courier and a service.');
    }

    public function test_delivery_without_address_and_destination_shows_the_messages(): void
    {
        $response = $this->submit([
            'fulfillment' => 'delivery',
        ]);

        $response->assertSessionHasErrors(['address', 'destination_id', 'courier', 'service']);

        $this->followRedirects($response)
            ->assertSee('Please enter your full delivery address.')
            ->assertSee('Please pick your city or district from the list.')
            ->assertSee('Please choose a courier and a service.');
    }

    public function test_invalid_courier_shows_the_message(): void
    {
        $response = $this->submit([
            'fulfillment' => 'delivery',
            'address' => 'Jl. Melati No. 1',
            'destination_id' => 23,
            'courier' => 'dhl',
            'service' => 'REG',
        ]);

        $response->assertSessionHasErrors(['courier']);

        $this->followRedirects($response)
            ->assertSee('Please choose a valid courier.');
    }

    public function test_missing_name_and_phone_show_the_messages(): void
    {
        $response = $this->submit([
            'name' => '',
            'phone' => '',
        ]);

        $response->assertSessionHasErrors(['name', 'phone']);

        $this->followRedirects($response)
            ->assertSee('Please enter your full name.')
            ->assertSee('Please enter your phone number.');
    }

    public function test_note_of_501_characters_is_rejected_with_a_message(): void
    {
        $response = $this->submit([
            'notes' => str_repeat('a', 501),
        ]);

        $response->assertSessionHasErrors(['notes']);

        $this->followRedirects($response)
            ->assertSee('The note cannot be longer than 500 characters.');

        $this->assertSame(0, Transaction::where('user_id', $this->member->id)->count());
    }

    public function test_note_of_500_characters_is_accepted(): void
    {
        $response = $this->submit([
            'notes' => str_repeat('a', 500),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertStatus(303);

        $this->assertSame(1, Transaction::where('user_id', $this->member->id)->count());
    }

    public function test_notes_field_has_maxlength_and_live_counter(): void
    {
        $this->actingAs($this->member)
            ->get($this->checkoutUrl())
            ->assertOk()
            ->assertSee('maxlength="500"', false)
            ->assertSee('</span>/500', false);
    }
}
