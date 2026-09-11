<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TeamChatMessage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'reply_to_id',
        'body',
        'is_announcement',
        'notify_all',
        'edited_at',
    ];

    protected $casts = [
        'is_announcement' => 'boolean',
        'notify_all' => 'boolean',
        'edited_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(TeamChatConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TeamChatAttachment::class, 'message_id');
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(TeamChatMention::class, 'message_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(TeamChatReaction::class, 'message_id');
    }
}
