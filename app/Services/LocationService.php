<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Address → coordinates. Driver is chosen by GEOCODER (google | nominatim | none);
 * results are cached and failures return null so callers fall back to text matching.
 */
class LocationService
{
    public function geocode(string $address): ?array
    {
        $address = trim($address);
        if ($address === '' || $this->driver() === 'none') {
            return null;
        }

        return Cache::remember('geocode:'.sha1(mb_strtolower($address)), now()->addDays(30), function () use ($address) {
            try {
                return match ($this->driver()) {
                    'google' => $this->google($address),
                    'nominatim' => $this->nominatim($address),
                    default => null,
                };
            } catch (Throwable $e) {
                Log::warning('Geocoding failed', ['driver' => $this->driver(), 'error' => $e->getMessage()]);

                return null;
            }
        });
    }

    public function isEnabled(): bool
    {
        return $this->driver() !== 'none';
    }

    private function driver(): string
    {
        $driver = config('services.geocoder.driver');
        if ($driver) {
            return $driver;
        }

        // Without a Google key fall back to OpenStreetMap's free geocoder, so typed places
        // ("Surrey", "V3T 0A1") resolve to coordinates and the radius filter applies.
        // Set GEOCODER=none to switch geocoding off.
        return config('services.google_maps.key') ? 'google' : 'nominatim';
    }

    private function google(string $address): ?array
    {
        $response = Http::timeout(6)->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $address,
            'region' => 'ca',
            'key' => config('services.google_maps.key'),
        ])->json();

        if (($response['status'] ?? null) !== 'OK' || empty($response['results'][0])) {
            return null;
        }
        $location = $response['results'][0]['geometry']['location'];

        return ['latitude' => (float) $location['lat'], 'longitude' => (float) $location['lng']];
    }

    private function nominatim(string $address): ?array
    {
        // OSM's usage policy requires an identifying User-Agent (app + site URL) and asks
        // for a real contact email; placeholder addresses get the request refused (403).
        $email = config('services.geocoder.contact_email') ?: config('mail.from.address');
        $hasRealEmail = $email && ! str_ends_with(strtolower($email), '@example.com');

        $http = Http::timeout(6)
            ->withHeaders(['User-Agent' => config('app.name').'/1.0 (+'.config('app.url').')'.($hasRealEmail ? ' '.$email : '')])
            ->get(config('services.geocoder.nominatim_url', 'https://nominatim.openstreetmap.org/search'), array_filter([
                'q' => $address,
                'format' => 'json',
                'limit' => 1,
                'countrycodes' => 'ca,us',
                'email' => $hasRealEmail ? $email : null,
            ]));

        if (! $http->successful()) {
            Log::warning('Nominatim geocoding refused', ['status' => $http->status(), 'body' => mb_substr($http->body(), 0, 200)]);

            return null;
        }

        $response = $http->json();
        if (empty($response[0]['lat'])) {
            return null;
        }

        return ['latitude' => (float) $response[0]['lat'], 'longitude' => (float) $response[0]['lon']];
    }
}
