<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\UploadedFile;


class ArAiVitalMesaReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $report_link;

    public function __construct($user, $report_link)
    {
        $this->user = $user;
        $this->report_link = $report_link;
    }

    public function build()
    {
        return $this->subject('Your MIDAS Symptoms Checker Report from MulkMed')
                    ->view('emails.arAiVitalMesaReport') // create this view
                    ->with([
                        "user" => $this->user,
                        "report_link" => $this->report_link
                    ]);
    }
}

