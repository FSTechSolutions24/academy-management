<?php

namespace App\Http\Controllers;

use App\Models\ParentAccessLink;
use App\Models\AttendanceRecord;
use App\Models\ExaminationMark;
use App\Services\AuditLogger;
use App\Services\PermissionService;
use App\Support\BranchContext;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ParentAccessLinkController extends Controller
{
    public function store(Request $request, OrganizationContext $organizations, BranchContext $branches, PermissionService $permissions, AuditLogger $audit): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'manage_parent_links'), 403);
        $branch = $branches->get();
        $data = $request->validate(['student_id' => ['required', 'string'], 'label' => ['nullable', 'string', 'max:100'], 'expires_in_days' => ['required', 'integer', 'min:1', 'max:365']]);
        $student = $branch->students()->where('public_id', $data['student_id'])->where('status', 'active')->firstOrFail();
        $token = Str::random(48);
        $link = ParentAccessLink::create(['organization_id' => $organizations->id(), 'branch_id' => $branch->id, 'student_id' => $student->id, 'created_by' => $request->user()->id, 'token_hash' => hash('sha256', $token), 'label' => $data['label'] ?? null, 'expires_at' => now()->addDays($data['expires_in_days'])]);
        $audit->log($request, 'parent_access_link.created', $link, [], ['student_id' => $student->public_id, 'expires_at' => $link->expires_at->toIso8601String()], $branch->id);

        return response()->json(['link' => ['id' => $link->id, 'url' => url('/parent/'.$token), 'expires_at' => $link->expires_at->toIso8601String()]], 201);
    }

    public function revoke(int $linkId, Request $request, BranchContext $branches, PermissionService $permissions, AuditLogger $audit): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'manage_parent_links'), 403);
        $link = ParentAccessLink::where('branch_id', $branches->get()->id)->whereKey($linkId)->firstOrFail();
        $link->update(['revoked_at' => now()]);
        $audit->log($request, 'parent_access_link.revoked', $link, [], ['revoked_at' => $link->revoked_at->toIso8601String()], $link->branch_id);

        return response()->json(['message' => 'Parent link revoked.']);
    }

    public function show(string $token): JsonResponse
    {
        $link = ParentAccessLink::with(['student.branch', 'student.enrollments.educationalLevel:id,name_ar,name_en', 'student.enrollments.academicYear:id,name'])->where('token_hash', hash('sha256', $token))->first();
        abort_unless($link && ! $link->revoked_at && $link->expires_at->isFuture() && $link->student->status === 'active', 404);
        $link->update(['last_used_at' => now()]);
        $enrollment = $link->student->enrollments->first();
        $student = $link->student;
        $attendanceRecords = AttendanceRecord::with('session.group:id,name_ar,name_en,code')
            ->where('student_id', $student->id)
            ->whereHas('session', fn ($query) => $query->where('branch_id', $student->branch_id)->where('status', 'closed'))
            ->latest('recorded_at')->limit(60)->get();
        $attendance = ['total' => $attendanceRecords->count(), 'present' => $attendanceRecords->where('status', 'present')->count(), 'late' => $attendanceRecords->where('status', 'late')->count(), 'excused' => $attendanceRecords->where('status', 'excused')->count(), 'absent' => $attendanceRecords->where('status', 'absent')->count()];
        $results = ExaminationMark::with('examination.subject:id,name_ar,name_en,code')
            ->where('student_id', $student->id)
            ->whereHas('examination', fn ($query) => $query->where('branch_id', $student->branch_id)->whereIn('status', ['published', 'locked']))
            ->latest()->limit(30)->get()->map(fn ($mark) => ['id' => $mark->id, 'title' => $mark->examination->title, 'subject' => $mark->examination->subject?->only(['name_ar', 'name_en', 'code']), 'exam_date' => $mark->examination->exam_date?->toDateString(), 'mark' => $mark->mark, 'total_marks' => $mark->examination->total_marks, 'percentage' => $mark->examination->total_marks > 0 ? round(((float) $mark->mark / $mark->examination->total_marks) * 100, 1) : 0, 'comment' => $mark->comment]);

        return response()->json(['student' => ['name_ar' => $student->name_ar, 'name_en' => $student->name_en, 'student_code' => $student->student_code, 'branch' => $student->branch?->only(['name_ar', 'name_en']), 'level' => $enrollment?->educationalLevel?->only(['name_ar', 'name_en']), 'academic_year' => $enrollment?->academicYear?->only(['name'])], 'attendance' => $attendance, 'attendance_recent' => $attendanceRecords->take(12)->map(fn ($record) => ['date' => $record->session?->session_date?->toDateString(), 'status' => $record->status, 'group' => $record->session?->group?->only(['name_ar', 'name_en', 'code'])])->values(), 'results' => $results->values(), 'expires_at' => $link->expires_at->toIso8601String()]);
    }
}
