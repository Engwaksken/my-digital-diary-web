<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaPost;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SocialMediaPlannerController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            SocialMediaPost::query()
                ->where('user_id', $request->user()->id)
                ->orderByRaw('scheduled_at IS NULL')
                ->orderBy('scheduled_at')
                ->paginate((int) $request->input('per_page', 20))
        );
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $post = SocialMediaPost::create([
            ...$data,
            'user_id' => $request->user()->id,
            'status' => !empty($data['scheduled_at']) ? 'scheduled' : 'draft',
        ]);

        return response()->json(['data' => $post], 201);
    }

    public function update(Request $request, SocialMediaPost $socialMediaPost)
    {
        abort_unless($socialMediaPost->user_id === $request->user()->id, 403);

        $data = $this->validated($request);
        $data['status'] = !empty($data['scheduled_at']) ? 'scheduled' : 'draft';

        $socialMediaPost->update($data);

        return response()->json(['data' => $socialMediaPost->fresh()]);
    }

    public function destroy(Request $request, SocialMediaPost $socialMediaPost)
    {
        abort_unless($socialMediaPost->user_id === $request->user()->id, 403);
        $socialMediaPost->delete();

        return response()->json(['ok' => true]);
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
        ]);

        $deleted = SocialMediaPost::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $data['ids'])
            ->delete();

        return response()->json([
            'ok' => true,
            'deleted' => $deleted,
        ]);
    }

    public function postNow(
        Request $request,
        SocialMediaPost $socialMediaPost
    ) {
        abort_unless(
            $socialMediaPost->user_id === $request->user()->id,
            403
        );

        if ($socialMediaPost->status !== 'published') {
            $socialMediaPost->forceFill([
                'status' => 'ready_to_share',
            ])->save();
        }

        return response()->json([
            'data' => [
                'id' => $socialMediaPost->id,
                'title' => $socialMediaPost->title,
                'text' => $socialMediaPost->shareText(),
                'platforms' => $socialMediaPost->platforms ?? [],
                'status' => $socialMediaPost->status,
            ],
        ]);
    }

    public function markPublished(
        Request $request,
        SocialMediaPost $socialMediaPost
    ) {
        abort_unless(
            $socialMediaPost->user_id === $request->user()->id,
            403
        );

        $socialMediaPost->forceFill([
            'status' => 'published',
            'published_at' => now(),
            'last_error' => null,
        ])->save();

        return response()->json([
            'data' => $socialMediaPost->fresh(),
        ]);
    }

    public function readyToShare(Request $request)
    {
        $posts = SocialMediaPost::query()
            ->where('user_id', $request->user()->id)
            ->where(function ($query) {
                $query->where('status', 'ready_to_share')
                    ->orWhere(function ($due) {
                        $due->where('status', 'scheduled')
                            ->whereNotNull('scheduled_at')
                            ->where('scheduled_at', '<=', now());
                    });
            })
            ->orderBy('scheduled_at')
            ->get();

        return response()->json(['data' => $posts]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'campaign_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:180'],
            'caption' => ['nullable', 'string', 'max:10000'],
            'hashtags' => ['nullable', 'string', 'max:2000'],
            'media_type' => ['required', Rule::in(['text','image','video','carousel'])],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => [Rule::in([
                'instagram','facebook','x','tiktok','linkedin',
                'whatsapp_status','whatsapp_channel'
            ])],
            'platform_content' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date'],
            'approval_status' => ['nullable', Rule::in([
                'draft','pending','approved','rejected'
            ])],
            'posting_mode' => [
                'nullable',
                Rule::in(['manual','automatic']),
            ],
        ]);
    }
}
