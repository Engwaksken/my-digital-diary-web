<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Services\SupportAiService;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function show(Request $request)
    {
        $c=SupportConversation::where('user_id',$request->user()->id)->whereNull('ended_at')->where('status','!=','closed')->latest()->first();
        if (!$c) $c=SupportConversation::create(['user_id'=>$request->user()->id,'status'=>'ai']);
        $c->load(['messages' => fn ($query) => $query->oldest('id')]);
        return response()->json(['data'=>$c]);
    }
    public function send(Request $request, SupportAiService $ai)
    {
        $data=$request->validate(['message'=>['required','string','max:4000']]);
        $c=SupportConversation::where('user_id',$request->user()->id)->whereNull('ended_at')->where('status','!=','closed')->latest()->first() ?: SupportConversation::create(['user_id'=>$request->user()->id,'status'=>'ai']);
        $c->messages()->create(['user_id'=>$request->user()->id,'sender_type'=>'user','message'=>$data['message']]);
        if (!$c->assigned_to_user_id && ($reply=$ai->reply($c,$data['message']))) $c->messages()->create(['sender_type'=>'ai','message'=>$reply]);
        $c->update(['last_message_at'=>now()]);
        return $this->show($request);
    }
}
