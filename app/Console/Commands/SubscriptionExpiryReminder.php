<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RiderAllocation;
use App\Models\Agencies;
use App\Mail\SubscriptionExpiryReminderMail;
use App\Mail\PostpaidBillingReminderMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SubscriptionExpiryReminder extends Command
{
    protected $signature = 'subscription:expiry-reminder';
    protected $description = 'Send subscription expiry reminder emails (7, 3, 1 days)';

    public function handle()
    {
        $reminderDays = [7, 3, 1];

        foreach ($reminderDays as $days) {

            $targetDate = Carbon::now()->addDays($days)->toDateString();

            $subscriptions = RiderAllocation::whereDate('expiry_date', $targetDate)->get();

            foreach ($subscriptions as $subscription) {

                $sentDays = explode(',', $subscription->reminder_days_sent ?? '');

                // Skip if reminder already sent
                // if (in_array($days, $sentDays)) {
                //     continue;
                // }

                $user = Agencies::find($subscription->agency_id);

                if ($user && $user->email) {

                // 🔹 PREPAID MAIL
                if ($subscription->payment_type === 'Prepaid') {

                    Mail::to($user->email)->send(
                        new SubscriptionExpiryReminderMail(
                            $user,
                            $subscription->expiry_date,
                            $days
                        )
                    );
                }

                // 🔹 POSTPAID MAIL
                if ($subscription->payment_type === 'Postpaid') {

                    Mail::to($user->email)->send(
                        new PostpaidBillingReminderMail(
                            $user,
                            $subscription->expiry_date,
                            $days
                        )
                    );
                }

                    // Save reminder sent info
                    // $sentDays[] = $days;
                    // $subscription->reminder_days_sent = implode(',', array_filter($sentDays));
                    // $subscription->save();
                }
            }
        }

        $this->info('Subscription expiry reminder emails sent successfully.');
    }
}
