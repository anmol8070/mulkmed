<?php
namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PostpaidBillingReminderMail extends Mailable
{
    use SerializesModels;

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
        return $this->subject("Postpaid Billing Cycle Reminder")
            ->view('emails.postpaid_billing_reminder');
    }
}
