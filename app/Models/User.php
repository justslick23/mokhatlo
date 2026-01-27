<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function societies()
    {
        return $this->hasManyThrough(
            Society::class,
            Member::class,
            'user_id',
            'id',
            'id',
            'society_id'
        );
    }

    public function activeMemberships(): HasMany
    {
        return $this->members()->where('status', 'active');
    }

    public function isChairmanOf(Society $society): bool
    {
        return $this->members()
            ->where('society_id', $society->id)
            ->where('role', 'chairman')
            ->exists();
    }

    public function isTreasurerOf(Society $society): bool
    {
        return $this->members()
            ->where('society_id', $society->id)
            ->where('role', 'treasurer')
            ->exists();
    }

    public function isSecretaryOf(Society $society): bool
    {
        return $this->members()
            ->where('society_id', $society->id)
            ->where('role', 'secretary')
            ->exists();
    }

    public function isMemberOf(Society $society): bool
    {
        return $this->members()
            ->where('society_id', $society->id)
            ->exists();
    }

    public function getRoleIn(Society $society): ?string
    {
        $member = $this->members()
            ->where('society_id', $society->id)
            ->first();

        return $member?->role;
    }

    public function canManageSociety(Society $society): bool
    {
        return $this->isChairmanOf($society) || $this->isTreasurerOf($society);
    }
}

