<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\RoleCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) env('ACADEMY_DEMO_PASSWORD');
        if ($password === '') {
            throw new RuntimeException('Set ACADEMY_DEMO_PASSWORD in .env before running DemoAccountsSeeder.');
        }

        DB::transaction(function () use ($password): void {
            $organization = Organization::firstOrCreate(
                ['slug' => 'academyhub-demo'],
                [
                    'public_id' => (string) Str::ulid(),
                    'name_ar' => 'أكاديمية هب التجريبية',
                    'name_en' => 'AcademyHub Demo Academy',
                    'email' => 'demo@academyhub.test',
                    'contact_phone' => '+201000000000',
                    'whatsapp_phone' => '+201000000000',
                    'governorate' => 'Cairo',
                    'city' => 'Nasr City',
                    'preferred_locale' => 'en',
                    'currency' => 'EGP',
                    'timezone' => 'Africa/Cairo',
                    'subscription_status' => 'trial',
                    'trial_ends_at' => now()->addDays(14),
                ],
            );

            $roles = app(RoleCatalog::class)->createDefaults($organization);
            $branch = $organization->branches()->firstOrCreate(
                ['name_en' => 'Nasr City Demo Centre'],
                [
                    'public_id' => (string) Str::ulid(),
                    'name_ar' => 'مركز مدينة نصر التجريبي',
                    'address' => 'Fictional training centre, Nasr City',
                    'governorate' => 'Cairo',
                    'city' => 'Nasr City',
                    'phone' => '+201000000000',
                    'is_head_office' => true,
                    'is_active' => true,
                ],
            );

            $organization->academicYears()->firstOrCreate(
                ['branch_id'=>$branch->id,'name'=>'2026/2027'],
                ['starts_on'=>'2026-09-01','ends_on'=>'2027-06-30','status'=>'active','is_active'=>true],
            );
            foreach ([
                ['name_ar'=>'الصف الثالث الثانوي','name_en'=>'Secondary 3','stage'=>'secondary','sort_order'=>1],
                ['name_ar'=>'الصف الثاني الثانوي','name_en'=>'Secondary 2','stage'=>'secondary','sort_order'=>2],
            ] as $level) {
                $organization->educationalLevels()->firstOrCreate(['branch_id'=>$branch->id,'name_ar'=>$level['name_ar']],$level);
            }
            foreach ([
                ['name_ar'=>'الفيزياء','name_en'=>'Physics','code'=>'PHY','color'=>'#6d5dfc'],
                ['name_ar'=>'الرياضيات','name_en'=>'Mathematics','code'=>'MTH','color'=>'#14b8a6'],
            ] as $subject) {
                $organization->subjects()->firstOrCreate(['branch_id'=>$branch->id,'code'=>$subject['code']],$subject);
            }

            $superAdmin = User::updateOrCreate(
                ['email'=>'superadmin@academyhub.test'],
                ['name'=>'AcademyHub Super Admin','password'=>$password,'email_verified_at'=>now(),'is_platform_admin'=>true],
            );
            $teacher = User::updateOrCreate(
                ['email'=>'teacher@academyhub.test'],
                ['name'=>'Demo Physics Teacher','password'=>$password,'email_verified_at'=>now(),'is_platform_admin'=>false],
            );

            OrganizationMembership::updateOrCreate(
                ['organization_id'=>$organization->id,'user_id'=>$superAdmin->id],
                ['role'=>'owner','role_id'=>$roles['owner']->id,'status'=>'active','joined_at'=>now(),'permissions'=>[]],
            );
            OrganizationMembership::updateOrCreate(
                ['organization_id'=>$organization->id,'user_id'=>$teacher->id],
                ['role'=>'teacher','role_id'=>$roles['teacher']->id,'status'=>'active','joined_at'=>now(),'permissions'=>[]],
            );

            DB::table('branch_user')->updateOrInsert(
                ['branch_id'=>$branch->id,'user_id'=>$teacher->id],
                ['organization_id'=>$organization->id,'role'=>'teacher','status'=>'active','created_at'=>now(),'updated_at'=>now()],
            );
        });
    }
}
