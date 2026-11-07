<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('organization_roles')->where('key', 'teacher')->get()->each(function (object $role): void {
            $permissions = json_decode($role->permissions, true) ?: [];
            if (! in_array('import_students', $permissions, true)) {
                $permissions[] = 'import_students';
                DB::table('organization_roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions), 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        DB::table('organization_roles')->where('key', 'teacher')->get()->each(function (object $role): void {
            $permissions = array_values(array_filter(json_decode($role->permissions, true) ?: [], fn ($permission) => $permission !== 'import_students'));
            DB::table('organization_roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions), 'updated_at' => now()]);
        });
    }
};
