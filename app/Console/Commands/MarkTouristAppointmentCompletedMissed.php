<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserPlan;
use Carbon\Carbon;
use App\Http\Controllers\v1\TouristController;
use App\Models\TouristAppointments;
use App\Models\Constants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\TouristJitsiMeeting;
use App\Helpers\BusinessTimeZoneHelper;


class MarkTouristAppointmentCompletedMissed extends Command
{
    protected $signature = 'touristAppointments:markCompletedMissed';
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

        $appointments = TouristAppointments::on($connection)
            ->where(function ($query) {
                $query->where('tourist_appointments.status', Constants::orderPlacedPending)
                    ->orWhere('tourist_appointments.status', Constants::orderAccepted);
            })
            ->whereRaw("
                TIMESTAMP(
                    tourist_appointments.date,
                    SEC_TO_TIME(TIME_TO_SEC(STR_TO_DATE(tourist_appointments.time, '%H%i')) + 90*60)
                ) < ?
            ", [$now])
            ->get();

        foreach ($appointments as $appointment) {

            $jitsiMeeting = TouristJitsiMeeting::on($connection)
                ->where('appointment_id', $appointment->id)
                ->latest()
                ->first();

            if ($jitsiMeeting && ($jitsiMeeting->user_joined == 0 || $jitsiMeeting->doctor_joined == 0)) {
                $appointment->status = Constants::orderAccepted;
                $appointment->save();

                $request = Request::create('', 'POST', [
                    'doctor_id'      => $appointment->doctor_id,
                    'appointment_id' => $appointment->id,
                    'diagnosed_with' => 'NA'
                ]);

                (new TouristController())
                    ->markMissedAppointmentFromSheduler($request);
            }
        }

        $this->info("Processed {$appointments->count()} appointments on {$connection}");
    }

}
