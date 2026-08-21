<?php

namespace App\Http\Controllers;

use App\Models\UserRecycleBinItem;
use App\Services\UserDataVaultService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserDataController extends Controller
{
    public function index(Request $request)
    {
        $user=$request->user();
        $usage=$this->usage($request)->getData(true);
        $trash=UserRecycleBinItem::where('user_id',$user->id)->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()))->latest('deleted_at')->paginate(15);
        return view('account-data.index', compact('usage','trash'));
    }

    public function usage(Request $request)
    {
        $userId=$request->user()->id; $modules=[]; $total=0; $used=0;
        foreach (UserDataVaultService::MODELS as $class) {
            if (!method_exists($class,'query')) continue;
            try { $count=$class::where('user_id',$userId)->count(); } catch (\Throwable $e) { continue; }
            $total++; if($count>0)$used++;
            $modules[]=['name'=>str(class_basename($class))->headline()->toString(),'count'=>$count,'used'=>$count>0];
        }
        $progress=$total ? (int)round(($used/$total)*100) : 0;
        return response()->json(['progress'=>$progress,'used_modules'=>$used,'total_modules'=>$total,'modules'=>$modules]);
    }

    public function backup(Request $request): StreamedResponse
    {
        $user=$request->user(); $data=['generated_at'=>now()->toIso8601String(),'user'=>['id'=>$user->id,'name'=>$user->name,'email'=>$user->email],'modules'=>[]];
        foreach(UserDataVaultService::MODELS as $class){
            try{$data['modules'][class_basename($class)]=$class::where('user_id',$user->id)->get()->toArray();}catch(\Throwable $e){}
        }
        $name='my-digital-diary-backup-'.now()->format('Y-m-d-His').'.json';
        return response()->streamDownload(function()use($data){echo json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);},$name,['Content-Type'=>'application/json']);
    }

    public function trashJson(Request $request)
    {
        $items=UserRecycleBinItem::where('user_id',$request->user()->id)
            ->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()))
            ->latest('deleted_at')->limit(100)->get();
        return response()->json(['items'=>$items->map(fn($i)=>[
            'id'=>$i->id,'label'=>$i->label,'type'=>class_basename($i->model_type),
            'deleted_at'=>optional($i->deleted_at)->toIso8601String(),'expires_at'=>optional($i->expires_at)->toIso8601String(),
        ])->values()]);
    }

    public function restoreJson(Request $request, UserRecycleBinItem $item, UserDataVaultService $vault)
    {
        abort_unless($item->user_id===$request->user()->id,403);
        $restored=$vault->restore($item);
        return response()->json(['message'=>'Item restored.','id'=>$restored->getKey()]);
    }

    public function destroyJson(Request $request, UserRecycleBinItem $item)
    {
        abort_unless($item->user_id===$request->user()->id,403);
        $item->delete(); return response()->json(['message'=>'Item permanently deleted.']);
    }

    public function restore(Request $request, UserRecycleBinItem $item, UserDataVaultService $vault)
    {
        abort_unless($item->user_id===$request->user()->id,403);
        $vault->restore($item);
        return back()->with('status','Item restored successfully.');
    }

    public function destroy(Request $request, UserRecycleBinItem $item)
    {
        abort_unless($item->user_id===$request->user()->id,403);
        $item->delete(); return back()->with('status','Item permanently deleted.');
    }
}
