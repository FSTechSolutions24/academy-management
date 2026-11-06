<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessStudentImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function __construct(public readonly int $importId) {}

    public function handle(): void
    {
        $import = DB::table('student_imports')->where('id', $this->importId)->first();
        if (! $import || ! in_array($import->status, ['queued', 'processing'], true)) {
            return;
        }

        DB::table('student_imports')->where('id', $this->importId)->update(['status' => 'processing', 'updated_at' => now()]);
        $processed = DB::transaction(function () use ($import): int {
            $rows = DB::table('student_import_rows')
                ->where('student_import_id', $this->importId)
                ->where('status', 'ready')
                ->orderBy('row_number')
                ->limit(100)
                ->lockForUpdate()
                ->get();
            if ($rows->isEmpty()) {
                return 0;
            }

                DB::table('student_sequences')->insertOrIgnore(['branch_id' => $import->branch_id, 'next_number' => 1, 'created_at' => now(), 'updated_at' => now()]);
                $sequence = DB::table('student_sequences')->where('branch_id', $import->branch_id)->lockForUpdate()->first();
                $nextNumber = (int) $sequence->next_number;
                foreach ($rows as $row) {
                    $data = json_decode($row->data, true, flags: JSON_THROW_ON_ERROR);
                    $student = Student::create([
                        'public_id' => (string) Str::ulid(),
                        'organization_id' => $import->organization_id,
                        'branch_id' => $import->branch_id,
                        'student_code' => 'STU-'.str_pad((string) $nextNumber++, 6, '0', STR_PAD_LEFT),
                        'name_ar' => $data['name_ar'],
                        'name_en' => $data['name_en'] ?: null,
                        'date_of_birth' => $data['date_of_birth'] ?: null,
                        'gender' => $data['gender'] ?: null,
                        'phone' => $data['phone'] ?: null,
                        'email' => $data['email'] ?: null,
                        'notes' => $data['notes'] ?: null,
                        'status' => 'active',
                    ]);
                    $guardianPhone = $data['guardian_phone'];
                    $guardian = Guardian::firstOrCreate(
                        ['organization_id' => $import->organization_id, 'phone' => $guardianPhone],
                        ['name' => $data['guardian_name'], 'email' => $data['guardian_email'] ?: null, 'whatsapp_phone' => $guardianPhone],
                    );
                    $guardian->update(['name' => $data['guardian_name'], 'email' => $data['guardian_email'] ?: null, 'whatsapp_phone' => $guardianPhone]);
                    $student->guardians()->attach($guardian->id, ['relationship' => $data['guardian_relationship'] ?: 'guardian', 'is_primary' => true]);
                    $student->enrollments()->create([
                        'organization_id' => $import->organization_id,
                        'branch_id' => $import->branch_id,
                        'status' => 'active',
                        'enrolled_on' => today(),
                    ]);
                    AuditLog::create([
                        'organization_id' => $import->organization_id,
                        'branch_id' => $import->branch_id,
                        'actor_id' => $import->created_by,
                        'event' => 'student.created',
                        'auditable_type' => $student->getMorphClass(),
                        'auditable_id' => $student->id,
                        'after' => ['student_code' => $student->student_code, 'source' => 'student_import'],
                        'ip_address' => $import->ip_address,
                        'user_agent' => $import->user_agent,
                    ]);
                    DB::table('student_import_rows')->where('id', $row->id)->update(['status' => 'imported', 'updated_at' => now()]);
                }
                DB::table('student_sequences')->where('branch_id', $import->branch_id)->update(['next_number' => $nextNumber, 'updated_at' => now()]);
                return $rows->count();
        });

        if ($processed > 0) {
            DB::table('student_imports')->where('id', $this->importId)->increment('imported_rows', $processed, ['updated_at' => now()]);
            self::dispatch($this->importId);
            return;
        }

        DB::table('student_imports')->where('id', $this->importId)->update(['status' => 'completed', 'completed_at' => now(), 'storage_path' => null, 'updated_at' => now()]);
        if ($import->storage_path) {
            Storage::disk('local')->delete($import->storage_path);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $import = DB::table('student_imports')->where('id', $this->importId)->first();
        if (! $import) {
            return;
        }

        DB::table('student_import_rows')->where('student_import_id', $this->importId)->where('status', 'ready')->update([
            'status' => 'failed',
            'errors' => json_encode(['Import processing failed. Download this error file and retry the affected rows.'], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
        DB::table('student_imports')->where('id', $this->importId)->update(['status' => 'failed', 'storage_path' => null, 'updated_at' => now()]);
        if ($import->storage_path) {
            Storage::disk('local')->delete($import->storage_path);
        }
    }
}
