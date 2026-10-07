<?php

namespace Tests;

use App\Models\ChildcareCentre;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;

abstract class TestCase extends BaseTestCase
{
    protected const PASSWORD = 'StrongPass123!';

    /** A verified educator in Burnaby with location set. */
    protected function educator(array $overrides = [], bool $verified = true): User
    {
        $user = app(AuthService::class)->register(array_merge([
            'role' => 'educator',
            'email' => 'educator'.uniqid().'@example.com',
            'password' => self::PASSWORD,
            'first_name' => 'Priya',
            'last_name' => 'Nair',
            'city' => 'Burnaby',
            'province' => 'British Columbia',
            'postal_code' => 'V5K 0A1',
            'country' => 'Canada',
            'terms' => true,
        ], $overrides));

        if ($verified) {
            $user->markEmailAsVerified();
        }
        $user->educatorProfile->forceFill(['latitude' => 49.2827, 'longitude' => -123.0207])->save();

        return $user->refresh();
    }

    /** A verified centre owner whose centre is published at a Burnaby address. */
    protected function centreOwner(array $overrides = [], bool $published = true): User
    {
        $user = app(AuthService::class)->register(array_merge([
            'role' => 'childcare_centre',
            'email' => 'centre'.uniqid().'@example.com',
            'password' => self::PASSWORD,
            'centre_name' => 'Little Sprouts Daycare',
            'contact_person' => 'Anita Sharma',
            'phone' => '(604) 555-0100',
            'address' => '1420 Kingsway',
            'city' => 'Burnaby',
            'province' => 'British Columbia',
            'postal_code' => 'V5H 2A1',
            'country' => 'Canada',
            'terms' => true,
        ], $overrides));
        $user->markEmailAsVerified();

        $centre = $user->centre;
        $centre->forceFill([
            'latitude' => 49.2488,
            'longitude' => -123.0016,
            'hours_of_operation' => [['label' => 'Mon – Fri', 'value' => '7:30 AM – 5:30 PM']],
            'status' => $published ? 'published' : 'draft',
            'is_published' => $published,
        ])->save();

        return $user->refresh();
    }

    protected function centreOf(User $owner): ChildcareCentre
    {
        return $owner->centre()->firstOrFail();
    }

    /**
     * A real PNG upload built without GD (so image validation runs for real).
     */
    protected function pngUpload(string $name = 'photo.png', int $width = 800, int $height = 600): UploadedFile
    {
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $rows = str_repeat("\x00".str_repeat("\xC8\xB4\xE6", $width), $height);
        $png = "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress($rows))
            .$chunk('IEND', '');

        $path = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($path, $png);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    protected function jobPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Early Childhood Educator — Preschool room',
            'category' => 'early_childhood_educator',
            'employment_type' => 'full_time',
            'number_of_positions' => 2,
            'start_date' => now()->addMonth()->toDateString(),
            'application_deadline' => now()->addWeeks(2)->toDateString(),
            'use_centre_address' => true,
            'show_pay' => true,
            'salary_min' => '26',
            'salary_max' => '30',
            'salary_period' => 'hour',
            'description' => 'Join our 3–5 room to plan and lead play-based learning.',
            'responsibilities' => "Plan play-based experiences\nBuild relationships with families",
            'benefits' => "Extended health & dental\nPaid professional development",
            'required_education' => 'ece_certificate',
            'experience_level' => 'one_year',
            'required_certifications' => ['ece_certificate', 'first_aid_cpr'],
        ], $overrides);
    }
}
