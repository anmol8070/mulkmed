<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\UploadedFile;

class AiVitalReportMail extends Mailable
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
        return $this->subject('Your AI Health Check Report from MulkMed')
                    ->view('emails.aiVitalReport') // create this view
                    ->attach($this->uploadedFile->getRealPath(), [
                        'as' => $this->uploadedFile->getClientOriginalName(),
                        'mime' => $this->uploadedFile->getMimeType(),
                    ]);
    }
}
