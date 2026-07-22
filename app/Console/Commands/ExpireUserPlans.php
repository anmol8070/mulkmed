<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserPlan;
use Carbon\Carbon;
use App\Http\Controllers\v1\AppointmentController;
use App\Models\Appointments;
use App\Models\Constants;
use Illuminate\Http\Request;

class ExpireUserPlans extends Command
{
    protected $signature = 'userPlans:expire';
    protected $description = 'Mark expired user plans as inactive and cancel related appointments because user plan is expired';

    public function handle()
    {
        // $today = Carbon::today();

        // // Get expired active plans
        // $plans = UserPlan::where('valid_to', '<', $today) 
        //     ->where('status', Constants::statusUserPlanActive)
        //     ->get();

        // foreach ($plans as $plan) {
        //     // 1. Mark plan inactive
        //     $plan->status = Constants::statusUserPlanInactive;
        //     $plan->save();

        //     // 2. Find related pending appointments
        //     $appointments = Appointments::where('user_plan_id', $plan->id)
        //         ->where('status', Constants::orderPlacedPending)
        //         ->get();

        //     foreach ($appointments as $appointment) {
        //         // First set status to accepted so scheduler can complete
        //         $appointment->status = Constants::orderAccepted;
        //         $appointment->canceled_reason = "plan Expired";
        //         $appointment->save();

        //         // Create fake request for controller
        //         $request = Request::create('', 'POST', [
        //             'doctor_id'      => $appointment->doctor_id,
        //             'appointment_id' => $appointment->id,
        //             // 'completion_otp' => $appointment->completion_otp,
        //             'diagnosed_with' => 'NA'
        //         ]);

        //         // Call controller method
        //         $controller = new AppointmentController();
        //         $controller->cancelAppointmentFromSheduler($request);
        //     }
        // }
        //     $this->info("Expired plans and cancel appointments processed successfully: {$plans->count()}");
        }
}
