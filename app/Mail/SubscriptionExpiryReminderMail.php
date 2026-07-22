<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpiryReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $expiryDate;
    public $daysLeft;

    public function __construct($user, $expiryDate, $daysLeft)
    {
        $this->user = $user;
        $this->expiryDate = $expiryDate;
        $this->daysLeft = $daysLeft;
    }

    public function build()
    {
        return $this->subject("Subscription Expiry Reminder - {$this->daysLeft} Days Left")
                    ->view('emails.subscription_expiry_reminder');
    }
}
