<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Filled by the framework at runtime (sign-ins, queue, cache), never by seed data. */
    private const RUNTIME_TABLES = [
        'migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions',
        'password_reset_tokens', 'personal_access_tokens', 'oauth_login_codes', 'oauth_pending_signups',
        // Permissions are granted through roles, so no account holds one directly.
        'model_has_permissions',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_every_business_table_has_rows(): void
    {
        $this->seed(DemoSeeder::class);

        $empty = collect(Schema::getTables())->pluck('name')
            ->reject(fn ($table) => in_array($table, self::RUNTIME_TABLES, true) || str_starts_with($table, 'sqlite_'))
            ->filter(fn ($table) => DB::table($table)->doesntExist())
            ->values()->all();

        $this->assertSame([], $empty, 'Tables left empty by DemoSeeder: '.implode(', ', $empty));
    }

    public function test_demo_media_files_exist_and_are_linked(): void
    {
        $this->seed(DemoSeeder::class);

        $owner = User::where('email', 'centre@demo.educatorconnect.ca')->firstOrFail();
        $centre = $owner->centre;
        Storage::disk('public')->assertExists($centre->logo_path);
        Storage::disk('public')->assertExists($centre->gallery()->first()->file_path);
        $this->assertSame(12, $centre->gallery()->count());

        $priya = User::where('email', 'educator@demo.educatorconnect.ca')->firstOrFail()->educatorProfile;
        Storage::disk('local')->assertExists($priya->resume_path);
        $this->assertStringStartsWith('%PDF-1.4', Storage::disk('local')->get($priya->resume_path));
        $this->assertSame('https://priyanair.ca', $priya->website);
        $this->assertSame('https://little-sprouts-daycare.ca', $centre->website);
    }

    public function test_seeding_sends_no_email_and_runs_once(): void
    {
        Mail::fake();
        $this->seed(DemoSeeder::class);
        $users = User::count();

        $this->seed(DemoSeeder::class);

        $this->assertSame($users, User::count());
        Mail::assertNothingOutgoing();
    }

    public function test_production_needs_the_explicit_switch(): void
    {
        $this->app['env'] = 'production';
        config(['platform.demo.allow_seed' => false]);

        $this->expectException(RuntimeException::class);
        $this->runSeederDirectly();
    }

    public function test_production_seed_gives_the_admin_a_random_password(): void
    {
        $this->app['env'] = 'production';
        config(['platform.demo.allow_seed' => true]);

        $this->runSeederDirectly();

        $this->postJson('/api/v1/auth/login', ['email' => 'admin@demo.educatorconnect.ca', 'password' => DemoSeeder::PASSWORD])
            ->assertStatus(401);
    }

    /** `db:seed` asks for confirmation in production; the seeder itself is what's under test. */
    private function runSeederDirectly(): void
    {
        $this->app->make(DemoSeeder::class)->setContainer($this->app)->__invoke();
    }

    public function test_purge_removes_demo_data_and_keeps_real_accounts(): void
    {
        $this->seed(DemoSeeder::class);
        $real = User::factory()->create(['role' => 'educator', 'email' => 'real.person@example.com']);

        $this->artisan('demo:purge', ['--force' => true])->assertSuccessful();

        $this->assertSame([$real->id], User::pluck('id')->all());
        foreach (['childcare_centres', 'user_jobs', 'job_applications', 'educator_profiles', 'professional_listings',
            'professional_claims', 'messages', 'notifications', 'profile_view_events', 'addresses', 'audit_logs',
            'practicum_opportunities', 'centre_gallery_items', 'social_accounts', 'model_has_roles'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), "{$table} still has demo rows");
        }
        // Reference data stays.
        $this->assertGreaterThan(0, DB::table('professional_categories')->count());
        $this->assertFalse(Storage::disk('public')->exists('demo'));

        // And it can be loaded again afterwards.
        $this->seed(DemoSeeder::class);
        $this->assertTrue(User::where('email', 'centre@demo.educatorconnect.ca')->exists());
    }
}
