<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessStudentImport;
use App\Services\PermissionService;
use App\Services\StudentImportFileReader;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StudentImportController extends Controller
{
    public function store(Request $request, BranchContext $branches, PermissionService $permissions, StudentImportFileReader $reader): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'import_students'), 403);
        $request->validate(['file' => ['required', 'file', 'max:5120']]);
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        abort_unless(in_array($extension, ['csv', 'xlsx'], true), 422, 'Upload a CSV or XLSX file.');

        try {
            $rows = $reader->read($file);
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }
        abort_if($rows === [], 422, 'The file does not contain any student rows.');

        $branch = $branches->get();
        $organizationId = (int) $branch->organization_id;
        $publicId = (string) Str::ulid();
        $storagePath = $file->storeAs("student-imports/{$organizationId}/{$branch->id}", $publicId.'.'.$extension, 'local');
        if (! $storagePath) {
            abort(500, 'The uploaded file could not be stored privately.');
        }
        $validRows = 0;
        $invalidRows = 0;
        try {
            DB::transaction(function () use ($request, $rows, $branch, $organizationId, $publicId, $storagePath, &$validRows, &$invalidRows): void {
                $importId = DB::table('student_imports')->insertGetId([
                    'public_id' => $publicId,
                    'organization_id' => $organizationId,
                    'branch_id' => $branch->id,
                    'created_by' => $request->user()->id,
                    'source_name' => mb_substr($request->file('file')->getClientOriginalName(), 0, 255),
                    'storage_path' => $storagePath,
                    'status' => 'preview',
                    'total_rows' => count($rows),
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach (array_chunk($rows, 250) as $chunk) {
                    $records = [];
                    foreach ($chunk as [$rowNumber, $data]) {
                        $errors = $this->validateRow($data);
                        $status = $errors === [] ? 'ready' : 'invalid';
                        $validRows += $status === 'ready' ? 1 : 0;
                        $invalidRows += $status === 'invalid' ? 1 : 0;
                        $records[] = [
                            'student_import_id' => $importId,
                            'row_number' => $rowNumber,
                            'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                            'errors' => $errors === [] ? null : json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                            'status' => $status,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    DB::table('student_import_rows')->insert($records);
                }

                DB::table('student_imports')->where('id', $importId)->update(['valid_rows' => $validRows, 'invalid_rows' => $invalidRows]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storagePath);
            throw $exception;
        }

        return response()->json($this->serialize($this->scopedImport($request, $branches, $publicId)), 201);
    }

    public function show(string $publicId, Request $request, BranchContext $branches, PermissionService $permissions): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'import_students'), 403);
        $import = $this->scopedImport($request, $branches, $publicId);

        return response()->json($this->serialize($import));
    }

    public function commit(string $publicId, Request $request, BranchContext $branches, PermissionService $permissions): JsonResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'import_students'), 403);
        $import = $this->scopedImport($request, $branches, $publicId);
        abort_unless($import->status === 'preview', 409, 'This import has already been started.');
        abort_if((int) $import->valid_rows === 0, 422, 'There are no valid rows to import.');

        $started = DB::table('student_imports')->where('id', $import->id)->where('status', 'preview')->update(['status' => 'queued', 'updated_at' => now()]);
        abort_unless($started === 1, 409, 'This import has already been started.');
        ProcessStudentImport::dispatch((int) $import->id);

        return response()->json($this->serialize($this->scopedImport($request, $branches, $publicId)), 202);
    }

    public function errors(string $publicId, Request $request, BranchContext $branches, PermissionService $permissions): StreamedResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'import_students'), 403);
        $import = $this->scopedImport($request, $branches, $publicId);
        $rows = DB::table('student_import_rows')->where('student_import_id', $import->id)->whereIn('status', ['invalid', 'failed'])->orderBy('row_number')->get();

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['row_number', ...StudentImportFileReader::ALLOWED_HEADERS, 'errors']);
            foreach ($rows as $row) {
                $data = json_decode($row->data, true) ?: [];
                $errors = json_decode($row->errors ?? '[]', true) ?: [];
                $values = array_map(fn ($header) => $this->safeSpreadsheetCell((string) ($data[$header] ?? '')), StudentImportFileReader::ALLOWED_HEADERS);
                fputcsv($output, [$row->row_number, ...$values, $this->safeSpreadsheetCell(implode('; ', $errors))]);
            }
            fclose($output);
        }, 'student-import-errors-'.$publicId.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function template(Request $request, PermissionService $permissions): StreamedResponse
    {
        abort_unless($permissions->has($request->attributes->get('membership'), 'import_students'), 403);

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, StudentImportFileReader::ALLOWED_HEADERS);
            fclose($output);
        }, 'academyhub-student-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validateRow(array $data): array
    {
        $validator = Validator::make($data, [
            'name_ar' => ['required', 'string', 'max:160'],
            'name_en' => ['nullable', 'string', 'max:160'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'phone' => ['nullable', 'string', 'max:24'],
            'email' => ['nullable', 'email', 'max:190'],
            'guardian_name' => ['required', 'string', 'max:160'],
            'guardian_phone' => ['required', 'string', 'max:24'],
            'guardian_email' => ['nullable', 'email', 'max:190'],
            'guardian_relationship' => ['nullable', Rule::in(['father', 'mother', 'guardian', 'other'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $validator->fails() ? $validator->errors()->all() : [];
    }

    private function serialize(object $import): array
    {
        $previewRows = DB::table('student_import_rows')->where('student_import_id', $import->id)->orderBy('row_number')->limit(50)->get();

        return [
            'public_id' => $import->public_id,
            'source_name' => $import->source_name,
            'status' => $import->status,
            'total_rows' => (int) $import->total_rows,
            'valid_rows' => (int) $import->valid_rows,
            'invalid_rows' => (int) $import->invalid_rows,
            'imported_rows' => (int) $import->imported_rows,
            'completed_at' => $import->completed_at,
            'preview' => $previewRows->map(fn ($row) => [
                'row_number' => (int) $row->row_number,
                'data' => json_decode($row->data, true) ?: [],
                'errors' => json_decode($row->errors ?? '[]', true) ?: [],
                'status' => $row->status,
            ]),
        ];
    }

    private function scopedImport(Request $request, BranchContext $branches, string $publicId): object
    {
        $branch = $branches->get();

        return DB::table('student_imports')
            ->where('public_id', $publicId)
            ->where('organization_id', $branch->organization_id)
            ->where('branch_id', $branch->id)
            ->firstOrFail();
    }

    private function safeSpreadsheetCell(string $value): string
    {
        return preg_match('/^[\s\p{Zs}]*[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
