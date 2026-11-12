<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A typed place plus a radius must search by distance, not just by city name:
 * "Surrey within 100 km" includes the Burnaby, Coquitlam and Vancouver centres.
 */
class CentreLocationSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed(DemoSeeder::class);
    }

    public function test_typed_city_is_geocoded_and_radius_applies(): void
    {
        config(['services.geocoder.driver' => 'nominatim']);
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '49.1913', 'lon' => '-122.8490']])]);

        $response = $this->getJson('/api/v1/childcare-centres?location=surrey&radius=100&per_page=50')
            ->assertOk()
            ->assertJsonPath('meta.origin.latitude', 49.1913);

        $slugs = collect($response->json('data'))->pluck('slug');
        $this->assertContains('bright-beginnings', $slugs);
        $this->assertGreaterThan(1, $response->json('meta.total'));

        // A small radius still narrows the results.
        $this->getJson('/api/v1/childcare-centres?location=surrey&radius=5&per_page=50')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'bright-beginnings']);
    }

    public function test_radius_excludes_centres_inside_the_bounding_box_but_outside_the_circle(): void
    {
        // Kids' Harbour (New Westminster) is ~6.5 km from central Burnaby: inside the 5 km box corner, outside the circle.
        $this->getJson('/api/v1/childcare-centres?latitude=49.2433&longitude=-122.9725&radius=5&per_page=50')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'bright-beginnings'])
            ->assertJsonMissing(['slug' => 'kids-harbour']);
    }

    public function test_falls_back_to_known_centres_when_geocoder_is_unavailable(): void
    {
        config(['services.geocoder.driver' => 'none']);

        $response = $this->getJson('/api/v1/childcare-centres?location=New Westminster&radius=25&per_page=50')->assertOk();

        $this->assertNotNull($response->json('meta.origin'));
        $this->assertContains('bright-beginnings', collect($response->json('data'))->pluck('slug'));
    }
}
