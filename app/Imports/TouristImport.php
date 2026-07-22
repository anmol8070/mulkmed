<?php

namespace App\Imports;

use App\Models\TouristList;
use App\Models\AgencySubscriptionPlans;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Models\Agencies;
use App\Models\RiderAllocation;
use App\Models\AgencyRidersUsage;
use PhpOffice\PhpSpreadsheet\Shared\Date;


class TouristImport implements ToModel, WithHeadingRow
{
    protected $errors = []; // Store riders limit errors
    protected $importLogId;
    
    public function __construct($importLogId)
    {
        $this->importLogId = $importLogId;
    }

    public function model(array $row)
    {
       
        return DB::transaction(function () use ($row) {

            //  dd($row);
            $agency_id = session('agency_id');
      

            // 🔹 Fetch agency info
            $partner_info = Agencies::find($agency_id);
            if (!$partner_info) {
                throw new Exception('Partner not found.');
            }
           

            // 🔹 Get latest rider allocation
            $riderAllocation = RiderAllocation::where('agency_id', $agency_id)
                // ->orderBy('id', 'desc')
                ->first();

            if (!$riderAllocation) {
                throw new Exception('Plan not allocated.');
            }

              
            $days = 1; // Default days


            // 🔹 Prepaid plan logic
            // if ($riderAllocation->payment_type === 'Prepaid') {  
                 $checkIn = $checkOut = $fly_in = $fly_out = $start_date = $booking_id =  $service_type = null;     

                // Validate required dates
                if ($partner_info->agency_type == 2) { // Hotel
                    if (empty($row['check_in_date'])) throw new Exception('Check-in time missing.');
                    if (empty($row['check_out_date'])) throw new Exception('Check-out time missing.');
                    // $checkIn = Carbon::parse($row['check_in_date']);
                    // $checkOut = Carbon::parse($row['check_out_time']);

                     if (is_numeric($row['check_in_date'])) {
                            $checkIn = Carbon::instance(
                                Date::excelToDateTimeObject($row['check_in_date'])
                            );
                        } else {
                            $checkIn = Carbon::parse($row['check_in_date']);
                        }

                        if (is_numeric($row['check_out_date'])) {
                            $checkOut = Carbon::instance(
                                Date::excelToDateTimeObject($row['check_out_date'])
                            );
                        } else {
                            $checkOut = Carbon::parse($row['check_out_date']);
                        }
         ;
                    $days = $checkIn->diffInDays($checkOut);
                    if ($days <= 0) $days = 1;

                     $nextId = TouristList::max('id') + 1;
                     $bookingCode       = 'BK' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
                    //  $booking_id     =  $row['booking_id'] ?? null;
                     $booking_id        = $bookingCode;
                     $service_type      = 'Inbound';
                }

                if ($partner_info->agency_type == 1) { // Travel
                    if (empty($row['fly_in'])) throw new Exception('Fly-in date missing.');
                    if (empty($row['fly_out'])) throw new Exception('Fly-out date missing.');

                    // $flyIn = Carbon::parse($row['fly_in']);
                    // $flyOut = Carbon::parse($row['fly_out']);

                    
                     if (is_numeric($row['fly_in'])) {
                            $fly_in = Carbon::instance(
                                Date::excelToDateTimeObject($row['fly_in'])
                            );
                        } else {
                            $fly_in = Carbon::parse($row['fly_in']);
                        }

                        if (is_numeric($row['fly_out'])) {
                            $fly_out = Carbon::instance(
                                Date::excelToDateTimeObject($row['fly_out'])
                            );
                        } else {
                            $fly_out = Carbon::parse($row['fly_out']);
                        }

                    // $days = $fly_in->diffInDays($fly_out);
                    // if ($days <= 0) $days = 1;
                     $days = 1 ;

                   $booking_id         = $row['pnr_no'] ?? null;
                   $service_type       = $row['service_type'] ?? null;
                }

                if ($partner_info->agency_type == 3) { // Visa
                    if (empty($row['start_date'])) throw new Exception('Start date missing.');
                    // $days = intval($row['start_date']);
                        if (is_numeric($row['start_date'])) {
                            $start_date = Carbon::instance(
                                Date::excelToDateTimeObject($row['start_date'])
                            );
                        } else {
                            $start_date = Carbon::parse($row['start_date']);
                        }

                        // $days = $row['visa_expiry_days'];
                         $days = 1 ;

                        $booking_id         = $row['passport_no'] ?? null;
                        $service_type       = $row['service_type'] ?? null;
                }


                // 🔹 Get agency plan
                $plan = AgencySubscriptionPlans::where('subscription_id', $riderAllocation->id)
                    ->where('agency_id', $agency_id)
                    ->first();

                 
                if (!$plan) {
                    throw new Exception('Subscription plan not found.');
                }

            // 🔹 Prepaid plan logic
            if ($riderAllocation->payment_type === 'Prepaid') {    

                // 🔹 Riders limit check
                if ($service_type === 'Outbound' && $plan->outbound_remaining_riders < $days) {
                    $this->errors[] = 'Your outbound riders limit has been exhausted.';
                    return null; // Skip insert
                }

                if ($service_type === 'Inbound' && $plan->inbound_remaining_riders < $days) {
                    $this->errors[] = 'Your inbound riders limit has been exhausted.';
                    return null; // Skip insert
                }
            }

            if ($service_type === 'Outbound'){

                $outbound_riders = $days;

            }

            if ($service_type === 'Inbound'){

                 $inbound_riders = $days;
            }

            
            // 🔹 Insert tourist
            $tourist = TouristList::create([
                'agent_id'       => $agency_id ?? null,
                'agent_type'     => $partner_info->agency_type ?? null,
                'first_name'     => $row['first_name'] ?? null,
                'last_name'      => $row['last_name'] ?? null,
                // 'booking_id'     => $row['booking_id'] ?? null,
                'booking_id'     => $booking_id ?? null,
                'check_in_time'  => $checkIn ?? null,
                'check_out_time' => $checkOut ?? null,
                'country_code' => $row['country_code'] ?? null,
                'contact_number' => $row['mobile_number'] ?? null,
                'service_type'   => $service_type,
                'fly_in'         => $fly_in ?? null,
                'fly_out'        => $fly_out ?? null,
                'start_date'     => $start_date ?? null,
                'import_log_id'  => $this->importLogId, // ✅ THIS IS IMPORTANT
                'inbound_riders'        => $inbound_riders ?? null,
                'outbound_riders'       => $outbound_riders ?? null,
                'number_of_midas'               => $days ?? null,
                'number_of_ai_health_check'     => $days ?? null,
                'number_of_consultation'        => $days ?? null,
                'visa_expiry_days'              => $row['visa_expiry_days'] ?? null,
                

            ]);

            $inbound_riders_count = 0;
            $outbound_riders_count = 0;

            // 🔹 Decrease remaining riders
            if (!empty($plan)) {
                if ($service_type === 'Outbound') {
                    $plan->decrement('outbound_remaining_riders', $days);
                    $outbound_riders_count = $days;
                    $outbound_amount = $outbound_riders_count * $riderAllocation->outbound_amount;
                    $plan->increment('amount', $outbound_amount);
                }

                if ($service_type === 'Inbound') {
                    $plan->decrement('inbound_remaining_riders', $days);
                    $inbound_riders_count = $days;
                    $inbound_amount = $inbound_riders_count * $riderAllocation->inbound_amount;
                    $plan->increment('amount', $inbound_amount);
                }
            }

            // 🔹 Insert into agency_riders_usage
            AgencyRidersUsage::create([
                'agency_id'       => $agency_id,
                'plan_id'         => $plan->id ?? null,
                'tourist_id'      => $tourist->id,
                'service_type'    => $service_type ?? null,
                'payment_type'    => $riderAllocation->payment_type,
                'inbound_riders'  => $inbound_riders_count,
                'outbound_riders' => $outbound_riders_count,
                'used_riders'     => $days,
            ]);

            return $tourist;
        });
    }

    // 🔹 Get only riders limit errors after import
    public function getErrors()
    {
        return $this->errors;
    }
}
