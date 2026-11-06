<?php
namespace App\Http\Controllers;
use App\Models\Examination;
use App\Services\PermissionService;
use App\Services\AuditLogger;
use App\Support\BranchContext;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class ExaminationController extends Controller {
 public function index(Request $request, BranchContext $branches, PermissionService $permissions): JsonResponse { abort_unless($permissions->has($request->attributes->get('membership'),'view_reports'),403); $exams=Examination::where('branch_id',$branches->get()->id)->with('subject:id,name_ar,name_en,code')->latest('exam_date')->latest()->get(); return response()->json(['exams'=>$exams]); }
 public function store(Request $request, OrganizationContext $organizations, BranchContext $branches, PermissionService $permissions, AuditLogger $audit): JsonResponse { abort_unless($permissions->has($request->attributes->get('membership'),'create_examinations'),403); $branch=$branches->get(); $data=$request->validate(['title'=>['required','string','max:160'],'subject_id'=>['nullable',Rule::exists('subjects','id')->where('branch_id',$branch->id)],'exam_date'=>['required','date'],'total_marks'=>['required','integer','min:1','max:10000'],'notes'=>['nullable','string','max:2000']]); $exam=Examination::create([...$data,'organization_id'=>$organizations->id(),'branch_id'=>$branch->id,'public_id'=>(string)Str::ulid(),'created_by'=>$request->user()->id,'status'=>'draft']); $audit->log($request,'examination.created',$exam,[],['title'=>$exam->title,'exam_date'=>$exam->exam_date->toDateString()],$branch->id); return response()->json(['exam'=>$exam->load('subject:id,name_ar,name_en,code')],201); }
}
