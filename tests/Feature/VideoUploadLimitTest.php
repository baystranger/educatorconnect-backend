<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoUploadLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_over_25_mb_is_rejected_with_a_clear_message(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('intro.mp4', 26 * 1024, 'video/mp4');

        $this->actingAs($this->educator())->post('/api/v1/educator/profile/media/video', ['file' => $file], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('errors.file.0', 'Videos must be 25 MB or smaller.');
    }

    public function test_video_up_to_25_mb_is_accepted(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('intro.mp4', 24 * 1024, 'video/mp4');

        $this->actingAs($this->educator())->post('/api/v1/educator/profile/media/video', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk();
    }
}
