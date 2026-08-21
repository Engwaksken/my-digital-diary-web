<?php

namespace App\Http\Controllers;

use App\Models\SupportConversation;
use App\Services\SupportAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    private function conversation(Request $request): SupportConversation
    {
        $latest = SupportConversation::where('user_id', $request->user()->id)
            ->whereNull('ended_at')
            ->where('status', '!=', 'closed')
            ->latest('last_message_at')
            ->latest('id')
            ->first();

        return $latest ?: SupportConversation::create([
            'user_id' => $request->user()->id,
            'status' => 'ai',
            'last_message_at' => now(),
        ]);
    }

    public function index(Request $request)
    {
        $conversation = $this->conversation($request);
        $conversation->load(['assignee']);
        $messages = $conversation->messages()
            ->with('user')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();
        $messages->setCollection($messages->getCollection()->reverse()->values());

        $lastSupportUserId = $conversation->messages()->where('sender_type', 'support')->whereNotNull('user_id')->latest('id')->value('user_id');
        $supportPerson = $conversation->assignee ?: ($lastSupportUserId ? \App\Models\User::find($lastSupportUserId) : null);

        return view('support.index', compact('conversation', 'messages', 'supportPerson'));
    }

    public function send(Request $request, SupportAiService $ai)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:4000']]);
        $conversation = $this->conversation($request);

        $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'sender_type' => 'user',
            'message' => trim($data['message']),
        ]);
        $conversation->update(['last_message_at' => now()]);

        if (! $conversation->assigned_to_user_id) {
            if ($reply = $ai->reply($conversation, trim($data['message']))) {
                $conversation->messages()->create(['sender_type' => 'ai', 'message' => $reply]);
                $conversation->update(['last_message_at' => now()]);
            }
        }

        return back();
    }

    public function end(Request $request)
    {
        $conversation = $this->conversation($request);
        $lastSupportUserId = $conversation->messages()
            ->where('sender_type', 'support')
            ->whereNotNull('user_id')
            ->latest('id')
            ->value('user_id');
        $supportUserId = $conversation->assigned_to_user_id ?: $lastSupportUserId;

        $rules = ['comment' => ['nullable', 'string', 'max:1000']];
        $rules['rating'] = $supportUserId
            ? ['required', 'integer', 'between:1,5']
            : ['nullable', 'integer', 'between:1,5'];
        $data = $request->validate($rules);

        $conversation->update([
            'status' => 'closed',
            'ended_at' => now(),
            'ended_by' => 'user',
            'rated_support_user_id' => $supportUserId,
            'support_rating' => $data['rating'] ?? null,
            'support_rating_comment' => $data['comment'] ?? null,
        ]);

        return redirect()->route('support.index')->with('status', 'Support request ended. Thank you for your feedback.');
    }

    public function widgetHistory(Request $request): JsonResponse
    {
        $conversation = $this->conversation($request);
        $conversation->load('assignee');

        $messages = $conversation->messages()->with('user:id,name')->latest('id')->limit(30)->get()->reverse()->values()
            ->map(fn ($message) => [
                'id' => $message->id,
                'sender_type' => $message->sender_type,
                'sender' => $message->sender_type === 'user' ? 'You' : ($message->sender_type === 'ai' ? 'My Digital Diary AI' : ($message->user?->name ?? 'Support')),
                'message' => $message->sender_type === 'ai' ? SupportAiService::plainText($message->message) : $message->message,
                'created_at' => optional($message->created_at)->format('H:i'),
            ]);

        return response()->json([
            'messages' => $messages,
            'mode' => $conversation->assigned_to_user_id ? 'human' : 'ai',
            'assignee' => $conversation->assignee?->name,
            'full_chat_url' => route('support.index'),
        ]);
    }

    public function widgetSend(Request $request, SupportAiService $ai): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:4000']]);
        $conversation = $this->conversation($request);
        $text = trim($data['message']);

        $userMessage = $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'sender_type' => 'user',
            'message' => $text,
        ]);
        $conversation->update(['last_message_at' => now()]);

        $replyMessage = null;
        if (! $conversation->assigned_to_user_id && ($reply = $ai->reply($conversation, $text))) {
            $replyMessage = $conversation->messages()->create(['sender_type' => 'ai', 'message' => $reply]);
            $conversation->update(['last_message_at' => now()]);
        }

        return response()->json([
            'ok' => true,
            'mode' => $conversation->assigned_to_user_id ? 'human' : 'ai',
            'user_message' => ['id' => $userMessage->id, 'sender_type' => 'user', 'sender' => 'You', 'message' => $userMessage->message],
            'reply' => $replyMessage ? ['id' => $replyMessage->id, 'sender_type' => 'ai', 'sender' => 'My Digital Diary AI', 'message' => SupportAiService::plainText($replyMessage->message)] : null,
            'waiting_for_human' => (bool) $conversation->assigned_to_user_id,
        ]);
    }
}
