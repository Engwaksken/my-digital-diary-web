<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceMember extends Model
{
    protected $fillable = [
        'workspace_id', 'user_id', 'role', 'status', 'permissions', 'joined_at', 'left_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function canManageMembers(): bool
    {
        return in_array($this->role, ['owner', 'admin'], true)
            || (bool) data_get($this->permissions, 'members.manage', false);
    }
}
