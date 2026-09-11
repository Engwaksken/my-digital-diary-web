<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\OrganizationSharedItem;
use App\Models\OrganizationWorkspaceActivity;
use App\Models\OrganizationWorkspaceFile;
use App\Services\OrganizationWorkspaceService;
use Illuminate\Http\Request;
class WorkspaceController extends Controller {
 public function __construct(private readonly OrganizationWorkspaceService $workspace){}
 public function overview(Request $r){ $o=$this->workspace->organizationForUser($r->user()); if(!$o)return response()->json(['enabled'=>false]); $id=(int)$o->id;
  return response()->json(['enabled'=>true,'organization'=>['id'=>$id,'name'=>$o->name??$o->organization_name??'Workspace'],'role'=>$this->workspace->roleForUser($id,(int)$r->user()->id),'can_manage_members'=>$this->workspace->canManage($id,(int)$r->user()->id),'members'=>$this->workspace->members($id),'shared_items'=>OrganizationSharedItem::where('organization_id',$id)->whereNull('revoked_at')->latest()->limit(30)->get(),'files'=>OrganizationWorkspaceFile::with('uploader:id,name,email')->where('organization_id',$id)->latest()->limit(30)->get(),'activity'=>OrganizationWorkspaceActivity::with('actor:id,name')->where('organization_id',$id)->latest('created_at')->limit(30)->get()]);
 }
}