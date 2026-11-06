<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesCargoData;
use Tests\TestCase;

class DashboardCurrencyRatesTest extends TestCase
{
    use CreatesCargoData, RefreshDatabase;

    public function test_guest_cannot_access_currency_rates(): void
    {
        $this->get(route('dashboard.currency-rates'))->assertRedirect('/login');
    }

    public function test_authenticated_user_gets_cached_provider_rates(): void
    {
        Cache::flush();
        $now = now();
        Http::fake([
            'open.er-api.com/v6/latest/USD' => Http::response([
                'result' => 'success',
                'base_code' => 'USD',
                'time_last_update_unix' => $now->timestamp,
                'time_next_update_unix' => $now->copy()->addDay()->timestamp,
                'rates' => ['USD' => 1, 'IDR' => 16000, 'EUR' => .9],
            ]),
        ]);
        $user = $this->makeUser(UserRole::Customer);

        $this->actingAs($user)
            ->getJson(route('dashboard.currency-rates'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=900, private')
            ->assertJsonPath('base', 'USD')
            ->assertJsonPath('rates.IDR', 16000)
            ->assertJsonPath('is_stale', false);

        $this->actingAs($user)
            ->getJson(route('dashboard.currency-rates'))
            ->assertOk();

        Http::assertSentCount(1);
    }

    public function test_cached_rates_are_returned_as_stale_when_provider_is_unreachable(): void
    {
        $cachedAt = now()->subDays(2);
        Cache::put('currency-rates.usd', [
            'base' => 'USD',
            'rates' => ['USD' => 1, 'IDR' => 16000],
            'updated_at' => $cachedAt->toIso8601String(),
            'fresh_until' => $cachedAt->toIso8601String(),
            'provider' => 'ExchangeRate-API',
            'provider_url' => 'https://www.exchangerate-api.com/',
        ], now()->addDay());
        Http::fake([
            'open.er-api.com/v6/latest/USD' => Http::response([], 503),
        ]);

        $this->actingAs($this->makeUser(UserRole::Admin))
            ->getJson(route('dashboard.currency-rates'))
            ->assertOk()
            ->assertJsonPath('rates.IDR', 16000)
            ->assertJsonPath('is_stale', true);
    }
}
