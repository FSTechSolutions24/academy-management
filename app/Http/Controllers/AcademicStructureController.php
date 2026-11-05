<?php

namespace App\Http\Controllers;

use App\Support\BranchContext;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\AuditLogger;
use App\Services\PermissionService;

class AcademicStructureController extends Controller
{
    public function index(BranchContext $branches): JsonResponse
    {
        $branch = $branches->get();
        return response()->json([
            'branch'=>$branch->only(['public_id','name_ar','name_en']),
            'academic_years'=>$branch->academicYears()->orderByDesc('starts_on')->get(),
            'levels'=>$branch->educationalLevels()->orderBy('sort_order')->orderBy('name_en')->get(),
            'subjects'=>$branch->subjects()->orderBy('name_en')->get(),
        ]);
    }

    public function storeYear(Request $request, OrganizationContext $organizations, BranchContext $branches, AuditLogger $audit): JsonResponse
    {
        $this->ensureCanManage($request);
        $branch = $branches->get();
        $data = $request->validate([
            'name'=>['required','string','max:30',Rule::unique('academic_years')->where(fn ($query) => $query->where('organization_id',$organizations->id())->where('branch_id',$branch->id))],
            'starts_on'=>['required','date'], 'ends_on'=>['required','date','after:starts_on'],
            'status'=>['required',Rule::in(['upcoming','active','closed','archived'])], 'is_active'=>['required','boolean'],
        ]);
        $year = DB::transaction(function () use ($data, $organizations, $branch) {
            if ($data['is_active']) $branch->academicYears()->update(['is_active'=>false]);
            return $branch->academicYears()->create([...$data,'organization_id'=>$organizations->id()]);
        });
        $audit->log($request,'academic_year.created',$year,[],$year->only(['name','starts_on','ends_on','status','is_active']));
        return response()->json(['academic_year'=>$year], 201);
    }

    public function storeLevel(Request $request, OrganizationContext $organizations, BranchContext $branches, AuditLogger $audit): JsonResponse
    {
        $this->ensureCanManage($request);
        $branch = $branches->get();
        $data = $request->validate([
            'name_ar'=>['required','string','max:120',Rule::unique('educational_levels')->where(fn ($query) => $query->where('organization_id',$organizations->id())->where('branch_id',$branch->id))],
            'name_en'=>['nullable','string','max:120'], 'stage'=>['nullable','string','max:60'],
            'sort_order'=>['nullable','integer','min:0','max:1000'],
        ]);
        $level = $branch->educationalLevels()->create([...$data,'organization_id'=>$organizations->id(),'is_active'=>true]);
        $audit->log($request,'educational_level.created',$level,[],$level->only(['name_ar','name_en','stage','sort_order']));
        return response()->json(['level'=>$level], 201);
    }

    public function storeSubject(Request $request, OrganizationContext $organizations, BranchContext $branches, AuditLogger $audit): JsonResponse
    {
        $this->ensureCanManage($request);
        $request->merge(['code'=>strtoupper((string) $request->input('code'))]);
        $branch = $branches->get();
        $data = $request->validate([
            'name_ar'=>['required','string','max:120'], 'name_en'=>['nullable','string','max:120'],
            'code'=>['required','alpha_dash','max:24',Rule::unique('subjects')->where(fn ($query) => $query->where('organization_id',$organizations->id())->where('branch_id',$branch->id))],
            'color'=>['required','regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        $subject = $branch->subjects()->create([...$data,'code'=>strtoupper($data['code']),'organization_id'=>$organizations->id(),'is_active'=>true]);
        $audit->log($request,'subject.created',$subject,[],$subject->only(['name_ar','name_en','code','color']));
        return response()->json(['subject'=>$subject], 201);
    }

    private function ensureCanManage(Request $request): void
    {
        abort_unless(app(PermissionService::class)->has($request->attributes->get('membership'),'manage_academic_structure'),403);
    }
}
