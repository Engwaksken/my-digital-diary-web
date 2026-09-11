<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Workspace extends Model
{
    protected $fillable = [
        'owner_user_id', 'name', 'type', 'slug', 'status', 'member_limit',
        'billing_owner_type', 'billing_owner_id', 'settings',
    ];

    protected $casts = ['settings' => 'array'];

    protected static function booted(): void
    {
        static::creating(function (Workspace $workspace) {
            if (! $workspace->slug) {
                $workspace->slug = (Str::slug($workspace->name) ?: 'workspace').'-'.Str::lower(Str::random(6));
            }

            if (in_array($workspace->type, ['family', 'small_team'], true) && ! $workspace->member_limit) {
                $workspace->member_limit = 5;
            }
        });
    }

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function members(): HasMany { return $this->hasMany(WorkspaceMember::class); }
    public function activeMembers(): HasMany { return $this->members()->where('status', 'active'); }
    public function invitations(): HasMany { return $this->hasMany(WorkspaceInvitation::class); }
    public function auditLogs(): HasMany { return $this->hasMany(WorkspaceAuditLog::class); }

    public function isAtMemberLimit(): bool
    {
        return $this->member_limit !== null && $this->activeMembers()->count() >= $this->member_limit;
    }
}
