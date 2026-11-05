<?php

namespace App\Http\Controllers;

use App\Models\GroupSchedule;
use App\Models\TeachingGroup;
use App\Services\AuditLogger;
use App\Services\PermissionService;
use App\Support\BranchContext;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeachingGroupController extends Controller
{
    public function index(Request $request, BranchContext $branches, PermissionService $permissions): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'view_students'), 403);
        $branch = $branches->get();
        $groups = $branch->teachingGroups()->with(['academicYear:id,name', 'educationalLevel:id,name_ar,name_en', 'subject:id,name_ar,name_en'])->withCount(['students as active_students_count' => fn ($query) => $query->where('group_enrollments.status', 'active')])->where('status', 'active')->orderBy('name_en')->orderBy('name_ar')->get();

        return response()->json(['groups' => $groups->map(fn (TeachingGroup $group) => $this->serialize($group))]);
    }

    public function store(Request $request, OrganizationContext $organizations, BranchContext $branches, PermissionService $permissions, AuditLogger $audit): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'manage_academic_structure'), 403);
        $branch = $branches->get();
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:160'], 'name_en' => ['nullable', 'string', 'max:160'], 'code' => ['required', 'string', 'max:40', Rule::unique('teaching_groups', 'code')->where('branch_id', $branch->id)],
            'academic_year_id' => ['nullable', Rule::exists('academic_years', 'id')->where('branch_id', $branch->id)], 'educational_level_id' => ['nullable', Rule::exists('educational_levels', 'id')->where('branch_id', $branch->id)], 'subject_id' => ['nullable', Rule::exists('subjects', 'id')->where('branch_id', $branch->id)],
            'max_capacity' => ['nullable', 'integer', 'min:1', 'max:10000'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $group = $branch->teachingGroups()->create([...$data, 'organization_id' => $organizations->id(), 'public_id' => (string) Str::ulid(), 'status' => 'active']);
        $audit->log($request, 'teaching_group.created', $group, [], ['code' => $group->code, 'name_ar' => $group->name_ar], $branch->id);

        return response()->json(['group' => $this->serialize($group->load(['academicYear:id,name', 'educationalLevel:id,name_ar,name_en', 'subject:id,name_ar,name_en']))], 201);
    }

    public function schedules(Request $request, BranchContext $branches, PermissionService $permissions): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'view_students'), 403);
        $schedules = GroupSchedule::where('branch_id', $branches->get()->id)->where('is_active', true)->with('group:id,public_id,name_ar,name_en,code')->orderBy('day_of_week')->orderBy('starts_at')->get();

        return response()->json(['schedules' => $schedules]);
    }

    public function storeSchedule(Request $request, OrganizationContext $organizations, BranchContext $branches, PermissionService $permissions, AuditLogger $audit): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'manage_academic_structure'), 403);
        $branch = $branches->get();
        $data = $request->validate(['teaching_group_id' => ['required', Rule::exists('teaching_groups', 'id')->where('branch_id', $branch->id)], 'day_of_week' => ['required', 'integer', 'between:0,6'], 'starts_at' => ['required', 'date_format:H:i'], 'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'], 'room' => ['nullable', 'string', 'max:100'], 'mode' => ['required', Rule::in(['onsite', 'online'])]]);
        $conflict = GroupSchedule::where('branch_id', $branch->id)->where('day_of_week', $data['day_of_week'])->where('is_active', true)->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])->exists();
        if ($conflict) {
            throw ValidationException::withMessages(['starts_at' => 'This time overlaps another active group schedule in the centre.']);
        }
        $schedule = GroupSchedule::create([...$data, 'organization_id' => $organizations->id(), 'branch_id' => $branch->id]);
        $audit->log($request, 'teaching_group.schedule_created', $schedule, [], ['day_of_week' => $schedule->day_of_week, 'starts_at' => $schedule->starts_at], $branch->id);

        return response()->json(['schedule' => $schedule->load('group:id,public_id,name_ar,name_en,code')], 201);
    }

    private function serialize(TeachingGroup $group): array
    {
        return [...$group->only(['public_id', 'name_ar', 'name_en', 'code', 'max_capacity', 'status']), 'active_students' => $group->active_students_count ?? $group->students()->wherePivot('status', 'active')->count(), 'academic_year' => $group->academicYear?->only(['id', 'name']), 'level' => $group->educationalLevel?->only(['id', 'name_ar', 'name_en']), 'subject' => $group->subject?->only(['id', 'name_ar', 'name_en'])];
    }
}
