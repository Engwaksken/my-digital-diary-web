<?php
namespace App\Http\Controllers;
use App\Models\SocialMediaPost;
use App\Services\SocialMediaAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class SocialMediaAnalyticsController extends Controller {
    public function show(Request $request, SocialMediaPost $socialMediaPost) {
        abort_unless($socialMediaPost->user_id===$request->user()->id,403);
        $socialMediaPost->load(['metrics','metricSnapshots'=>fn($q)=>$q->latest('captured_at')->limit(100)]);
        return view('social-media-planner.analytics',['post'=>$socialMediaPost]);
    }

    public function sync(Request $request, SocialMediaPost $socialMediaPost, SocialMediaAnalyticsService $analytics) {
        abort_unless($socialMediaPost->user_id===$request->user()->id,403);
        $results=$analytics->syncPost($socialMediaPost);
        $synced=collect($results)->where('ok',true)->count();
        $failed=collect($results)->where('ok',false)->where('skipped','!=',true)->count();
        return back()->with($failed>0 && $synced===0 ? 'error' : 'success', "Analytics sync finished: {$synced} synced, {$failed} failed.");
    }

    public function update(Request $request, SocialMediaPost $socialMediaPost, SocialMediaAnalyticsService $analytics) {
        abort_unless($socialMediaPost->user_id===$request->user()->id,403);
        $data=$request->validate([
            'platform'=>['required',Rule::in(['instagram','facebook','x','tiktok','linkedin','whatsapp_status','whatsapp_channel'])],
            'views'=>['nullable','integer','min:0'],'reach'=>['nullable','integer','min:0'],'impressions'=>['nullable','integer','min:0'],
            'likes'=>['nullable','integer','min:0'],'comments'=>['nullable','integer','min:0'],'shares'=>['nullable','integer','min:0'],
            'saves'=>['nullable','integer','min:0'],'clicks'=>['nullable','integer','min:0'],'replies'=>['nullable','integer','min:0'],
            'external_post_id'=>['nullable','string','max:255'],
        ]);
        $analytics->saveManual($socialMediaPost,$data['platform'],$data);
        return back()->with('success','Post performance updated.');
    }
}
