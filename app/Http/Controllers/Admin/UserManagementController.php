<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
class UserManagementController extends Controller {
 public function index(Request $r){ $q=trim((string)$r->query('q','')); $status=trim((string)$r->query('status','')); $users=User::query()->with('subscriptionPlan')->when($q,fn($x)=>$x->where(fn($s)=>$s->where('name','like',"%$q%")->orWhere('email','like',"%$q%")))->when($status&&Schema::hasColumn('users','account_status'),fn($x)=>$x->where('account_status',$status))->latest('id')->paginate(25)->withQueryString(); return view('admin.user-management.index',compact('users','q','status')); }
 public function role(Request $r,User $user){ abort_if((int)$r->user()->id===(int)$user->id,422,'You cannot change your own role here.'); $d=$r->validate(['system_role'=>['required',Rule::in(['user','admin','super_admin'])]]); $role=$d['system_role']==='super_admin'?'admin':$d['system_role']; $changes=['role'=>$role]; if(Schema::hasColumn('users','system_role')){ $changes['system_role']=$d['system_role']; } $user->forceFill($changes)->save(); return back()->with('success','User role updated.');}
 public function suspend(Request $r,User $user){ abort_if((int)$r->user()->id===(int)$user->id,422,'You cannot suspend your own account.'); $d=$r->validate(['reason'=>['nullable','string','max:1000']]); $user->forceFill(['account_status'=>'suspended','suspended_at'=>now(),'suspended_reason'=>$d['reason']??null])->save(); return back()->with('success','User suspended.');}
 public function reactivate(Request $r,User $user){ $user->forceFill(['account_status'=>'active','suspended_at'=>null,'suspended_reason'=>null])->save(); return back()->with('success','User reactivated.');}
 public function destroy(Request $r,User $user){ abort_if((int)$r->user()->id===(int)$user->id,422,'You cannot delete your own account.'); $r->validate(['confirmation'=>['required','in:DELETE']]); $user->delete(); return back()->with('success','User deleted.');}
}
