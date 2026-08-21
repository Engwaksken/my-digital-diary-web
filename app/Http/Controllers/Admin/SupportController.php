<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    private function agents()
    {
        return User::whereIn('role', ['admin', 'super_admin', 'support'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);
    }

    public function index(Request $request)
    {
        $conversations = SupportConversation::with(['user', 'assignee', 'ratedSupport'])
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.support.index', [
            'conversations' => $conversations,
            'agents' => $this->agents(),
        ]);
    }

    public function show(Request $request, SupportConversation $conversation)
    {
        $conversation->load(['user', 'assignee', 'ratedSupport']);
        $messages = $conversation->messages()
            ->with('user')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();
        $messages->setCollection($messages->getCollection()->reverse()->values());

        return view('admin.support.show', [
            'conversation' => $conversation,
            'agents' => $this->agents(),
            'messages' => $messages,
        ]);
    }

    public function assign(Request $request, SupportConversation $conversation)
    {
        abort_unless(in_array((string) $request->user()->role, ['admin', 'super_admin'], true), 403);
        abort_if($conversation->ended_at, 422, 'This support request has already ended.');

        $data = $request->validate(['assigned_to_user_id' => ['required', 'integer', 'exists:users,id']]);
        $agent = User::whereIn('role', ['admin', 'super_admin', 'support'])->findOrFail($data['assigned_to_user_id']);

        $conversation->update([
            'assigned_to_user_id' => $agent->id,
            'status' => 'human',
            'assigned_at' => now(),
        ]);

        return back()->with('status', 'Support person assigned. AI auto-replies are disabled while the human assignment is active.');
    }

    public function unassign(Request $request, SupportConversation $conversation)
    {
        $user = $request->user();
        $isAdmin = in_array((string) $user->role, ['admin', 'super_admin'], true);
        $isAssignedAgent = (int) $conversation->assigned_to_user_id === (int) $user->id;

        abort_unless($isAdmin || $isAssignedAgent, 403);

        $conversation->update([
            'assigned_to_user_id' => null,
            'assigned_at' => null,
            'status' => $conversation->ended_at ? 'closed' : 'ai',
        ]);

        return back()->with('status', $conversation->ended_at ? 'Support assignment disconnected.' : 'Support person unassigned. AI support is available again.');
    }

    public function reply(Request $request, SupportConversation $conversation)
    {
        abort_if($conversation->ended_at, 422, 'This support request has ended.');

        $user = $request->user();
        if ((string) $user->role === 'support') {
            abort_unless((int) $conversation->assigned_to_user_id === (int) $user->id, 403, 'This conversation is assigned to another support person.');
        }

        $data = $request->validate(['message' => ['required', 'string', 'max:4000']]);
        $conversation->messages()->create([
            'user_id' => $user->id,
            'sender_type' => 'support',
            'message' => trim($data['message']),
        ]);
        $conversation->update(['status' => 'human', 'last_message_at' => now()]);

        return back();
    }
}
