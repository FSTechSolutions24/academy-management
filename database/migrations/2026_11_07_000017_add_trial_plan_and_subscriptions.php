<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { $now=now(); $plan=DB::table('subscription_plans')->where('slug','free-trial')->first(); if(!$plan){$id=DB::table('subscription_plans')->insertGetId(['slug'=>'free-trial','name'=>'Free trial','description'=>'Explore AcademyHub free for 14 days.','monthly_price_minor'=>0,'yearly_price_minor'=>0,'centre_limit'=>1,'student_limit'=>100,'staff_limit'=>2,'group_limit'=>10,'currency'=>'EGP','features'=>json_encode(['All core teaching tools','One centre','100 students','14 days, no card required']),'is_active'=>true,'is_featured'=>false,'position'=>0,'created_at'=>$now,'updated_at'=>$now]);}else{$id=$plan->id;} foreach(DB::table('organizations')->where('subscription_status','trial')->get() as $organization){if(!DB::table('organization_subscriptions')->where('organization_id',$organization->id)->exists()){DB::table('organization_subscriptions')->insert(['organization_id'=>$organization->id,'subscription_plan_id'=>$id,'status'=>$organization->trial_ends_at && $organization->trial_ends_at > $now ? 'trialing':'expired','billing_cycle'=>'trial','starts_at'=>$organization->created_at ?: $now,'ends_at'=>$organization->trial_ends_at ?: $now,'created_at'=>$now,'updated_at'=>$now]);}} }
 public function down(): void { $plan=DB::table('subscription_plans')->where('slug','free-trial')->first(); if($plan){DB::table('organization_subscriptions')->where('subscription_plan_id',$plan->id)->delete();DB::table('subscription_plans')->where('id',$plan->id)->delete();} }
};
