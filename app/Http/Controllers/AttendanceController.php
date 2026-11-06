<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\AttendanceCorrection;
use App\Models\Student;
use App\Models\StudentCard;
use App\Services\AuditLogger;
use App\Services\PermissionService;
use App\Support\BranchContext;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function sessions(Request $request, BranchContext $branches, PermissionService $permissions): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'view_attendance'), 403);
        $branch = $branches->get();
        $sessions = AttendanceSession::where('branch_id', $branch->id)->with(['group:id,name_ar,name_en,code'])->withCount('records')->latest('session_date')->latest('id')->limit(30)->get();

        return response()->json(['sessions' => $sessions->map(fn (AttendanceSession $session) => [...$session->only(['id', 'session_date', 'starts_at', 'ends_at', 'status']), 'group' => $session->group, 'records_count' => $session->records_count])]);
    }

    public function store(Request $request, OrganizationContext $organizations, BranchContext $branches, PermissionService $permissions, AuditLogger $audit): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'record_attendance'), 403);
        $branch = $branches->get();
        $data = $request->validate(['teaching_group_id' => ['required', Rule::exists('teaching_groups', 'id')->where('branch_id', $branch->id)], 'session_date' => ['required', 'date'], 'starts_at' => ['nullable', 'date_format:H:i'], 'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at']]);
        $session = AttendanceSession::create([...$data, 'organization_id' => $organizations->id(), 'branch_id' => $branch->id, 'created_by' => $request->user()->id, 'status' => 'open']);
        $audit->log($request, 'attendance.session_created', $session, [], ['session_date' => $session->session_date->toDateString()], $branch->id);

        return response()->json(['session' => $session->load('group:id,name_ar,name_en,code')], 201);
    }

    public function scan(int $sessionId, Request $request, BranchContext $branches, PermissionService $permissions): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'record_attendance'), 403);
        $branch = $branches->get();
        $session = AttendanceSession::where('branch_id', $branch->id)->whereKey($sessionId)->firstOrFail();
        abort_if($session->status !== 'open', 422, 'This attendance session is closed.');
        $data = $request->validate(['student_code' => ['required', 'string', 'max:40'], 'status' => ['nullable', Rule::in(['present', 'late', 'excused'])]]);
        $student = $session->group->students()->where('students.organization_id', $session->organization_id)->where('students.branch_id', $branch->id)->where('students.student_code', $data['student_code'])->wherePivot('status', 'active')->first();
        if (!$student) {
            $tokenHash = hash('sha256', $data['student_code']);
            $card = StudentCard::where('organization_id', $session->organization_id)->where('branch_id', $branch->id)->where('token_hash', $tokenHash)->where('status', 'active')->first();
            if ($card && hash_equals($card->token_hash, $tokenHash)) {
                $student = $session->group->students()->where('students.organization_id', $session->organization_id)->where('students.branch_id', $branch->id)->where('students.id', $card->student_id)->wherePivot('status', 'active')->first();
            }
        }
        abort_unless($student, 404, 'Student is not enrolled in this group.');
        $duplicate = false;
        $record = DB::transaction(function () use ($session, $student, $data, $request, $branch, &$duplicate) {
            $nextStatus = $data['status'] ?? 'present';
            $record = $session->records()->where('student_id', $student->id)->lockForUpdate()->first();
            $previousStatus = $record?->status;
            $duplicate = $record !== null;
            $record = $session->records()->updateOrCreate(['student_id' => $student->id], ['recorded_by' => $request->user()->id, 'status' => $nextStatus, 'recorded_at' => now(), 'method' => 'scanner']);
            if ($previousStatus && $previousStatus !== $nextStatus) {
                $this->logCorrection($session, $record, $previousStatus, $nextStatus, 'scanner', $request->user()->id, $branch->id);
            }
            return $record;
        });

        return response()->json(['duplicate' => $duplicate, 'record' => $record->load('student:id,student_code,name_ar,name_en')]);
    }

    public function report(int $sessionId, Request $request, BranchContext $branches, PermissionService $permissions): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'view_attendance'), 403);
        $session = AttendanceSession::where('branch_id', $branches->get()->id)->whereKey($sessionId)->with(['group:id,name_ar,name_en,code', 'records.student:id,student_code,name_ar,name_en'])->firstOrFail();

        return response()->json(['session' => $session, 'summary' => $session->records()->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status')]);
    }

    public function corrections(int $sessionId, Request $request, BranchContext $branches, PermissionService $permissions): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'view_attendance'), 403);
        $branch = $branches->get();
        $session = AttendanceSession::where('branch_id', $branch->id)->whereKey($sessionId)->firstOrFail();
        $corrections = AttendanceCorrection::where('branch_id', $branch->id)->where('attendance_session_id', $session->id)->with(['student:id,student_code,name_ar,name_en', 'changedBy:id,name'])->latest('id')->get();

        return response()->json(['corrections' => $corrections]);
    }

    public function bulkMark(int $sessionId, Request $request, BranchContext $branches, PermissionService $permissions): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'record_attendance'), 403);
        $session = AttendanceSession::where('branch_id', $branches->get()->id)->whereKey($sessionId)->firstOrFail();
        abort_if($session->status !== 'open', 422, 'This attendance session is closed.');
        $data = $request->validate(['records' => ['required', 'array', 'min:1', 'max:1000'], 'records.*.student_id' => ['required', 'integer'], 'records.*.status' => ['required', Rule::in(['present', 'late', 'excused', 'absent'])]]);
        $enrolled = $session->group->students()->wherePivot('status', 'active')->pluck('students.id')->all();
        $invalid = collect($data['records'])->pluck('student_id')->diff($enrolled);
        abort_if($invalid->isNotEmpty(), 422, 'One or more students are not enrolled in this group.');
        $branch = $branches->get();
        DB::transaction(function () use ($data, $session, $request, $branch): void {
            foreach ($data['records'] as $item) {
                $record = $session->records()->where('student_id', $item['student_id'])->lockForUpdate()->first();
                $previousStatus = $record?->status;
                $record = $session->records()->updateOrCreate(['student_id' => $item['student_id']], ['recorded_by' => $request->user()->id, 'status' => $item['status'], 'recorded_at' => now(), 'method' => 'manual']);
                if ($previousStatus && $previousStatus !== $item['status']) {
                    $this->logCorrection($session, $record, $previousStatus, $item['status'], 'manual', $request->user()->id, $branch->id);
                }
            }
        });

        return response()->json(['message' => 'Attendance saved.', 'updated' => count($data['records'])]);
    }

    private function logCorrection(AttendanceSession $session, $record, string $from, string $to, string $method, int $userId, int $branchId): void
    {
        AttendanceCorrection::create([
            'organization_id' => $session->organization_id,
            'branch_id' => $branchId,
            'attendance_session_id' => $session->id,
            'attendance_record_id' => $record->id,
            'student_id' => $record->student_id,
            'changed_by' => $userId,
            'from_status' => $from,
            'to_status' => $to,
            'method' => $method,
        ]);
    }

    public function close(int $sessionId, Request $request, BranchContext $branches, PermissionService $permissions, AuditLogger $audit): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'record_attendance'), 403);
        $branch = $branches->get();
        $session = AttendanceSession::where('branch_id', $branch->id)->whereKey($sessionId)->firstOrFail();
        if ($session->status === 'closed') {
            return response()->json(['message' => 'Attendance session already closed.', 'already_closed' => true]);
        }
        DB::transaction(function () use ($session, $request): void {
            $studentIds = $session->group->students()->wherePivot('status', 'active')->pluck('students.id');
            $existingIds = $session->records()->pluck('student_id');
            foreach ($studentIds->diff($existingIds) as $studentId) {
                $session->records()->create(['student_id' => $studentId, 'recorded_by' => $request->user()->id, 'status' => 'absent', 'method' => 'auto']);
            }
            $session->update(['status' => 'closed', 'closed_at' => now()]);
        });
        $audit->log($request, 'attendance.session_closed', $session, ['status' => 'open'], ['status' => 'closed'], $branch->id);

        return response()->json(['message' => 'Attendance session closed.']);
    }
}
