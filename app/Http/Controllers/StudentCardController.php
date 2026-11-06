<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentCard;
use App\Services\AuditLogger;
use App\Services\PermissionService;
use App\Support\BranchContext;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentCardController extends Controller
{
    public function issue(Request $request, OrganizationContext $organizations, BranchContext $branches, PermissionService $permissions, AuditLogger $audit): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'edit_students'), 403);
        $data = $request->validate(['public_ids' => ['required', 'array', 'min:1', 'max:100'], 'public_ids.*' => ['required', 'string', 'distinct']]);
        $branch = $branches->get();
        $organizationId = $organizations->id();
        $students = Student::where('organization_id', $organizationId)->where('branch_id', $branch->id)->where('status', 'active')->whereIn('public_id', $data['public_ids'])->orderBy('id')->get();
        abort_if($students->count() !== count($data['public_ids']), 404, 'One or more active students were not found in this centre.');

        $cards = DB::transaction(function () use ($students, $branch, $organizationId, $request, $audit): array {
            $issued = [];
            foreach ($students as $student) {
                Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
                $previousCards = StudentCard::where('organization_id', $organizationId)->where('branch_id', $branch->id)->where('student_id', $student->id)->where('status', 'active')->lockForUpdate()->get();
                foreach ($previousCards as $previousCard) {
                    $previousCard->update(['status' => 'revoked', 'revoked_at' => now()]);
                    $audit->log($request, 'student.card_revoked', $previousCard, ['status' => 'active'], ['status' => 'revoked', 'reason' => 'reissued'], $branch->id);
                }
                $token = $this->newToken();
                $card = StudentCard::create(['public_id' => (string) Str::ulid(), 'organization_id' => $organizationId, 'branch_id' => $branch->id, 'student_id' => $student->id, 'token_hash' => hash('sha256', $token), 'status' => 'active', 'issued_by' => $request->user()->id, 'issued_at' => now()]);
                $audit->log($request, 'student.card_issued', $card, [], ['student_public_id' => $student->public_id, 'status' => 'active'], $branch->id);
                $issued[] = ['student' => ['public_id' => $student->public_id, 'student_code' => $student->student_code, 'name_ar' => $student->name_ar, 'name_en' => $student->name_en], 'token' => $token];
            }
            return $issued;
        });

        return response()->json(['cards' => $cards], 201);
    }

    public function revoke(Request $request, OrganizationContext $organizations, BranchContext $branches, PermissionService $permissions, AuditLogger $audit): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'edit_students'), 403);
        $data = $request->validate(['public_ids' => ['required', 'array', 'min:1', 'max:100'], 'public_ids.*' => ['required', 'string', 'distinct']]);
        $branch = $branches->get();
        $students = Student::where('organization_id', $organizations->id())->where('branch_id', $branch->id)->whereIn('public_id', $data['public_ids'])->get();
        abort_if($students->count() !== count($data['public_ids']), 404, 'One or more students were not found in this centre.');
        $revoked = 0;
        DB::transaction(function () use ($students, $branch, $organizations, $request, $audit, &$revoked): void {
            foreach ($students as $student) {
                $cards = StudentCard::where('organization_id', $organizations->id())->where('branch_id', $branch->id)->where('student_id', $student->id)->where('status', 'active')->lockForUpdate()->get();
                foreach ($cards as $card) {
                    $card->update(['status' => 'revoked', 'revoked_at' => now()]);
                    $audit->log($request, 'student.card_revoked', $card, ['status' => 'active'], ['status' => 'revoked'], $branch->id);
                    $revoked++;
                }
            }
        });

        return response()->json(['revoked' => $revoked]);
    }

    private function newToken(): string
    {
        $token = '';
        for ($index = 0; $index < 10; $index++) {
            $token .= str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
        }
        return $token;
    }
}
