<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Society extends Model
{
    protected $fillable = [
        'name',
        'description',
        'currency_code',
        'timezone',
        'status',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(
            SocietyMembership::class
        );
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(Cycle::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(
            SocietyInvitation::class
        );
    }

    
public function loans(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(
        \App\Models\Loan::class
    );
}

}
