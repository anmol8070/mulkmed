<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\UploadedFile;


class AiVitalMesaReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $uploadedFile;

    public function __construct($user, UploadedFile $uploadedFile)
    {
        $this->user = $user;
        $this->uploadedFile = $uploadedFile;
    }

    public function build()
    {
        return $this->subject('Your MIDAS Symptoms Checker Report from MulkMed')
                    ->view('emails.aiVitalMesaReport') // create this view
                    ->attach($this->uploadedFile->getRealPath(), [
                        'as' => $this->uploadedFile->getClientOriginalName(),
                        'mime' => $this->uploadedFile->getMimeType(),
                    ]);
    }
}

