<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAuditLog;
use App\Models\WorkspaceMember;
use Illuminate\Http\Request;

class WorkspaceAccessService
{
    public const NEVER_IMPLICITLY_SHARED = [
        'diary', 'reflection', 'health', 'self_care', 'spiritual_growth',
        'signature', 'private_document', 'personal_finance', 'ai_private_insight',
    ];

    public const SHAREABLE_TYPES = [
        'task', 'goal', 'meeting', 'reminder', 'budget',
        'contribution', 'document', 'announcement',
    ];

    public function membership(User $user, Workspace $workspace): ?WorkspaceMember
    {
        return WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
    }

    public function canViewWorkspace(User $user, Workspace $workspace): bool
    {
        return (bool) $this->membership($user, $workspace);
    }

    public function canManageMembers(User $user, Workspace $workspace): bool
    {
        return $this->membership($user, $workspace)?->canManageMembers() ?? false;
    }

    public function assertShareableType(string $type): void
    {
        abort_unless(
            in_array($type, self::SHAREABLE_TYPES, true),
            422,
            'This personal record type cannot be shared into a workspace.'
        );
    }

    public function log(
        Workspace $workspace,
        ?User $actor,
        string $action,
        mixed $subject = null,
        array $metadata = [],
        ?Request $request = null
    ): void {
        WorkspaceAuditLog::query()->create([
            'workspace_id' => $workspace->id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => is_object($subject) ? $subject::class : null,
            'subject_id' => is_object($subject) && isset($subject->id) ? $subject->id : null,
            'metadata' => $metadata ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
