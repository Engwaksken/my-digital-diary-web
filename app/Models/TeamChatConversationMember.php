<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TeamChatConversationMember extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'last_read_at',
        'muted_until',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
        'muted_until' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(TeamChatConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
