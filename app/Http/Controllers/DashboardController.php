<?php

namespace App\Http\Controllers;

use App\Support\OrganizationContext;
use App\Support\BranchContext;
use App\Support\BranchAccess;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(Request $request, OrganizationContext $context, BranchContext $branchContext, BranchAccess $access): JsonResponse
    {
        $organization = $context->get();
        $branch = $branchContext->get();
        return response()->json([
            'organization'=>$organization->only(['public_id','name_ar','name_en','preferred_locale','currency','subscription_status','trial_ends_at']),
            'branch'=>$branch->only(['public_id','name_ar','name_en','governorate','city','is_head_office']),
            'branches'=>$access->query($request->user(), $organization)->orderByDesc('is_head_office')->orderBy('name_en')->get(['public_id','name_ar','name_en','governorate','city','is_head_office']),
            'stats'=>[
                ['label'=>'Active students','value'=>0,'change'=>'Ready for student import','tone'=>'violet'],
                ['label'=>'Today attendance','value'=>0,'change'=>'No sessions scheduled','tone'=>'teal'],
                ['label'=>'Published results','value'=>0,'change'=>'Set up your first exam','tone'=>'amber'],
                ['label'=>'Collected this month','value'=>'EGP 0','change'=>'Finance setup pending','tone'=>'blue'],
            ],
            'setup'=>[
                ['label'=>'Academy workspace','done'=>true],
                ['label'=>'Academic year','done'=>$branch->academicYears()->exists()],
                ['label'=>'Educational levels','done'=>$branch->educationalLevels()->exists()],
                ['label'=>'Subjects','done'=>$branch->subjects()->exists()],
                ['label'=>'Teaching groups','done'=>false],
                ['label'=>'Import students','done'=>false],
            ],
            'academic_year'=>$branch->academicYears()->where('is_active',true)->first()?->only(['name','starts_on','ends_on']),
            'subjects'=>$branch->subjects()->orderBy('name_en')->get(['id','name_ar','name_en','code','color']),
        ]);
    }
}
