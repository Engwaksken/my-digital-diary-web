<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TeamChatAttachment extends Model
{
    protected $fillable = [
        'message_id',
        'uploaded_by',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(TeamChatMessage::class, 'message_id');
    }
}
