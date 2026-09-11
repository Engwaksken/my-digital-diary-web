<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceSharedItem extends Model
{
    protected $fillable = [
        'workspace_id', 'owner_user_id', 'item_type', 'item_id',
        'visibility', 'shared_with_user_ids', 'shared_at',
    ];

    protected $casts = ['shared_with_user_ids' => 'array', 'shared_at' => 'datetime'];
}
