<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class JitsiMeetingLinkTourist extends Mailable
{
    use Queueable, SerializesModels;

    public $jitsiLink;

    public function __construct($appointment, $doctor, $tourist, $meetingLink)
    {
        $this->doctorName = $doctor->name;
        $this->appointmentDate = Carbon::parse($appointment->date)->format('m-d-Y');
        $this->appointment = $appointment;
        $this->doctor = $doctor;
        $this->tourist = $tourist;
        $this->meetingLink = $meetingLink;
        $this->appointmentTime = Carbon::createFromFormat('Hi', $appointment->time)->format('g:i A');
    }

    public function build()
    {
        return $this->subject("Appointment Confirmed with {$this->doctorName} on {$this->appointmentDate}")
                    ->view('emails.jitsi_link_tourist')->with([
                        "appointment" => $this->appointment,
                        "doctor" => $this->doctor,
                        "tourist" => $this->tourist,
                        "meetingLink" => $this->meetingLink,
                        "appointmentDate" => $this->appointmentDate,
                        "appointmentTime" => $this->appointmentTime

                    ]);
    }
}


