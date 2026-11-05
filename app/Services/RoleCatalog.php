<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationRole;
use Illuminate\Support\Collection;

final class RoleCatalog
{
    public const DEFINITIONS = [
        'owner'=>['Owner',['*']],
        'organization_admin'=>['Organisation administrator',['manage_staff','manage_centres','manage_academic_structure','view_students','create_students','edit_students','archive_students','import_students','view_attendance','record_attendance','correct_attendance','create_examinations','enter_marks','publish_results','view_finance','record_payments','send_messages','manage_parent_links','view_reports','manage_settings']],
        'teacher'=>['Teacher',['view_students','create_students','edit_students','view_attendance','record_attendance','create_examinations','enter_marks','publish_results','view_reports']],
        'assistant'=>['Assistant',['view_students','create_students','view_attendance','record_attendance','enter_marks']],
        'receptionist'=>['Receptionist',['view_students','create_students','edit_students','view_attendance','record_attendance','record_payments']],
        'finance_officer'=>['Finance officer',['view_students','view_finance','record_payments','view_reports']],
    ];

    public function createDefaults(Organization $organization): Collection
    {
        return collect(self::DEFINITIONS)->mapWithKeys(function (array $definition, string $key) use ($organization): array {
            $role = OrganizationRole::firstOrCreate(
                ['organization_id'=>$organization->id,'key'=>$key],
                ['name'=>$definition[0],'permissions'=>$definition[1],'is_system'=>true],
            );
            return [$key=>$role];
        });
    }
}
