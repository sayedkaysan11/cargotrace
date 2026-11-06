<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class CurrencyRateService
{
    private const CACHE_KEY = 'currency-rates.usd';

    private const ENDPOINT = 'https://open.er-api.com/v6/latest/USD';

    public function rates(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($this->isValidRecord($cached) && Carbon::parse($cached['fresh_until'])->isFuture()) {
            return $this->responseData($cached, false);
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout(6)
                ->get(self::ENDPOINT);

            if (! $response->successful()) {
                throw new RuntimeException('The exchange-rate provider returned an unsuccessful response.');
            }

            $payload = $response->json();
            $rates = is_array($payload) ? ($payload['rates'] ?? null) : null;

            if (
                ! is_array($payload)
                || ($payload['result'] ?? null) !== 'success'
                || ($payload['base_code'] ?? null) !== 'USD'
                || ! is_array($rates)
            ) {
                throw new RuntimeException('The exchange-rate provider returned invalid rate data.');
            }

            $rates = array_filter(
                $rates,
                static fn ($value, $code): bool => is_string($code)
                    && preg_match('/^[A-Z]{3}$/', $code) === 1
                    && is_numeric($value)
                    && is_finite((float) $value)
                    && (float) $value > 0,
                ARRAY_FILTER_USE_BOTH,
            );

            if (! isset($rates['USD'], $rates['IDR'])) {
                throw new RuntimeException('The exchange-rate provider omitted required currency rates.');
            }

            $nextUpdate = isset($payload['time_next_update_unix'])
                ? Carbon::createFromTimestampUTC((int) $payload['time_next_update_unix'])
                : now()->addDay();

            $record = [
                'base' => 'USD',
                'rates' => $rates,
                'updated_at' => isset($payload['time_last_update_unix'])
                    ? Carbon::createFromTimestampUTC((int) $payload['time_last_update_unix'])->toIso8601String()
                    : now()->toIso8601String(),
                'fresh_until' => $nextUpdate->addMinute()->toIso8601String(),
                'provider' => 'ExchangeRate-API',
                'provider_url' => 'https://www.exchangerate-api.com/',
            ];

            Cache::put(self::CACHE_KEY, $record, now()->addDays(3));

            return $this->responseData($record, false);
        } catch (ConnectionException|RuntimeException $exception) {
            if ($this->isValidRecord($cached)) {
                return $this->responseData($cached, true);
            }

            throw new ServiceUnavailableHttpException(
                3600,
                'Currency rates are temporarily unavailable. Please try again later.',
                $exception,
            );
        }
    }

    private function isValidRecord(mixed $record): bool
    {
        return is_array($record)
            && isset($record['base'], $record['rates'], $record['updated_at'], $record['fresh_until'])
            && $record['base'] === 'USD'
            && is_array($record['rates'])
            && isset($record['rates']['USD'], $record['rates']['IDR']);
    }

    private function responseData(array $record, bool $isStale): array
    {
        return [
            'base' => $record['base'],
            'rates' => $record['rates'],
            'updated_at' => $record['updated_at'],
            'is_stale' => $isStale,
            'provider' => $record['provider'] ?? 'ExchangeRate-API',
            'provider_url' => $record['provider_url'] ?? 'https://www.exchangerate-api.com/',
        ];
    }
}
