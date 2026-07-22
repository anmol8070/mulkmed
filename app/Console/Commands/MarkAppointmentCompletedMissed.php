<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserPlan;
use Carbon\Carbon;
use App\Http\Controllers\v1\AppointmentController;
use App\Models\Appointments;
use App\Models\Constants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\JitsiMeeting;
use App\Helpers\BusinessTimeZoneHelper;


class MarkAppointmentCompletedMissed extends Command
{
    protected $signature = 'appointments:markCompletedMissed';
    protected $description = 'Mark missed and complete appointments and update plans';

    public function handle()
    {
        $connections = ['mysql', 'mulkmed_india'];

        foreach ($connections as $connection) {

            DB::setDefaultConnection($connection);

            $this->info("Processing appointments for DB: {$connection}");

            $this->processAppointments($connection);
        }

        $this->info("Cron execution completed for all databases");
    }

    private function processAppointments(string $connection)
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');

        $appointments = Appointments::on($connection)
            ->where(function ($query) {
                $query->where('appointments.status', Constants::orderPlacedPending)
                    ->orWhere('appointments.status', Constants::orderAccepted);
            })
            ->whereRaw("
                TIMESTAMP(
                    appointments.date,
                    SEC_TO_TIME(TIME_TO_SEC(STR_TO_DATE(appointments.time, '%H%i')) + 90*60)
                ) < ?
            ", [$now])
            ->get();

        foreach ($appointments as $appointment) {

            $jitsiMeeting = JitsiMeeting::on($connection)
                ->where('appointment_id', $appointment->id)
                ->latest()
                ->first();

            if ($jitsiMeeting && ($jitsiMeeting->user_joined == 0 || $jitsiMeeting->doctor_joined == 0)) {

                $plan = UserPlan::on($connection)->find($appointment->user_plan_id);

                if ($plan) {
                    $plan->consultations_used += 1;

                    if ($plan->consultations_used >= $plan->consultations_total) {
                        $plan->status = Constants::statusUserPlanInactive;
                    }

                    $plan->save();
                }

                $appointment->status = Constants::orderAccepted;
                $appointment->save();

                $request = Request::create('', 'POST', [
                    'doctor_id'      => $appointment->doctor_id,
                    'appointment_id' => $appointment->id,
                    'diagnosed_with' => 'NA'
                ]);

                (new AppointmentController())
                    ->markMissedAppointmentFromSheduler($request);
            }
        }

        $this->info("Processed {$appointments->count()} appointments on {$connection}");
    }

}
