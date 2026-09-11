<?php
namespace App\Http\Controllers;
use App\Models\OrganizationSharedItem;
use App\Models\OrganizationWorkspaceActivity;
use App\Models\OrganizationWorkspaceFile;
use App\Services\OrganizationWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
class WorkspaceController extends Controller {
 public function __construct(private readonly OrganizationWorkspaceService $workspace){}
 private function org(Request $r): object { $o=$this->workspace->organizationForUser($r->user()); abort_unless($o,403,'A Family/Team or Enterprise workspace is required.'); $this->workspace->assertMember((int)$o->id,(int)$r->user()->id); return $o; }
 public function index(Request $r){
  $o=$this->org($r); $id=(int)$o->id; $role=$this->workspace->roleForUser($id,(int)$r->user()->id); $members=$this->workspace->members($id);
  $sharedItems=OrganizationSharedItem::with(['sharedBy:id,name,email','sharedWith:id,name,email'])->where('organization_id',$id)->whereNull('revoked_at')->where(fn($q)=>$q->where('is_team_wide',1)->orWhere('shared_with_user_id',$r->user()->id)->orWhere('shared_by_user_id',$r->user()->id))->latest()->limit(50)->get();
  $files=OrganizationWorkspaceFile::with('uploader:id,name,email')->where('organization_id',$id)->latest()->limit(50)->get();
  $activity=OrganizationWorkspaceActivity::with('actor:id,name')->where('organization_id',$id)->latest('created_at')->limit(30)->get();
  return view('workspace.index',compact('o','role','members','sharedItems','files','activity'));
 }
 public function upload(Request $r){ $o=$this->org($r); $d=$r->validate(['file'=>['required','file','max:51200','mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,zip'],'permission'=>['required',Rule::in(['view','edit','manage'])]]);
  $u=$d['file']; $path=$u->store('organization-workspaces/'.$o->id,'local'); $f=OrganizationWorkspaceFile::create(['organization_id'=>$o->id,'uploaded_by_user_id'=>$r->user()->id,'disk'=>'local','path'=>$path,'original_name'=>$u->getClientOriginalName(),'mime_type'=>$u->getMimeType(),'size_bytes'=>$u->getSize()?:0,'permission'=>$d['permission'],'is_team_wide'=>1]);
  $this->workspace->log((int)$o->id,(int)$r->user()->id,'workspace_file_uploaded',OrganizationWorkspaceFile::class,$f->id,['name'=>$f->original_name]); return back()->with('success','File uploaded to the workspace.');
 }
 public function download(Request $r,OrganizationWorkspaceFile $file){ $o=$this->org($r); abort_unless((int)$file->organization_id===(int)$o->id,403); abort_unless(Storage::disk($file->disk)->exists($file->path),404); return Storage::disk($file->disk)->download($file->path,$file->original_name); }
 public function destroyFile(Request $r,OrganizationWorkspaceFile $file){ $o=$this->org($r); abort_unless((int)$file->organization_id===(int)$o->id,403); abort_unless($this->workspace->canManage((int)$o->id,(int)$r->user()->id)||(int)$file->uploaded_by_user_id===(int)$r->user()->id,403); Storage::disk($file->disk)->delete($file->path); $file->delete(); return back()->with('success','Workspace file deleted.'); }
}