<?php

namespace App\Http\Controllers;

use App\Models\Guardian;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\PermissionService;
use App\Support\BranchContext;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(Request $request, BranchContext $branches, PermissionService $permissions): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'view_students'), 403);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'level_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])]]);
        $branch = $branches->get();
        $query = $branch->students()->with(['guardians:id,name,phone', 'enrollments' => fn ($query) => $query->latest('id')->with(['educationalLevel:id,name_ar,name_en', 'academicYear:id,name'])]);
        if ($data['search'] ?? null) {
            $term = $data['search'];
            $query->where(fn ($query) => $query->where('student_code', 'like', "%{$term}%")->orWhere('name_ar', 'like', "%{$term}%")->orWhere('name_en', 'like', "%{$term}%"));
        }
        if ($data['status'] ?? null) {
            $query->where('status', $data['status']);
        }
        if ($data['level_id'] ?? null) {
            $query->whereHas('enrollments', fn ($query) => $query->where('educational_level_id', $data['level_id'])->where('status', 'active'));
        }
        $students = $query->latest()->paginate(20);

        return response()->json([
            'students' => collect($students->items())->map(fn (Student $student) => $this->serialize($student)),
            'pagination' => ['page' => $students->currentPage(), 'last_page' => $students->lastPage(), 'total' => $students->total()],
            'levels' => $branch->educationalLevels()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name_ar', 'name_en']),
            'academic_years' => $branch->academicYears()->orderByDesc('is_active')->orderByDesc('starts_on')->get(['id', 'name', 'is_active']),
        ]);
    }

    public function store(Request $request, OrganizationContext $organizations, BranchContext $branches, PermissionService $permissions, AuditLogger $audit): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'create_students'), 403);
        $branch = $branches->get();
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:160'], 'name_en' => ['nullable', 'string', 'max:160'],
            'date_of_birth' => ['nullable', 'date', 'before:today'], 'gender' => ['nullable', Rule::in(['male', 'female'])],
            'phone' => ['nullable', 'string', 'max:24'], 'email' => ['nullable', 'email', 'max:190'], 'notes' => ['nullable', 'string', 'max:2000'],
            'academic_year_id' => ['nullable', Rule::exists('academic_years', 'id')->where('branch_id', $branch->id)],
            'educational_level_id' => ['nullable', Rule::exists('educational_levels', 'id')->where('branch_id', $branch->id)],
            'guardian_name' => ['required', 'string', 'max:160'], 'guardian_phone' => ['required', 'string', 'max:24'],
            'guardian_email' => ['nullable', 'email', 'max:190'], 'guardian_relationship' => ['required', Rule::in(['father', 'mother', 'guardian', 'other'])],
        ]);
        $student = DB::transaction(function () use ($data, $organizations, $branch) {
            DB::table('student_sequences')->insertOrIgnore(['branch_id' => $branch->id, 'next_number' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $sequence = DB::table('student_sequences')->where('branch_id', $branch->id)->lockForUpdate()->first();
            $code = 'STU-'.str_pad((string) $sequence->next_number, 6, '0', STR_PAD_LEFT);
            DB::table('student_sequences')->where('branch_id', $branch->id)->update(['next_number' => $sequence->next_number + 1, 'updated_at' => now()]);
            $student = Student::create([...collect($data)->only(['name_ar', 'name_en', 'date_of_birth', 'gender', 'phone', 'email', 'notes'])->all(), 'public_id' => (string) Str::ulid(), 'organization_id' => $organizations->id(), 'branch_id' => $branch->id, 'student_code' => $code, 'status' => 'active']);
            $guardian = Guardian::firstOrCreate(['organization_id' => $organizations->id(), 'phone' => $data['guardian_phone']], ['name' => $data['guardian_name'], 'email' => $data['guardian_email'] ?? null, 'whatsapp_phone' => $data['guardian_phone']]);
            $student->guardians()->attach($guardian->id, ['relationship' => $data['guardian_relationship'], 'is_primary' => true]);
            $student->enrollments()->create(['organization_id' => $organizations->id(), 'branch_id' => $branch->id, 'academic_year_id' => $data['academic_year_id'] ?? null, 'educational_level_id' => $data['educational_level_id'] ?? null, 'status' => 'active', 'enrolled_on' => today()]);

            return $student;
        });
        $audit->log($request, 'student.created', $student, [], ['student_code' => $student->student_code, 'name_ar' => $student->name_ar], $branch->id);

        return response()->json(['student' => $this->serialize($student->load(['guardians:id,name,phone', 'enrollments.educationalLevel:id,name_ar,name_en', 'enrollments.academicYear:id,name']))], 201);
    }

    public function update(string $publicId, Request $request, OrganizationContext $organizations, BranchContext $branches, PermissionService $permissions, AuditLogger $audit): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'edit_students'), 403);
        $branch = $branches->get();
        $student = $branch->students()->where('public_id', $publicId)->firstOrFail();
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:160'], 'name_en' => ['nullable', 'string', 'max:160'],
            'date_of_birth' => ['nullable', 'date', 'before:today'], 'gender' => ['nullable', Rule::in(['male', 'female'])],
            'phone' => ['nullable', 'string', 'max:24'], 'email' => ['nullable', 'email', 'max:190'], 'notes' => ['nullable', 'string', 'max:2000'],
            'academic_year_id' => ['nullable', Rule::exists('academic_years', 'id')->where('branch_id', $branch->id)],
            'educational_level_id' => ['nullable', Rule::exists('educational_levels', 'id')->where('branch_id', $branch->id)],
            'guardian_name' => ['required', 'string', 'max:160'], 'guardian_phone' => ['required', 'string', 'max:24'],
            'guardian_email' => ['nullable', 'email', 'max:190'], 'guardian_relationship' => ['required', Rule::in(['father', 'mother', 'guardian', 'other'])],
        ]);
        DB::transaction(function () use ($data, $student, $organizations, $branch): void {
            $student->update(collect($data)->only(['name_ar', 'name_en', 'date_of_birth', 'gender', 'phone', 'email', 'notes'])->all());
            $guardian = Guardian::firstOrCreate(['organization_id' => $organizations->id(), 'phone' => $data['guardian_phone']], ['name' => $data['guardian_name'], 'email' => $data['guardian_email'] ?? null, 'whatsapp_phone' => $data['guardian_phone']]);
            $guardian->update(['name' => $data['guardian_name'], 'email' => $data['guardian_email'] ?? null, 'whatsapp_phone' => $data['guardian_phone']]);
            $primaryGuardianId = $student->guardians()->wherePivot('is_primary', true)->pluck('guardians.id')->first();
            if ($primaryGuardianId) {
                $student->guardians()->updateExistingPivot($primaryGuardianId, ['is_primary' => false]);
            }
            $student->guardians()->syncWithoutDetaching([$guardian->id => ['relationship' => $data['guardian_relationship'], 'is_primary' => true]]);
            $student->enrollments()->updateOrCreate(['branch_id' => $branch->id, 'status' => 'active'], ['organization_id' => $organizations->id(), 'academic_year_id' => $data['academic_year_id'] ?? null, 'educational_level_id' => $data['educational_level_id'] ?? null, 'enrolled_on' => today()]);
        });
        $audit->log($request, 'student.updated', $student, [], ['student_code' => $student->student_code], $branch->id);

        return response()->json(['student' => $this->serialize($student->fresh()->load(['guardians:id,name,phone', 'enrollments.educationalLevel:id,name_ar,name_en', 'enrollments.academicYear:id,name']))]);
    }

    public function archive(string $publicId, Request $request, BranchContext $branches, PermissionService $permissions, AuditLogger $audit): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'archive_students'), 403);
        $branch = $branches->get();
        $student = $branch->students()->where('public_id', $publicId)->firstOrFail();
        $student->update(['status' => 'archived']);
        $student->enrollments()->where('status', 'active')->update(['status' => 'completed']);
        $audit->log($request, 'student.archived', $student, ['status' => 'active'], ['status' => 'archived'], $branch->id);

        return response()->json(['message' => 'Student archived.']);
    }

    private function serialize(Student $student): array
    {
        $enrollment = $student->enrollments->first();
        $guardian = $student->guardians->first();

        return [...$student->only(['public_id', 'student_code', 'name_ar', 'name_en', 'date_of_birth', 'gender', 'phone', 'email', 'status']),
            'guardian' => $guardian?->only(['name', 'phone']), 'level' => $enrollment?->educationalLevel?->only(['id', 'name_ar', 'name_en']),
            'academic_year' => $enrollment?->academicYear?->only(['id', 'name']), 'enrolled_on' => $enrollment?->enrolled_on?->toDateString()];
    }
}
