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
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function export(Request $request, BranchContext $branches, PermissionService $permissions): StreamedResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'view_students'), 403);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'level_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])]]);
        $branch = $branches->get();
        $query = $branch->students()->with(['guardians', 'enrollments.educationalLevel', 'enrollments.academicYear'])->latest();
        if ($data['search'] ?? null) { $term = $data['search']; $query->where(fn ($builder) => $builder->where('student_code', 'like', "%{$term}%")->orWhere('name_ar', 'like', "%{$term}%")->orWhere('name_en', 'like', "%{$term}%")); }
        if ($data['status'] ?? null) $query->where('status', $data['status']);
        if ($data['level_id'] ?? null) $query->whereHas('enrollments', fn ($builder) => $builder->where('educational_level_id', $data['level_id'])->where('status', 'active'));
        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'wb'); fputcsv($output, ['student_code', 'name_ar', 'name_en', 'date_of_birth', 'gender', 'phone', 'email', 'guardian_name', 'guardian_phone', 'guardian_email', 'guardian_relationship', 'level', 'academic_year', 'status']);
            $query->chunkById(500, function ($students) use ($output): void { foreach ($students as $student) { $guardian = $student->guardians->sortByDesc(fn ($item) => $item->pivot->is_primary)->first(); $enrollment = $student->enrollments->first(); fputcsv($output, [$student->student_code, $this->safeCsv($student->name_ar), $this->safeCsv($student->name_en), $student->date_of_birth?->toDateString(), $student->gender, $this->safeCsv($student->phone), $this->safeCsv($student->email), $this->safeCsv($guardian?->name), $this->safeCsv($guardian?->phone), $this->safeCsv($guardian?->email), $guardian?->pivot?->relationship, $this->safeCsv($enrollment?->educationalLevel?->name_en ?? $enrollment?->educationalLevel?->name_ar), $this->safeCsv($enrollment?->academicYear?->name), $student->status]); } }); fclose($output);
        }, 'academyhub-students.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
    public function index(Request $request, BranchContext $branches, PermissionService $permissions): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'view_students'), 403);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'level_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])]]);
        $branch = $branches->get();
        $query = $branch->students()->with(['guardians:id,organization_id,name,phone,whatsapp_phone,email', 'enrollments' => fn ($query) => $query->latest('id')->with(['educationalLevel:id,name_ar,name_en', 'academicYear:id,name'])]);
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
            'guardians' => ['sometimes', 'array', 'min:1', 'max:5'],
            'guardians.*.name' => ['required_with:guardians', 'string', 'max:160'],
            'guardians.*.phone' => ['required_with:guardians', 'string', 'max:24'],
            'guardians.*.email' => ['nullable', 'email', 'max:190'],
            'guardians.*.relationship' => ['required_with:guardians', Rule::in(['father', 'mother', 'guardian', 'other'])],
            'guardians.*.is_primary' => ['sometimes', 'boolean'],
            'guardian_name' => ['required_without:guardians', 'string', 'max:160'], 'guardian_phone' => ['required_without:guardians', 'string', 'max:24'],
            'guardian_email' => ['nullable', 'email', 'max:190'], 'guardian_relationship' => ['required_without:guardians', Rule::in(['father', 'mother', 'guardian', 'other'])],
        ]);
        $guardianData = $this->guardianData($data);
        $student = DB::transaction(function () use ($data, $guardianData, $organizations, $branch) {
            DB::table('student_sequences')->insertOrIgnore(['branch_id' => $branch->id, 'next_number' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $sequence = DB::table('student_sequences')->where('branch_id', $branch->id)->lockForUpdate()->first();
            $code = 'STU-'.str_pad((string) $sequence->next_number, 6, '0', STR_PAD_LEFT);
            DB::table('student_sequences')->where('branch_id', $branch->id)->update(['next_number' => $sequence->next_number + 1, 'updated_at' => now()]);
            $student = Student::create([...collect($data)->only(['name_ar', 'name_en', 'date_of_birth', 'gender', 'phone', 'email', 'notes'])->all(), 'public_id' => (string) Str::ulid(), 'organization_id' => $organizations->id(), 'branch_id' => $branch->id, 'student_code' => $code, 'status' => 'active']);
            $this->syncGuardians($student, $guardianData, $organizations->id());
            $student->enrollments()->create(['organization_id' => $organizations->id(), 'branch_id' => $branch->id, 'academic_year_id' => $data['academic_year_id'] ?? null, 'educational_level_id' => $data['educational_level_id'] ?? null, 'status' => 'active', 'enrolled_on' => today()]);

            return $student;
        });
        $audit->log($request, 'student.created', $student, [], ['student_code' => $student->student_code, 'name_ar' => $student->name_ar, 'guardians' => $this->guardianAuditState($student)], $branch->id);

        return response()->json(['student' => $this->serialize($student->load(['guardians:id,organization_id,name,phone,whatsapp_phone,email', 'enrollments.educationalLevel:id,name_ar,name_en', 'enrollments.academicYear:id,name']))], 201);
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
            'guardians' => ['sometimes', 'array', 'min:1', 'max:5'],
            'guardians.*.name' => ['required_with:guardians', 'string', 'max:160'],
            'guardians.*.phone' => ['required_with:guardians', 'string', 'max:24'],
            'guardians.*.email' => ['nullable', 'email', 'max:190'],
            'guardians.*.relationship' => ['required_with:guardians', Rule::in(['father', 'mother', 'guardian', 'other'])],
            'guardians.*.is_primary' => ['sometimes', 'boolean'],
            'guardian_name' => ['required_without:guardians', 'string', 'max:160'], 'guardian_phone' => ['required_without:guardians', 'string', 'max:24'],
            'guardian_email' => ['nullable', 'email', 'max:190'], 'guardian_relationship' => ['required_without:guardians', Rule::in(['father', 'mother', 'guardian', 'other'])],
        ]);
        $guardianData = $this->guardianData($data);
        $beforeGuardians = $this->guardianAuditState($student);
        DB::transaction(function () use ($data, $guardianData, $student, $organizations, $branch): void {
            $student->update(collect($data)->only(['name_ar', 'name_en', 'date_of_birth', 'gender', 'phone', 'email', 'notes'])->all());
            $this->syncGuardians($student, $guardianData, $organizations->id());
            $student->enrollments()->updateOrCreate(['branch_id' => $branch->id, 'status' => 'active'], ['organization_id' => $organizations->id(), 'academic_year_id' => $data['academic_year_id'] ?? null, 'educational_level_id' => $data['educational_level_id'] ?? null, 'enrolled_on' => today()]);
        });
        $audit->log($request, 'student.updated', $student, ['guardians' => $beforeGuardians], ['student_code' => $student->student_code, 'guardians' => $this->guardianAuditState($student)], $branch->id);

        return response()->json(['student' => $this->serialize($student->fresh()->load(['guardians:id,organization_id,name,phone,whatsapp_phone,email', 'enrollments.educationalLevel:id,name_ar,name_en', 'enrollments.academicYear:id,name']))]);
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

    public function bulkArchive(Request $request, BranchContext $branches, PermissionService $permissions, AuditLogger $audit): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'archive_students'), 403);
        $data = $request->validate([
            'public_ids' => ['required', 'array', 'min:1', 'max:100'],
            'public_ids.*' => ['required', 'string', 'max:26', 'distinct'],
        ]);
        $branch = $branches->get();
        $students = $branch->students()->where('organization_id', $branch->organization_id)->whereIn('public_id', $data['public_ids'])->get();
        abort_unless($students->count() === count($data['public_ids']), 404);

        $archived = 0;
        DB::transaction(function () use ($students, $branch, $request, $audit, &$archived): void {
            foreach ($students as $student) {
                if ($student->status === 'archived') {
                    continue;
                }

                $previousStatus = $student->status;
                $student->update(['status' => 'archived']);
                $student->enrollments()->where('status', 'active')->update(['status' => 'completed']);
                $audit->log($request, 'student.archived', $student, ['status' => $previousStatus], ['status' => 'archived', 'bulk' => true], $branch->id);
                $archived++;
            }
        });

        return response()->json(['archived' => $archived]);
    }

    private function serialize(Student $student): array
    {
        $enrollment = $student->enrollments->first();
        $guardians = $student->guardians->sortByDesc(fn (Guardian $guardian) => $guardian->pivot->is_primary)->values();
        $guardian = $guardians->first();

        return [...$student->only(['public_id', 'student_code', 'name_ar', 'name_en', 'date_of_birth', 'gender', 'phone', 'email', 'status']),
            'guardian' => $guardian?->only(['name', 'phone']),
            'guardians' => $guardians->map(fn (Guardian $guardian) => [...$guardian->only(['name', 'phone', 'whatsapp_phone', 'email']), 'relationship' => $guardian->pivot->relationship, 'is_primary' => (bool) $guardian->pivot->is_primary])->all(),
            'level' => $enrollment?->educationalLevel?->only(['id', 'name_ar', 'name_en']),
            'academic_year' => $enrollment?->academicYear?->only(['id', 'name']), 'enrolled_on' => $enrollment?->enrolled_on?->toDateString()];
    }

    private function guardianData(array $data): array
    {
        if (isset($data['guardians'])) {
            $guardians = $data['guardians'];
            abort_if(collect($guardians)->where('is_primary', true)->count() > 1, 422, 'Only one primary guardian is allowed.');
            if (! collect($guardians)->contains(fn (array $guardian) => (bool) ($guardian['is_primary'] ?? false))) {
                $guardians[0]['is_primary'] = true;
            }

            return $guardians;
        }

        return [[
            'name' => $data['guardian_name'], 'phone' => $data['guardian_phone'], 'email' => $data['guardian_email'] ?? null,
            'relationship' => $data['guardian_relationship'], 'is_primary' => true,
        ]];
    }

    private function syncGuardians(Student $student, array $guardians, int $organizationId): void
    {
        $links = [];
        foreach ($guardians as $guardianData) {
            $guardian = Guardian::firstOrCreate(
                ['organization_id' => $organizationId, 'phone' => $guardianData['phone']],
                ['name' => $guardianData['name'], 'email' => $guardianData['email'] ?? null, 'whatsapp_phone' => $guardianData['phone']],
            );
            $guardian->update(['name' => $guardianData['name'], 'email' => $guardianData['email'] ?? null, 'whatsapp_phone' => $guardianData['phone']]);
            $links[$guardian->id] = ['relationship' => $guardianData['relationship'], 'is_primary' => (bool) ($guardianData['is_primary'] ?? false)];
        }

        $student->guardians()->sync($links);
    }

    private function guardianAuditState(Student $student): array
    {
        return $student->guardians()->get()->mapWithKeys(fn (Guardian $guardian) => [(string) $guardian->id => [
            'relationship' => $guardian->pivot->relationship,
            'is_primary' => (bool) $guardian->pivot->is_primary,
        ]])->all();
    }

    private function safeCsv(?string $value): string { $value = (string) $value; return preg_match('/^[\s\p{Zs}]*[=+\-@]/u', $value) ? "'".$value : $value; }
}
