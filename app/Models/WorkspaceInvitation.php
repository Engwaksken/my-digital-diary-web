<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceInvitation extends Model
{
    protected $fillable = [
        'workspace_id', 'invited_by_user_id', 'email', 'role',
        'token_hash', 'status', 'expires_at', 'accepted_at',
    ];

    protected $casts = ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];

    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
}
