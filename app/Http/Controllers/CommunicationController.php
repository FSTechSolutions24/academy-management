<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsAppMessage;
use App\Models\CommunicationConsent;
use App\Models\CommunicationMessage;
use App\Models\CommunicationTemplate;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\PermissionService;
use App\Support\BranchContext;
use App\Support\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommunicationController extends Controller
{
    public function consents(Request $request, BranchContext $branches, PermissionService $permissions): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'send_messages'), 403);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'student_id' => ['nullable', 'string'], 'guardian_id' => ['nullable', 'integer']]);
        $query = CommunicationConsent::with(['student:id,public_id,student_code,name_ar,name_en', 'guardian:id,name,phone,whatsapp_phone'])
            ->where('branch_id', $branches->get()->id);
        if ($data['student_id'] ?? null) $query->whereHas('student', fn ($builder) => $builder->where('public_id', $data['student_id']));
        if ($data['guardian_id'] ?? null) $query->where('guardian_id', $data['guardian_id']);
        if ($data['search'] ?? null) {
            $term = $data['search'];
            $query->where(function ($builder) use ($term): void {
                $builder->whereHas('student', fn ($student) => $student->where('student_code', 'like', "%{$term}%")->orWhere('name_ar', 'like', "%{$term}%")->orWhere('name_en', 'like', "%{$term}%"))
                    ->orWhereHas('guardian', fn ($guardian) => $guardian->where('name', 'like', "%{$term}%"));
            });
        }
        $items = $query->latest('updated_at')->paginate(30);
        return response()->json(['consents' => $items->items(), 'pagination' => ['page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'total' => $items->total()]]);
    }

    public function recordConsent(Request $request, OrganizationContext $organizations, BranchContext $branches, PermissionService $permissions, AuditLogger $audit): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'send_messages'), 403);
        $branch = $branches->get();
        $data = $request->validate([
            'student_id' => ['required', 'string'], 'guardian_id' => ['required', 'integer'],
            'is_opted_in' => ['required', 'boolean'], 'method' => ['required', Rule::in(['written', 'verbal', 'online', 'withdrawn'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        abort_if(($data['is_opted_in'] && $data['method'] === 'withdrawn') || (! $data['is_opted_in'] && $data['method'] !== 'withdrawn'), 422, 'Use a consent capture method to opt in and withdrawn to opt out.');
        $student = $branch->students()->where('public_id', $data['student_id'])->where('status', 'active')->firstOrFail();
        abort_unless($student->guardians()->whereKey($data['guardian_id'])->exists(), 422, 'The guardian is not linked to this student.');
        $previous = CommunicationConsent::where('branch_id', $branch->id)->where('student_id', $student->id)->where('guardian_id', $data['guardian_id'])->first();
        $consent = DB::transaction(function () use ($data, $student, $branch, $organizations, $request): CommunicationConsent {
            return CommunicationConsent::updateOrCreate(
                ['organization_id' => $organizations->id(), 'branch_id' => $branch->id, 'student_id' => $student->id, 'guardian_id' => $data['guardian_id']],
                ['captured_by' => $request->user()->id, 'is_opted_in' => $data['is_opted_in'], 'method' => $data['method'], 'notice_version' => 'whatsapp-v1', 'notes' => $data['notes'] ?? null, 'captured_at' => now()],
            );
        });
        $audit->log($request, $consent->is_opted_in ? 'communication.consent_granted' : 'communication.consent_withdrawn', $consent, $previous ? ['is_opted_in' => $previous->is_opted_in, 'method' => $previous->method, 'captured_at' => $previous->captured_at?->toIso8601String()] : [], ['student_id' => $student->public_id, 'guardian_id' => $data['guardian_id'], 'is_opted_in' => $consent->is_opted_in, 'method' => $consent->method, 'captured_at' => $consent->captured_at?->toIso8601String()], $branch->id);
        return response()->json(['consent' => $consent->load(['student:id,student_code,name_ar,name_en', 'guardian:id,name,phone,whatsapp_phone'])], 201);
    }

    public function templates(Request $request, BranchContext $branches, PermissionService $permissions): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'send_messages'), 403);
        return response()->json(['templates' => CommunicationTemplate::where('branch_id', $branches->get()->id)->orderBy('name')->get()]);
    }

    public function storeTemplate(Request $request, OrganizationContext $organizations, BranchContext $branches, PermissionService $permissions): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'send_messages'), 403);
        $branch = $branches->get();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'provider_template_name' => ['required', 'alpha_dash', 'max:100', Rule::unique('communication_templates')->where('branch_id', $branch->id)],
            'language' => ['required', 'string', 'max:12'], 'category' => ['required', Rule::in(['utility'])],
            'variables' => ['sometimes', 'array', 'max:10'], 'variables.*' => ['required', 'string', 'max:60'], 'body_preview' => ['nullable', 'string', 'max:2000'],
        ]);
        $template = CommunicationTemplate::create([...$data, 'organization_id' => $organizations->id(), 'branch_id' => $branch->id, 'created_by' => $request->user()->id, 'variables' => $data['variables'] ?? [], 'is_active' => true]);
        return response()->json(['template' => $template], 201);
    }

    public function queue(Request $request, OrganizationContext $organizations, BranchContext $branches, PermissionService $permissions, AuditLogger $audit): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'send_messages'), 403);
        $branch = $branches->get();
        $data = $request->validate(['student_id' => ['required', 'string'], 'guardian_id' => ['required', 'integer'], 'template_id' => ['required', 'integer'], 'parameters' => ['sometimes', 'array', 'max:10'], 'parameters.*' => ['required', 'string', 'max:500']]);
        $student = $branch->students()->where('public_id', $data['student_id'])->where('status', 'active')->with('guardians')->firstOrFail();
        $guardian = $student->guardians->firstWhere('id', $data['guardian_id']);
        abort_unless($guardian, 422, 'The guardian is not linked to this student.');
        $consent = CommunicationConsent::where('organization_id', $organizations->id())->where('branch_id', $branch->id)->where('student_id', $student->id)->where('guardian_id', $guardian->id)->where('is_opted_in', true)->first();
        abort_unless($consent, 422, 'Current WhatsApp consent is required before sending a message.');
        $template = CommunicationTemplate::where('branch_id', $branch->id)->where('is_active', true)->findOrFail($data['template_id']);
        $phone = $guardian->whatsapp_phone ?: $guardian->phone;
        abort_unless($phone && preg_match('/^\+[1-9]\d{7,14}$/', $phone), 422, 'The guardian needs a WhatsApp number in international format, such as +201001234567.');
        $parameters = array_values($data['parameters'] ?? []);
        abort_unless(count($parameters) === count($template->variables ?? []), 422, 'Provide one parameter for each template variable.');
        $message = CommunicationMessage::create(['organization_id' => $organizations->id(), 'branch_id' => $branch->id, 'student_id' => $student->id, 'guardian_id' => $guardian->id, 'template_id' => $template->id, 'created_by' => $request->user()->id, 'to_phone' => $phone, 'template_name' => $template->provider_template_name, 'language' => $template->language, 'parameters' => $parameters, 'status' => 'queued', 'queued_at' => now()]);
        SendWhatsAppMessage::dispatch($message->id)->afterCommit();
        $audit->log($request, 'communication.message_queued', $message, [], ['student_id' => $student->public_id, 'guardian_id' => $guardian->id, 'template' => $template->provider_template_name], $branch->id);
        return response()->json(['message' => $message], 202);
    }

    public function messages(Request $request, BranchContext $branches, PermissionService $permissions): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'send_messages'), 403);
        $items = CommunicationMessage::with(['student:id,student_code,name_ar,name_en', 'guardian:id,name,phone,whatsapp_phone'])
            ->where('branch_id', $branches->get()->id)->latest()->paginate(30);
        return response()->json(['messages' => $items->items(), 'pagination' => ['page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'total' => $items->total()]]);
    }

    public function retry(int $messageId, Request $request, BranchContext $branches, PermissionService $permissions, AuditLogger $audit): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'send_messages'), 403);
        $branch = $branches->get();
        $message = CommunicationMessage::where('branch_id', $branch->id)->where('status', 'failed')->findOrFail($messageId);
        abort_unless(CommunicationConsent::where('branch_id', $branch->id)->where('student_id', $message->student_id)->where('guardian_id', $message->guardian_id)->where('is_opted_in', true)->exists(), 422, 'Current WhatsApp consent is required before retrying.');
        $message->update(['status' => 'queued', 'queued_at' => now(), 'last_error' => null]);
        SendWhatsAppMessage::dispatch($message->id)->afterCommit();
        $audit->log($request, 'communication.message_retried', $message, ['status' => 'failed'], ['status' => 'queued'], $branch->id);
        return response()->json(['message' => $message->fresh()], 202);
    }
}
