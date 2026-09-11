<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrganizationMember;
use App\Models\TeamChatAttachment;
use App\Models\TeamChatConversation;
use App\Models\TeamChatConversationMember;
use App\Models\TeamChatMessage;
use App\Models\TeamChatReaction;
use App\Services\TeamChatAccessService;
use App\Services\TeamChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TeamChatController extends Controller
{
    public function __construct(
        private readonly TeamChatAccessService $access,
        private readonly TeamChatService $chat
    ) {}

    public function conversations(Request $request): JsonResponse
    {
        try {
            $organization = $this->access->managedOrganization($request->user());
            $this->access->ensureGeneralChannel($organization, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $user = $request->user();

        $rows = $this->access
            ->visibleConversations($user, $organization)
            ->orderByDesc('is_general')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (TeamChatConversation $conversation) use ($user) {
                $member = TeamChatConversationMember::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('user_id', $user->id)
                    ->first();

                $lastRead = $member?->last_read_at;

                $unread = $conversation->messages()
                    ->where('user_id', '!=', $user->id)
                    ->when(
                        $lastRead,
                        fn ($query) => $query->where('created_at', '>', $lastRead)
                    )
                    ->count();

                $last = $conversation->messages()
                    ->with('user:id,name')
                    ->latest('id')
                    ->first();

                return [
                    'id' => $conversation->id,
                    'type' => $conversation->type,
                    'name' => $conversation->name ?: 'Conversation',
                    'description' => $conversation->description,
                    'is_general' => $conversation->is_general,
                    'is_announcement_only' => $conversation->is_announcement_only,
                    'unread_count' => $unread,
                    'last_message' => $last ? [
                        'body' => $last->body,
                        'sender_name' => $last->user?->name,
                        'created_at' => $last->created_at?->toIso8601String(),
                    ] : null,
                ];
            });

        return response()->json([
            'data' => [
                'organization' => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                ],
                'can_manage' => $this->access->canManage($organization, $user),
                'conversations' => $rows,
            ],
        ]);
    }

    public function show(
        Request $request,
        TeamChatConversation $conversation
    ): JsonResponse {
        try {
            $organization = $this->access->assertConversationAccess(
                $request->user(),
                $conversation
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $messages = $conversation->messages()
            ->with([
                'user:id,name,email',
                'attachments',
                'replyTo.user:id,name',
                'reactions',
            ])
            ->latest('id')
            ->paginate(40);

        TeamChatConversationMember::query()->updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'user_id' => $request->user()->id,
            ],
            ['last_read_at' => now()]
        );

        $members = OrganizationMember::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->with('user:id,name,email')
            ->get()
            ->filter(fn ($membership) => $membership->user)
            ->map(fn ($membership) => [
                'user_id' => $membership->user->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role,
            ])
            ->values();

        return response()->json([
            'data' => [
                'conversation' => [
                    'id' => $conversation->id,
                    'name' => $conversation->name ?: 'Conversation',
                    'type' => $conversation->type,
                    'description' => $conversation->description,
                    'is_general' => $conversation->is_general,
                    'is_announcement_only' => $conversation->is_announcement_only,
                ],
                'can_manage' => $this->access->canManage(
                    $organization,
                    $request->user()
                ),
                'members' => $members,
                'messages' => collect($messages->items())
                    ->map(fn (TeamChatMessage $message) =>
                        $this->messageArray($message)
                    ),
                'meta' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                ],
            ],
        ]);
    }

    public function createConversation(Request $request): JsonResponse
    {
        $organization = $this->access->managedOrganization($request->user());

        $data = $request->validate([
            'type' => ['required', Rule::in(['channel', 'direct', 'group'])],
            'name' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'member_user_ids' => ['nullable', 'array'],
            'member_user_ids.*' => ['integer'],
            'is_announcement_only' => ['nullable', 'boolean'],
        ]);

        try {
            $conversation = $this->chat->createConversation(
                $organization,
                $request->user(),
                $data
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Conversation created.',
            'data' => ['id' => $conversation->id],
        ], 201);
    }

    public function send(
        Request $request,
        TeamChatConversation $conversation
    ): JsonResponse {
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:10000'],
            'reply_to_id' => ['nullable', 'integer'],
            'is_announcement' => ['nullable', 'boolean'],
            'notify_all' => ['nullable', 'boolean'],
            'mention_user_ids' => ['nullable', 'array'],
            'mention_user_ids.*' => ['integer'],
            'attachments' => ['nullable', 'array', 'max:8'],
            'attachments.*' => [
                'file',
                'max:20480',
                'mimes:pdf,doc,docx,xls,xlsx,csv,ppt,pptx,txt,jpg,jpeg,png,webp,gif,mp3,m4a,wav,mp4,mov',
            ],
        ]);

        try {
            $message = $this->chat->createMessage(
                $conversation,
                $request->user(),
                $data,
                $request->file('attachments', [])
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Message sent.',
            'data' => $this->messageArray($message),
        ], 201);
    }

    public function updateMessage(
        Request $request,
        TeamChatMessage $message
    ): JsonResponse {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        try {
            $message = $this->chat->editMessage(
                $message,
                $request->user(),
                $data['body']
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Message updated.',
            'data' => $this->messageArray($message),
        ]);
    }

    public function deleteMessage(
        Request $request,
        TeamChatMessage $message
    ): JsonResponse {
        try {
            $this->chat->deleteMessage($message, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Message deleted.']);
    }

    public function react(
        Request $request,
        TeamChatMessage $message
    ): JsonResponse {
        try {
            $this->access->assertConversationAccess(
                $request->user(),
                $message->conversation
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $data = $request->validate([
            'reaction' => ['required', Rule::in(['like', 'love', 'celebrate', 'support'])],
        ]);

        $existing = TeamChatReaction::query()
            ->where('message_id', $message->id)
            ->where('user_id', $request->user()->id)
            ->where('reaction', $data['reaction'])
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            TeamChatReaction::create([
                'message_id' => $message->id,
                'user_id' => $request->user()->id,
                'reaction' => $data['reaction'],
            ]);
        }

        return response()->json(['message' => 'Reaction updated.']);
    }

    public function downloadAttachment(
        Request $request,
        TeamChatAttachment $attachment
    ): StreamedResponse {
        $attachment->load('message.conversation');

        try {
            $this->access->assertConversationAccess(
                $request->user(),
                $attachment->message->conversation
            );
        } catch (RuntimeException $e) {
            abort(403, $e->getMessage());
        }

        abort_unless(
            Storage::disk($attachment->disk)->exists($attachment->path),
            404
        );

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name
        );
    }

    private function messageArray(TeamChatMessage $message): array
    {
        $message->loadMissing([
            'user:id,name,email',
            'attachments',
            'replyTo.user:id,name',
            'reactions',
        ]);

        return [
            'id' => $message->id,
            'body' => $message->body,
            'user_id' => $message->user_id,
            'sender_name' => $message->user?->name ?? 'Member',
            'sender_email' => $message->user?->email,
            'is_announcement' => $message->is_announcement,
            'notify_all' => $message->notify_all,
            'edited_at' => $message->edited_at?->toIso8601String(),
            'created_at' => $message->created_at?->toIso8601String(),
            'reply_to' => $message->replyTo ? [
                'id' => $message->replyTo->id,
                'body' => $message->replyTo->body,
                'sender_name' => $message->replyTo->user?->name,
            ] : null,
            'attachments' => $message->attachments->map(
                fn ($attachment) => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size_bytes' => $attachment->size_bytes,
                    'download_path' =>
                        '/api/team-chat/attachments/'.$attachment->id,
                ]
            )->values(),
            'reactions' => $message->reactions
                ->groupBy('reaction')
                ->map(fn ($items, $reaction) => [
                    'reaction' => $reaction,
                    'count' => $items->count(),
                ])
                ->values(),
        ];
    }
}
