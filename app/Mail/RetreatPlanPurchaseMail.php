<?php

namespace App\Mail;

use App\Models\Users;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the purchasing patient when they buy a Retreat Plan.
 */
class RetreatPlanPurchaseMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $planName;

    public function __construct($user, string $planName = '')
    {
        $this->user = $user;
        $this->planName = $planName;
    }

    public function build()
    {
        $patientName = trim((string) ($this->user?->fullname ?? $this->user?->name ?? ''));
        if ($patientName === '') {
            $patientName = '-';
        }

        $patientId = $this->user?->id ?? '-';
        $email = $this->user?->email ?? $this->user?->identity ?? '-';

        $phone = trim((string) (($this->user?->country_code ?? '') . ' ' . ($this->user?->phone_number ?? '')));
        if ($phone === '') {
            $phone = '-';
        }

        $planName = !empty($this->planName) ? $this->planName : '-';

        $subject = 'New Retreat Plan Purchase – ' . $patientName;

        return $this->subject($subject)
            ->view('emails.retreat_plan_purchase', [
                'patientName' => $patientName,
                'patientId'   => $patientId,
                'email'       => $email,
                'phone'       => $phone,
                'planName'    => $planName,
            ]);
    }
}

