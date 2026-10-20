<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** /storage/{path} works even when the public_html/storage link is missing. */
class PublicFileServingTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_video_is_served_with_range_support(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('educators/1/video/intro.mp4', str_repeat('v', 2048));

        $this->get('/storage/educators/1/video/intro.mp4')
            ->assertOk()
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertCookieMissing('XSRF-TOKEN');

        // Browsers fetch videos in byte ranges to play and skip.
        $this->get('/storage/educators/1/video/intro.mp4', ['Range' => 'bytes=0-99'])
            ->assertStatus(206)
            ->assertHeader('Content-Length', '100');
    }

    public function test_missing_files_hidden_files_and_escapes_are_not_served(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('.secret', 'x');

        $this->get('/storage/educators/1/video/missing.mp4')->assertNotFound();
        $this->get('/storage/.secret')->assertNotFound();
        $this->get('/storage/../../.env')->assertNotFound();
        $this->get('/storage/%2e%2e/%2e%2e/.env')->assertNotFound();
    }

    public function test_private_files_are_not_reachable_through_storage(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('local')->put('educators/1/resume/cv.pdf', '%PDF');

        $this->get('/storage/educators/1/resume/cv.pdf')->assertNotFound();
    }
}
