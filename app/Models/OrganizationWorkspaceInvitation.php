<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationWorkspaceInvitation extends Model
{
    protected $fillable = [
        'organization_id',
        'invited_by_user_id',
        'email',
        'role',
        'token_hash',
        'status',
        'expires_at',
        'accepted_at',
        'cancelled_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending'
            && ! $this->cancelled_at
            && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
