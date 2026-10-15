<?php
namespace App\Mail;

use App\Models\HomeSampleCollection;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the lab the user picked when a home sample collection is booked.
 */
class HomeSampleCollectionLabMail extends Mailable
{
    use Queueable, SerializesModels;

    public $booking;
    public $user;
    public $labName;

    public function __construct(HomeSampleCollection $booking, $user, $labName)
    {
        $this->booking = $booking;
        $this->user = $user;
        $this->labName = $labName;
    }

    public function build()
    {
        return $this->subject('New Home Sample Collection Request #' . $this->booking->id)
                    ->view('emails.home_sample_collection_lab');
    }
}
