<?php

namespace Database\Seeders;

use App\Models\ChildcareCentre;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\ProfessionalCategory;
use App\Models\ProfessionalListing;
use App\Models\ProfileViewEvent;
use App\Models\SavedJob;
use App\Models\User;
use App\Notifications\ApplicationStatusChangedNotification;
use App\Notifications\JobApplicationSubmittedNotification;
use App\Notifications\ParentRequestReceivedNotification;
use App\Services\AuthService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DEMO DATA ONLY — never runs in production.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Creates the centres, educators, jobs, applications and family requests shown in the
 * design so every screen can be exercised end to end. Demo accounts (password DemoPass123!):
 *   centre:   centre@demo.educatorconnect.ca   (Little Sprouts Daycare)
 *   educator: educator@demo.educatorconnect.ca (Priya Nair)
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'DemoPass123!';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder must not run in production.');
        }
        if (User::where('email', 'centre@demo.educatorconnect.ca')->exists()) {
            $this->command?->warn('Demo data already present — skipping.');

            return;
        }

        $this->call(DatabaseSeeder::class);

        DB::transaction(function (): void {
            $centres = $this->centres();
            $educators = $this->educators();
            $jobs = $this->jobs($centres);
            $this->applications($jobs, $educators);
            $this->families($centres['little-sprouts']);
            $this->profileViews($centres['little-sprouts'], $educators['priya']);
            $this->professionals();
        });

        $this->command?->info('Demo data created. Sign in with centre@demo.educatorconnect.ca or educator@demo.educatorconnect.ca / '.self::PASSWORD);
    }

    /** @return array<string, ChildcareCentre> */
    private function centres(): array
    {
        $definitions = [
            'little-sprouts' => [
                'email' => 'centre@demo.educatorconnect.ca', 'name' => 'Little Sprouts Daycare', 'contact' => 'Anita Sharma',
                'address' => '1420 Kingsway', 'city' => 'Burnaby', 'postal' => 'V5H 2A1', 'lat' => 49.2276, 'lng' => -123.0076,
                'licence' => 'group_child_care', 'approaches' => ['play_based', 'emergent'], 'highlights' => ['Hot lunch', 'Outdoor every day'],
                'about' => "Little Sprouts is a family-run centre in Burnaby caring for children from 6 months to 5 years since 2014. Small rooms, long-serving educators and a big garden where children spend part of every day.",
                'philosophy' => 'We believe children learn best through play, relationships and time outdoors.',
                'hours' => [['label' => 'Mon – Fri', 'value' => '7:30 AM – 5:30 PM'], ['label' => 'Sat – Sun', 'value' => 'Closed'], ['label' => 'Holidays', 'value' => 'Closed on statutory holidays']],
                'programs' => [
                    ['Infant', 6, 18, 'waitlist', 'full_day', 8, 3, 650],
                    ['Toddler', 18, 36, 'spaces', 'full_day', 12, 3, 500],
                    ['Preschool', 36, 60, 'full', 'part_or_full_day', 16, 2, 200],
                ],
                'verified' => true,
            ],
            'happy-hearts' => [
                'email' => 'happyhearts@demo.educatorconnect.ca', 'name' => 'Happy Hearts Early Learning', 'contact' => 'Grace Park',
                'address' => '5050 Imperial St', 'city' => 'Burnaby', 'postal' => 'V5J 1E1', 'lat' => 49.2213, 'lng' => -122.9890,
                'licence' => 'preschool', 'approaches' => ['reggio'], 'highlights' => [],
                'about' => 'Light-filled studios and a strong focus on art, music and family partnerships.',
                'philosophy' => 'Children are capable, curious and full of ideas.',
                'hours' => [['label' => 'Mon – Fri', 'value' => '8:00 AM – 4:00 PM']],
                'programs' => [['Preschool', 36, 60, 'waitlist', 'part_day', 20, 3, 450]],
                'verified' => true,
            ],
            'cedar-moss' => [
                'email' => 'cedarmoss@demo.educatorconnect.ca', 'name' => 'Cedar & Moss Nature School', 'contact' => 'Carlos Ruiz',
                'address' => '1250 Lonsdale Ave', 'city' => 'North Vancouver', 'postal' => 'V7M 2H6', 'lat' => 49.3200, 'lng' => -123.0724,
                'licence' => 'group_child_care', 'approaches' => ['nature_based'], 'highlights' => ['Forest school'],
                'about' => 'Forest-school mornings and a cozy indoor studio for afternoons.',
                'philosophy' => 'The outdoors is our first classroom.',
                'hours' => [['label' => 'Mon – Thu', 'value' => '8:30 AM – 3:00 PM']],
                'programs' => [['Nature preschool', 30, 60, 'spaces', 'part_day', 16, 3, 600]],
                'verified' => true,
            ],
            'maple-grove' => [
                'email' => 'maplegrove@demo.educatorconnect.ca', 'name' => 'Maple Grove Montessori', 'contact' => 'Mei Lin',
                'address' => '3300 Main St', 'city' => 'Vancouver', 'postal' => 'V5V 3M8', 'lat' => 49.2566, 'lng' => -123.1010,
                'licence' => 'montessori', 'approaches' => ['montessori'], 'highlights' => [],
                'about' => 'Authentic Montessori classrooms with trained guides.',
                'philosophy' => 'Follow the child.',
                'hours' => [['label' => 'Mon – Fri', 'value' => '7:45 AM – 5:15 PM']],
                'programs' => [['Toddler community', 18, 36, 'full', 'full_day', 10, 2, 900], ['Casa', 36, 72, 'full', 'full_day', 24, 3, 850]],
                'verified' => true,
            ],
            'kingsway-family' => [
                'email' => 'kingswayfamily@demo.educatorconnect.ca', 'name' => 'Kingsway Family Childcare', 'contact' => 'Linh Tran',
                'address' => '4710 Kingsway', 'city' => 'Burnaby', 'postal' => 'V5H 2C3', 'lat' => 49.2290, 'lng' => -122.9980,
                'licence' => 'family_child_care', 'approaches' => [], 'highlights' => ['Small group'],
                'about' => 'A home-based setting with up to 7 children and one familiar caregiver.',
                'philosophy' => 'A second home for your little one.',
                'hours' => [['label' => 'Mon – Fri', 'value' => '7:00 AM – 5:00 PM']],
                'programs' => [['Infant', 0, 36, 'spaces', 'full_day', 7, 1, 1100]],
                'verified' => false,
            ],
            'bright-beginnings' => [
                'email' => 'brightbeginnings@demo.educatorconnect.ca', 'name' => 'Bright Beginnings Childcare', 'contact' => 'Tom Becker',
                'address' => '6200 McKay Ave', 'city' => 'Burnaby', 'postal' => 'V5H 2W7', 'lat' => 49.2250, 'lng' => -123.0040,
                'licence' => 'group_child_care', 'approaches' => ['play_based'], 'highlights' => [],
                'about' => 'Warm, play-based care for toddlers and preschoolers near Metrotown.',
                'philosophy' => 'Every child belongs.',
                'hours' => [['label' => 'Mon – Fri', 'value' => '7:30 AM – 6:00 PM']],
                'programs' => [['Toddler', 18, 36, 'waitlist', 'full_day', 12, 3, 1000]],
                'verified' => true,
            ],
            'sunny-park' => [
                'email' => 'sunnypark@demo.educatorconnect.ca', 'name' => 'Sunny Park Childcare', 'contact' => 'Sam Patel',
                'address' => '800 Sunny Park Rd', 'city' => 'Coquitlam', 'postal' => 'V3K 1A1', 'lat' => 49.2620, 'lng' => -122.8520,
                'licence' => 'group_child_care', 'approaches' => ['play_based'], 'highlights' => [],
                'about' => 'A bright neighbourhood centre with a large playground.',
                'philosophy' => 'Joyful days, curious minds.',
                'hours' => [['label' => 'Mon – Fri', 'value' => '7:30 AM – 5:30 PM']],
                'programs' => [['Preschool', 36, 60, 'spaces', 'full_day', 20, 3, 750]],
                'verified' => true,
            ],
            'kids-harbour' => [
                'email' => 'kidsharbour@demo.educatorconnect.ca', 'name' => "Kids' Harbour", 'contact' => 'Ava Wong',
                'address' => '120 Esplanade', 'city' => 'New Westminster', 'postal' => 'V3M 1A1', 'lat' => 49.2010, 'lng' => -122.9110,
                'licence' => 'group_child_care', 'approaches' => ['emergent'], 'highlights' => [],
                'about' => 'Riverside care for infants and toddlers.',
                'philosophy' => 'Small moments, big learning.',
                'hours' => [['label' => 'Mon – Fri', 'value' => '7:00 AM – 6:00 PM']],
                'programs' => [['Infant / Toddler', 6, 36, 'spaces', 'full_day', 12, 4, 1200]],
                'verified' => true,
            ],
        ];

        $auth = app(AuthService::class);
        $centres = [];
        foreach ($definitions as $key => $d) {
            $owner = $auth->register([
                'role' => 'childcare_centre', 'email' => $d['email'], 'password' => self::PASSWORD,
                'centre_name' => $d['name'], 'contact_person' => $d['contact'], 'phone' => '(604) 555-01'.str_pad((string) count($centres), 2, '0', STR_PAD_LEFT),
                'address' => $d['address'], 'city' => $d['city'], 'province' => 'British Columbia', 'postal_code' => $d['postal'], 'country' => 'Canada',
            ]);
            $owner->markEmailAsVerified();

            $centre = $owner->centre;
            $centre->fill([
                'slug' => $key,
                'website' => Str::slug($d['name']).'.ca',
                'about' => $d['about'],
                'philosophy' => $d['philosophy'],
                'teaching_approaches' => $d['approaches'],
                'highlights' => $d['highlights'],
                'hours_of_operation' => $d['hours'],
                'licence_type' => $d['licence'],
                'latitude' => $d['lat'],
                'longitude' => $d['lng'],
            ]);
            $centre->forceFill([
                'is_verified' => $d['verified'],
                'is_published' => true,
                'status' => 'published',
                'published_at' => now()->subMonths(3),
                'submitted_at' => now()->subMonths(3),
            ])->save();

            foreach ($d['programs'] as $i => [$name, $min, $max, $availability, $schedule, $capacity, $educators, $tuition]) {
                $centre->programs()->create([
                    'name' => $name, 'min_age_months' => $min, 'max_age_months' => $max, 'availability' => $availability,
                    'schedule' => $schedule, 'capacity' => $capacity, 'educator_count' => $educators,
                    'tuition_amount' => $tuition, 'tuition_frequency' => 'month', 'sort_order' => $i,
                ]);
            }

            $centres[$key] = $centre;
        }

        // The demo centre gets the full profile from the design.
        $ls = $centres['little-sprouts'];
        $ls->update([
            'email' => 'hello@littlesprouts.ca',
            'mission_values' => 'Warmth, curiosity, community.',
            'daily_schedule' => [
                ['time' => '7:30 AM', 'activity' => 'Arrival & free play'],
                ['time' => '9:30 AM', 'activity' => 'Outdoor exploration in the garden'],
                ['time' => '11:45 AM', 'activity' => 'Lunch (made on site)'],
                ['time' => '12:30 PM', 'activity' => 'Rest time'],
                ['time' => '3:00 PM', 'activity' => 'Snack, art & music'],
            ],
            'tuition_info' => '$200–$650 / month depending on age and schedule. Part of the provincial fee reduction program.',
            'tour_info' => 'Tue & Thu at 10 AM or 3:30 PM. About 30 minutes — bring your little one!',
            'waitlist_info' => 'Free to join. Siblings first, then by date joined. We confirm within 2 business days.',
            'licence_number' => 'DEMO-0000',
            'welcome_video_title' => 'A welcome from Anita, our director',
        ]);
        foreach ([['Anita Sharma', 'Director', 18, 'ECE, IT, SN'], ['Mei Lin', 'Assistant Director', 11, 'ECE, IT'], ['Carlos Ruiz', 'Toddler room lead', 7, 'ECE'], ['Grace Park', 'Infant room lead', 9, 'ECE, IT']] as $i => [$name, $title, $years, $creds]) {
            $ls->teamMembers()->create(['name' => $name, 'title' => $title, 'years_experience' => $years, 'credentials' => $creds, 'sort_order' => $i]);
        }
        foreach ([
            ['Are meals provided?', 'Yes — a hot lunch and two snacks are made on site. We accommodate allergies and dietary needs.'],
            ['How do you support toilet learning?', 'We follow each child’s lead and partner with families on a consistent routine at home and at the centre.'],
            ['What is your sick policy?', 'Children with a fever, vomiting or diarrhea stay home for 24 hours after symptoms end.'],
            ['Do you offer part-time spaces?', 'Our preschool program offers 3-day and 5-day options. Infant and toddler spaces are full-time.'],
        ] as $i => [$q, $a]) {
            $ls->faqs()->create(['question' => $q, 'answer' => $a, 'sort_order' => $i]);
        }

        return $centres;
    }

    /** @return array<string, User> */
    private function educators(): array
    {
        $auth = app(AuthService::class);
        $people = [
            'priya' => ['educator@demo.educatorconnect.ca', 'Priya', 'Nair', 'Infant / Toddler Educator', 6, 49.2480, -123.0300, 'V5K 0A1'],
            'jordan' => ['jordan@demo.educatorconnect.ca', 'Jordan', 'Lee', 'Early Childhood Educator', 3, 49.2600, -123.0600, 'V5K 1B2'],
            'maria' => ['maria@demo.educatorconnect.ca', 'Maria', 'Alvarez', 'ECE Assistant', 1, 49.2300, -123.0100, 'V5H 3C1'],
            'tom' => ['tom@demo.educatorconnect.ca', 'Tom', 'Becker', 'Early Childhood Educator', 8, 49.2000, -122.9500, 'V3M 2A1'],
            'daniel' => ['daniel@demo.educatorconnect.ca', 'Daniel', 'Kim', 'ECE student', 0, 49.2400, -123.0200, 'V5J 2B4'],
        ];

        $educators = [];
        foreach ($people as $key => [$email, $first, $last, $title, $years, $lat, $lng, $postal]) {
            $user = $auth->register([
                'role' => 'educator', 'email' => $email, 'password' => self::PASSWORD,
                'first_name' => $first, 'last_name' => $last, 'city' => 'Burnaby', 'province' => 'British Columbia',
                'postal_code' => $postal, 'country' => 'Canada', 'is_student' => $key === 'daniel',
            ]);
            $user->markEmailAsVerified();
            $user->educatorProfile->update([
                'professional_title' => $title,
                'years_experience' => $years,
                'languages' => $key === 'priya' ? ['English', 'Hindi'] : ['English'],
                'latitude' => $lat,
                'longitude' => $lng,
                'employment_preferences' => $key === 'priya' ? ['full_time', 'part_time'] : ['full_time'],
                'availability' => ['Weekdays'],
                'available_from' => now()->addMonth()->startOfMonth(),
                'visibility' => 'verified_centres',
            ]);
            $educators[$key] = $user;
        }

        $priya = $educators['priya']->educatorProfile;
        $priya->update([
            'professional_summary' => 'Infant and toddler educator with six years in group care. I build calm, predictable routines and use primary caregiving so every child has a trusted adult. I love outdoor sensory play and working closely with families during transitions.',
            'age_groups_preferred' => ['Infants (0–18 mo)', 'Toddlers (18 mo–3 yrs)'],
            'areas_of_expertise' => ['Primary caregiving', 'Emergent curriculum', 'Inclusion support'],
            'special_interests' => ['Outdoor sensory play', 'Music & movement'],
            'professional_development' => "Circle of Security (2025)\nTrauma-informed practice (2024)",
            'criminal_record_check_status' => 'completed',
            'criminal_record_checked_on' => now()->subMonths(2),
            'portfolio' => [['title' => 'Learning stories portfolio', 'url' => 'https://drive.google.com/', 'note' => 'Google Drive · shared with centres']],
        ]);
        $priya->forceFill(['is_verified' => true])->save();
        foreach ([
            ['certification', 'Early Childhood Educator Certificate', 'BC ECE Registry', '2028-06-30', 'verified'],
            ['certification', 'Infant & Toddler Educator Certificate', 'BC ECE Registry', '2028-06-30', 'verified'],
            ['certification', 'First Aid & CPR (Level C)', 'Red Cross', '2027-03-31', 'pending'],
            ['education', 'Diploma, Early Childhood Care & Education', 'Graduated 2020', null, 'pending'],
        ] as $i => [$kind, $title, $issuer, $expires, $status]) {
            $credential = $priya->credentials()->create(['kind' => $kind, 'title' => $title, 'issuer' => $issuer, 'expires_on' => $expires, 'sort_order' => $i]);
            $credential->forceFill(['verification_status' => $status])->save();
        }

        $educators['daniel']->educatorProfile->studentProfile->update([
            'college_university' => 'Partner college', 'practicum_level' => 'level_2',
            'practicum_start_date' => Carbon::parse('next year january 6'), 'practicum_end_date' => Carbon::parse('next year march 14'),
            'required_practicum_hours' => 200,
        ]);

        return $educators;
    }

    /** @return array<string, Job> */
    private function jobs(array $centres): array
    {
        $rows = [
            'ls-ece' => ['little-sprouts', 'Early Childhood Educator', 'early_childhood_educator', 'full_time', 2, 26, 30, 2, 312],
            'ls-supervisor' => ['little-sprouts', 'Centre Supervisor', 'supervisor_manager', 'full_time', 1, 32, 36, 7, 148],
            'ls-practicum' => ['little-sprouts', 'ECE Practicum — Level 2', 'early_childhood_educator', 'practicum', 2, null, null, 4, 96],
            'ls-casual' => ['little-sprouts', 'Casual ECE Assistant', 'ece_assistant', 'casual', 2, 22, null, null, 0],
            'ls-infant' => ['little-sprouts', 'Infant / Toddler Educator', 'infant_toddler_educator', 'part_time', 1, 27, null, 60, 520],
            'mg-it' => ['maple-grove', 'Infant / Toddler Educator', 'infant_toddler_educator', 'part_time', 1, 27, null, 3, 210],
            'cm-practicum' => ['cedar-moss', 'ECE Practicum — Level 2', 'early_childhood_educator', 'practicum', 2, null, null, 4, 88],
            'hh-sub' => ['happy-hearts', 'Substitute ECE (On-call)', 'early_childhood_educator', 'substitute', 3, 25, null, 5, 140],
            'cm-supervisor' => ['cedar-moss', 'Centre Supervisor', 'supervisor_manager', 'full_time', 1, 32, 36, 7, 77],
            'bb-ece' => ['bright-beginnings', 'ECE — Preschool room', 'early_childhood_educator', 'full_time', 1, 24, 28, 9, 64],
            'bb-casual' => ['bright-beginnings', 'Casual ECE Assistant', 'ece_assistant', 'casual', 2, 22, null, 7, 51],
            'sp-ece' => ['sunny-park', 'Early Childhood Educator', 'early_childhood_educator', 'full_time', 1, 25, 29, 40, 300],
            'hh-inclusion' => ['happy-hearts', 'Inclusion Support Educator', 'inclusion_support', 'contract', 1, 27, 31, 12, 90],
            'kh-it' => ['kids-harbour', 'Infant / Toddler Educator', 'infant_toddler_educator', 'part_time', 1, 26, null, 6, 45],
        ];

        $jobs = [];
        foreach ($rows as $key => [$centreKey, $title, $category, $type, $positions, $min, $max, $daysAgo, $views]) {
            $centre = $centres[$centreKey];
            $status = match ($key) { 'ls-casual' => 'draft', 'ls-infant', 'sp-ece' => 'closed', default => 'published' };
            $job = $centre->jobs()->create([
                'title' => $title,
                'slug' => $key.'-'.Str::lower(Str::random(5)),
                'category' => $category,
                'employment_type' => $type,
                'number_of_positions' => $positions,
                'city' => $centre->city, 'province' => $centre->province, 'postal_code' => $centre->postal_code, 'country' => 'Canada',
                'latitude' => $centre->latitude, 'longitude' => $centre->longitude,
                'salary_min' => $min, 'salary_max' => $max, 'salary_period' => $min ? 'hour' : null, 'show_pay' => (bool) $min,
                'description' => 'Join our 3–5 room to plan and lead play-based learning for a group of 16 children alongside two co-educators. We value warm, responsive care and close partnerships with families.',
                'responsibilities' => "Plan and document emergent, play-based experiences\nBuild strong relationships with families\nSupport a safe, inclusive environment",
                'benefits' => "Extended health & dental\nPaid professional development\n3 weeks vacation",
                'required_education' => $type === 'practicum' ? 'enrolled_ece_program' : 'ece_certificate',
                'experience_level' => $type === 'practicum' ? 'none' : 'one_year',
                'required_certifications' => ['ece_certificate', 'first_aid_cpr'],
                'practicum_level' => $type === 'practicum' ? 'level_2' : null,
                'practicum_hours' => $type === 'practicum' ? 200 : null,
                'start_date' => now()->addWeeks(4)->toDateString(),
                'application_deadline' => $status === 'closed' ? now()->subDays(5)->toDateString() : now()->addDays(8 + $daysAgo % 20)->toDateString(),
                'status' => $status,
                'published_at' => $status === 'draft' ? null : now()->subDays($daysAgo ?? 0),
                'closed_at' => $status === 'closed' ? now()->subDays(5) : null,
            ]);
            $job->forceFill(['views' => $views])->save();
            $jobs[$key] = $job;
        }

        return $jobs;
    }

    private function applications(array $jobs, array $educators): void
    {
        $priya = $educators['priya'];
        $nextThursday = Carbon::now(config('platform.timezone'))->next(Carbon::THURSDAY)->setTime(10, 0)->utc();

        $make = function (Job $job, User $educator, string $status, Carbon $applied, array $extra = []) {
            $application = JobApplication::create(array_merge([
                'job_id' => $job->id, 'educator_id' => $educator->id, 'status' => $status,
                'applied_at' => $applied, 'status_changed_at' => $applied->copy()->addDays(2),
                'centre_seen_at' => $status === 'new' ? null : $applied->copy()->addDay(),
                'cover_message' => 'Hi! I’m excited about this role — I’m available for a working interview any weekday.',
            ], $extra));

            $path = ['new'];
            foreach (['reviewed', 'shortlisted', 'interview', 'offered', 'hired'] as $step) {
                if (array_search($status, ['new', 'reviewed', 'shortlisted', 'interview', 'offered', 'hired'], true) >= array_search($step, ['new', 'reviewed', 'shortlisted', 'interview', 'offered', 'hired'], true)) {
                    $path[] = $step;
                }
            }
            if ($status === 'not_selected') {
                $path[] = 'not_selected';
            }
            foreach ($path as $i => $step) {
                $event = $application->events()->create(['status' => $step, 'note' => $step === 'interview' && $application->interview_at ? $application->interview_at->format('D, M j, g:i A') : null]);
                $event->forceFill(['created_at' => $applied->copy()->addDays($i * 2), 'updated_at' => $applied->copy()->addDays($i * 2)])->save();
            }

            return $application;
        };

        $interview = $make($jobs['ls-ece'], $priya, 'interview', now()->subDays(15), [
            'interview_at' => $nextThursday,
            'interview_location' => '1420 Kingsway, Burnaby',
            'interview_details' => 'In person at 1420 Kingsway, Burnaby. Ask for Anita (Director).',
        ]);
        $make($jobs['mg-it'], $priya, 'shortlisted', now()->subDays(13));
        $make($jobs['hh-sub'], $priya, 'reviewed', now()->subDays(11));
        $make($jobs['bb-ece'], $priya, 'new', now()->subDays(8));
        $make($jobs['sp-ece'], $priya, 'not_selected', now()->subDays(35));

        $make($jobs['ls-ece'], $educators['jordan'], 'new', now()->subDay());
        $make($jobs['ls-ece'], $educators['maria'], 'reviewed', now()->subDays(9));
        $make($jobs['ls-ece'], $educators['tom'], 'shortlisted', now()->subDays(10));
        $make($jobs['ls-supervisor'], $educators['tom'], 'new', now()->subDays(2));
        $make($jobs['ls-practicum'], $educators['daniel'], 'new', now()->subDays(3), ['interview_at' => null]);

        $interview->messages()->create(['sender_user_id' => $interview->job->centre->owner_user_id, 'sender_role' => 'centre', 'body' => 'Thanks Priya — we’d love to meet you on Thursday at 10:00 AM.']);

        foreach (['ls-ece', 'hh-sub', 'cm-supervisor', 'mg-it', 'kh-it', 'cm-practicum'] as $key) {
            SavedJob::firstOrCreate(['job_id' => $jobs[$key]->id, 'educator_id' => $priya->id]);
        }

        $priya->notifyNow(new ApplicationStatusChangedNotification($interview), ['database']);
        $interview->job->centre->owner->notifyNow(new JobApplicationSubmittedNotification(JobApplication::where('educator_id', $educators['jordan']->id)->first()), ['database']);
    }

    private function families(ChildcareCentre $centre): void
    {
        $thursday = Carbon::now(config('platform.timezone'))->next(Carbon::THURSDAY);
        $requests = [
            ['inquiry', 'Amira M.', 'amira.m@example.com', '(604) 555-0144', '9 months', 'Infant', 'January 2027', 'email', 'Hi! Do you expect any infant spaces opening in January? We live close by and would love to visit.', null, 1],
            ['inquiry', 'David S.', 'david.s@example.com', null, '3 yrs', 'Preschool', 'September 2027', 'email', 'What does a typical day look like for the part-day preschool program?', null, 2],
            ['inquiry', 'Linh T.', 'linh.t@example.com', null, '20 mo', 'Toddler', 'As soon as possible', 'phone', 'Is your centre part of the $10-a-day program?', null, 10],
            ['tour', 'Chen family', 'chen@example.com', '(604) 555-0188', '2 yrs', 'Toddler', 'As soon as possible', 'email', 'We would love to see the toddler room.', $thursday->copy()->setTime(10, 0)->utc(), 1],
            ['tour', 'Okafor family', 'okafor@example.com', null, '11 mo', 'Infant', 'January 2027', 'email', null, $thursday->copy()->setTime(15, 30)->utc(), 1],
            ['waitlist', 'Rosa S.', 'rosa.s@example.com', null, '2 yrs 4 mo', 'Preschool', 'September 2027', 'email', null, null, 3],
            ['waitlist', 'Noah P.', 'noah.p@example.com', null, 'Expecting', 'Infant', 'September 2027', 'email', null, null, 6],
        ];

        foreach ($requests as [$type, $name, $email, $phone, $age, $program, $start, $contact, $message, $when, $daysAgo]) {
            $request = $centre->parentRequests()->make([
                'type' => $type, 'parent_name' => $name, 'email' => $email, 'phone' => $phone, 'child_age' => $age,
                'program_of_interest' => $program, 'desired_start' => $start, 'preferred_contact_method' => $contact,
                'message' => $message, 'requested_at' => $when, 'schedule' => 'Full day',
            ]);
            $request->forceFill(['status' => 'new', 'consent_at' => now()->subDays($daysAgo), 'created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays($daysAgo)])->save();
        }

        $centre->owner->notifyNow(new ParentRequestReceivedNotification($centre->parentRequests()->where('type', 'tour')->first()), ['database']);
    }

    private function profileViews(ChildcareCentre $centre, User $educator): void
    {
        $weekly = [40, 46, 44, 52, 58, 70, 84, 120];
        foreach ($weekly as $weeksAgo => $count) {
            $weekStart = now()->startOfWeek()->subWeeks(7 - $weeksAgo);
            $rows = [];
            for ($i = 0; $i < $count; $i++) {
                $day = $weekStart->copy()->addDays($i % 7);
                if ($day->isFuture()) {
                    $day = now();
                }
                $rows[] = ['viewable_type' => $centre->getMorphClass(), 'viewable_id' => $centre->id, 'viewed_on' => $day->toDateString(), 'created_at' => $day];
            }
            ProfileViewEvent::insert($rows);
        }
        $centre->forceFill(['profile_views' => array_sum($weekly)])->save();

        $profile = $educator->educatorProfile;
        $rows = [];
        for ($i = 0; $i < 23; $i++) {
            $day = now()->subDays($i % 28);
            $rows[] = ['viewable_type' => $profile->getMorphClass(), 'viewable_id' => $profile->id, 'viewed_on' => $day->toDateString(), 'created_at' => $day];
        }
        ProfileViewEvent::insert($rows);
    }

    private function professionals(): void
    {
        $category = fn (string $name) => ProfessionalCategory::where('name', $name)->value('id');
        $listings = [
            ['Maya Chen, SLP', 'Speech-Language Pathologists', 'Speech-Language Pathologist', 'Vancouver', 'Metro Vancouver', 'Early language screening and in-centre visits for 2–5 year olds.', 'claimed_verified', true, false, false],
            ['Brightpath Licensing', 'Licensing Consultants', 'Licensing Consultant', 'Burnaby', 'BC-wide', 'New centre setup, inspection prep and policy reviews.', 'claimed_verified', true, true, false],
            ['Little Ledger Bookkeeping', 'Bookkeepers', 'Bookkeeper', 'New Westminster', 'Lower Mainland', 'Listed by Educator Connect from public business information.', 'unclaimed', false, false, false],
            ['Northshore Play Design', 'Playground Designers', 'Playground Designer', 'North Vancouver', 'British Columbia', 'Natural playscapes and outdoor learning environments.', 'claimed_verified', false, false, true],
        ];
        foreach ($listings as [$name, $categoryName, $title, $city, $area, $description, $status, $verified, $featured, $consult]) {
            $listing = new ProfessionalListing([
                'professional_category_id' => $category($categoryName), 'name' => $name, 'professional_title' => $title,
                'description' => $description, 'city' => $city, 'province' => 'British Columbia', 'postal_code' => 'V5H 0A1',
                'service_area' => $area, 'book_consultation' => $consult,
                // Claimed listings carry contact details, so the directory shows Contact / Book a consultation.
                'email' => $status === 'unclaimed' ? null : 'hello@'.Str::slug($name, '').'.example.ca',
            ]);
            $listing->forceFill(['slug' => Str::slug($name), 'status' => $status, 'is_verified' => $verified, 'is_featured' => $featured])->save();
        }
    }
}
