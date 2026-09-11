<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OrganizationMember;
use App\Models\TeamChatAttachment;
use App\Models\TeamChatConversation;
use App\Models\TeamChatConversationMember;
use App\Models\TeamChatMessage;
use App\Models\TeamChatReaction;
use App\Models\User;
use App\Services\TeamChatAccessService;
use App\Services\TeamChatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TeamChatController extends Controller
{
    public function __construct(
        private readonly TeamChatAccessService $access,
        private readonly TeamChatService $chat
    ) {}

    public function index(Request $request): View
    {
        $organization = $this->access->managedOrganization($request->user());

        $general = $this->access->ensureGeneralChannel(
            $organization,
            $request->user()
        );

        $conversation = $this->access
            ->visibleConversations($request->user(), $organization)
            ->orderByDesc('is_general')
            ->orderByDesc('updated_at')
            ->first() ?? $general;

        return $this->renderWorkspace($request, $organization, $conversation);
    }

    public function show(
        Request $request,
        TeamChatConversation $conversation
    ): View {
        $organization = $this->access->assertConversationAccess(
            $request->user(),
            $conversation
        );

        return $this->renderWorkspace($request, $organization, $conversation);
    }

    public function storeConversation(Request $request): RedirectResponse
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
            return back()->withErrors(['conversation' => $e->getMessage()]);
        }

        return redirect()
            ->route('team-chat.show', $conversation)
            ->with('success', 'Conversation created.');
    }

    public function storeMessage(
        Request $request,
        TeamChatConversation $conversation
    ): RedirectResponse {
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
            $this->chat->createMessage(
                $conversation,
                $request->user(),
                $data,
                $request->file('attachments', [])
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }

        return redirect()
            ->route('team-chat.show', $conversation)
            ->with('success', 'Message sent.');
    }

    public function updateMessage(
        Request $request,
        TeamChatMessage $message
    ): RedirectResponse {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        try {
            $this->chat->editMessage(
                $message,
                $request->user(),
                $data['body']
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }

        return back()->with('success', 'Message updated.');
    }

    public function destroyMessage(
        Request $request,
        TeamChatMessage $message
    ): RedirectResponse {
        try {
            $this->chat->deleteMessage($message, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }

        return back()->with('success', 'Message deleted.');
    }

    public function react(
        Request $request,
        TeamChatMessage $message
    ): RedirectResponse {
        $this->access->assertConversationAccess(
            $request->user(),
            $message->conversation
        );

        $data = $request->validate([
            'reaction' => ['required', Rule::in(['like', 'love', 'celebrate', 'support'])],
        ]);

        $reaction = TeamChatReaction::query()
            ->where('message_id', $message->id)
            ->where('user_id', $request->user()->id)
            ->where('reaction', $data['reaction'])
            ->first();

        if ($reaction) {
            $reaction->delete();
        } else {
            TeamChatReaction::create([
                'message_id' => $message->id,
                'user_id' => $request->user()->id,
                'reaction' => $data['reaction'],
            ]);
        }

        return back();
    }

    public function downloadAttachment(
        Request $request,
        TeamChatAttachment $attachment
    ): StreamedResponse {
        $attachment->load('message.conversation');

        $this->access->assertConversationAccess(
            $request->user(),
            $attachment->message->conversation
        );

        abort_unless(
            Storage::disk($attachment->disk)->exists($attachment->path),
            404
        );

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name
        );
    }

    public function markRead(
        Request $request,
        TeamChatConversation $conversation
    ): RedirectResponse {
        $organization = $this->access->assertConversationAccess(
            $request->user(),
            $conversation
        );

        TeamChatConversationMember::query()->updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'user_id' => $request->user()->id,
            ],
            ['last_read_at' => now()]
        );

        return back();
    }

    public function archiveConversation(
        Request $request,
        TeamChatConversation $conversation
    ): RedirectResponse {
        $organization = $this->access->assertConversationAccess(
            $request->user(),
            $conversation
        );

        abort_unless(
            $this->access->canManage($organization, $request->user()),
            403
        );

        abort_if($conversation->is_general, 422, 'General cannot be archived.');

        $conversation->update(['is_archived' => true]);

        return redirect()
            ->route('team-chat.index')
            ->with('success', 'Conversation archived.');
    }

    private function renderWorkspace(
        Request $request,
        $organization,
        TeamChatConversation $conversation
    ): View {
        $user = $request->user();

        $conversations = $this->access
            ->visibleConversations($user, $organization)
            ->withCount([
                'messages as unread_count' => function ($query) use ($user) {
                    $query->where('user_id', '!=', $user->id)
                        ->whereRaw(
                            'team_chat_messages.created_at > COALESCE(('
                            .'SELECT last_read_at FROM team_chat_conversation_members '
                            .'WHERE conversation_id = team_chat_messages.conversation_id '
                            .'AND user_id = ? LIMIT 1'
                            .'), "1970-01-01")',
                            [$user->id]
                        );
                },
            ])
            ->orderByDesc('is_general')
            ->orderByDesc('updated_at')
            ->get();

        $messages = $conversation->messages()
            ->with([
                'user:id,name,email',
                'attachments',
                'replyTo.user:id,name',
                'reactions',
            ])
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        TeamChatConversationMember::query()->updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
            ],
            ['last_read_at' => now()]
        );

        $members = OrganizationMember::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->with('user:id,name,email')
            ->get()
            ->filter(fn ($member) => $member->user !== null)
            ->values();

        return view('team-chat.index', [
            'organization' => $organization,
            'conversation' => $conversation,
            'conversations' => $conversations,
            'messages' => $messages,
            'members' => $members,
            'canManage' => $this->access->canManage($organization, $user),
        ]);
    }
}
