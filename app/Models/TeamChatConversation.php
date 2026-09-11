<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TeamChatConversation extends Model
{
    protected $fillable = [
        'organization_id',
        'created_by',
        'type',
        'name',
        'description',
        'is_general',
        'is_announcement_only',
        'is_archived',
    ];

    protected $casts = [
        'is_general' => 'boolean',
        'is_announcement_only' => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamChatConversationMember::class, 'conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TeamChatMessage::class, 'conversation_id');
    }
}
