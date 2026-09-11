<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceAuditLog extends Model
{
    protected $fillable = [
        'workspace_id', 'actor_user_id', 'action', 'subject_type', 'subject_id',
        'metadata', 'ip_address', 'user_agent',
    ];

    protected $casts = ['metadata' => 'array'];
}
