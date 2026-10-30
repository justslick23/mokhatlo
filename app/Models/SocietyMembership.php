<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocietyMembership extends Model
{
    protected $fillable = [
        'society_id',
        'user_id',
        'member_number',
        'status',
        'joined_at',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    
public function loans(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(
        \App\Models\Loan::class,
        'society_membership_id'
    );
}

}