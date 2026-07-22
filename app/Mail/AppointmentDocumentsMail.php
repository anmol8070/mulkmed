<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentDocumentsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user_name;
    public $phone_number;
    public $user_email;
    public $doctor_name;
    public $clinic_name;
    public $userAttachments;

    /**
     * Create a new message instance.
     */
    public function __construct(
        $user_name,
        $phone_number,
        $user_email,
        $doctor_name,
        $clinic_name,
        $attachments = []
    ) {
        $this->user_name = $user_name;
        $this->phone_number = $phone_number;
        $this->user_email = $user_email;
        $this->doctor_name = $doctor_name;
        $this->clinic_name = $clinic_name;
        $this->userAttachments = $attachments;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $email = $this->subject('Patient Documents Submitted - Second Medical Opinion')
                    ->view('emails.appointmentDoumentsMail')
                    ->with([
                'user_name'    => $this->user_name,
                        'phone_number' => $this->phone_number,
                'user_email'   => $this->user_email,
                'doctor_name'  => $this->doctor_name,
                'clinic_name'  => $this->clinic_name,
            ]);

        \Log::info('Attachments:', (array) $this->userAttachments);

        foreach ((array) $this->userAttachments as $file) {

            try {

                if (file_exists($file)) {

                    $email->attach($file);

                    \Log::info('Attached file successfully: ' . $file);

                } else {

                    \Log::warning('Attachment file not found: ' . $file);

                }

            } catch (\Throwable $e) {

                \Log::error(
                    'Attachment failed: ' . $file .
                    ' Error: ' . $e->getMessage()
                );
            }
        }

        return $email;
    }
}