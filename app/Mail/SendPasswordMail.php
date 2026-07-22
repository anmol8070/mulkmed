<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $password;
    public $user_name;

    public $fullname;

    public function __construct($password, $user_name, $fullname)
    {
        $this->password = $password;
        $this->user_name = $user_name;
        $this->fullname = $fullname;
    }

    public function build()
    {
        return $this->subject('Your MulkMed Login Details')
                    ->view('emails.send_password');
    }
}

