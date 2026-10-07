<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'role', 'status',
        'oauth_provider', 'oauth_provider_id', 'email_verified_at', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function childcareCentres() { return $this->hasMany(ChildcareCentre::class, 'owner_user_id'); }
    public function centre() { return $this->hasOne(ChildcareCentre::class, 'owner_user_id')->oldestOfMany(); }
    public function educatorProfile() { return $this->hasOne(EducatorProfile::class); }
    public function applications() { return $this->hasMany(JobApplication::class, 'educator_id'); }
    public function savedJobs() { return $this->hasMany(SavedJob::class, 'educator_id'); }
    public function professionalListings() { return $this->hasMany(ProfessionalListing::class, 'owner_user_id'); }
    public function claims() { return $this->hasMany(ProfessionalClaim::class); }
    public function addresses() { return $this->morphMany(Address::class, 'addressable'); }
    public function socialAccounts() { return $this->hasMany(SocialAccount::class); }
    public function notificationPreference() { return $this->hasOne(NotificationPreference::class); }

    public function isEducator(): bool { return $this->role === UserRole::EDUCATOR->value; }
    public function isCentre(): bool { return $this->role === UserRole::CHILDCARE_CENTRE->value; }
    public function isAdministrator(): bool { return $this->role === UserRole::ADMINISTRATOR->value; }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }
}
