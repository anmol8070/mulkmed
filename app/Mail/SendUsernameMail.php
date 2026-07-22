<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendUsernameMail extends Mailable
{
    use Queueable, SerializesModels;

    public $username;
    public $password;

    public $fullname;

    public function __construct($username, $fullname, $password)
    {
        $this->username = $username;
        $this->fullname = $fullname;
        $this->password = $password;
    }

    public function build()
    {
        return $this->subject('Your MulkMed Login Details')
                    ->view('emails.send_username');
    }
}

