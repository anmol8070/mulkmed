<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class SendWelcomeSeniorMail extends Mailable
{
    use Queueable, SerializesModels;
    public $user_name;

    public $fullname;

    public $image;

    public function __construct($user_name, $fullname, $image)
    {
        $this->user_name = $user_name;
        $this->fullname = $fullname;
        $this->image = $image;
    }

    public function build()
{
    $mail = $this->subject('Welcome to Mulk Senior Health Card – Your Card is Active')
                 ->view('emails.send_senior_welcome');

    // Static attachments (prefer server path)
    $static = 'uploads/Mulk HnH Network List.pdf';

    if (Storage::disk('public')->exists($static)) {
        $mail->attach(Storage::disk('public')->path($static));
    } else {
        // fallback: fetch from public URL and attach as binary (handles spaces)
        try {
            $url = asset('storage/' . str_replace('%2F', '/', rawurlencode($static)));
            $resp = Http::get($url);
            if ($resp->ok()) {
                $mail->attachData($resp->body(), basename($static));
            } else {
                Log::warning("Static attach failed (HTTP) for {$url}, status: ".$resp->status());
            }
        } catch (\Throwable $e) {
            Log::error("Static attach exception: ".$e->getMessage());
        }
    }

    // Dynamic image if present
    if (!empty($this->image)) {
        $maybeRel = ltrim($this->image, '/'); // normalize

        // try public disk (storage/app/public/{maybeRel})
        if (Storage::disk('public')->exists($maybeRel)) {
            $mail->attach(Storage::disk('public')->path($maybeRel));
        } else {
            // try public/storage path (if you saved 'storage/...' in DB)
            $publicPath = public_path('storage/' . $maybeRel);
            if (file_exists($publicPath) && is_readable($publicPath)) {
                $mail->attach($publicPath);
            } else {
                // final fallback: fetch and attach via URL
                try {
                    $url = asset('storage/' . rawurlencode($maybeRel));
                    $resp = Http::get($url);
                    if ($resp->ok()) {
                        $mail->attachData($resp->body(), basename($maybeRel));
                    } else {
                        Log::warning("Image attach failed (HTTP) for {$url}, status: ".$resp->status());
                    }
                } catch (\Throwable $e) {
                    Log::error("Image attach exception: ".$e->getMessage());
                }
            }
        }
    }

    return $mail;
}

}

