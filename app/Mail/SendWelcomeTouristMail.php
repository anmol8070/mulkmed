<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class SendWelcomeTouristMail extends Mailable
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
    $mail = $this->subject('Welcome to Mulk Tourist Gold Card- Your Card is Active.')
                 ->view('emails.send_tourist_welcome');

    // Example static attachments (if you want to attach specific PDFs)
    $staticFiles = [
        'uploads/Mulk HnH Network List.pdf',
        // 'uploads/Mulk Med Member Benefits.pdf',
    ];

    foreach ($staticFiles as $rel) {
        if (Storage::disk('public')->exists($rel)) {
            $mail->attach(Storage::disk('public')->path($rel));
            continue;
        }

        // fallback: fetch from public URL and attach as binary (handles spaces)
        try {
            $url = asset('storage/' . str_replace('%2F', '/', rawurlencode($rel)));
            $resp = Http::get($url);
            if ($resp->ok()) {
                $mail->attachData($resp->body(), basename($rel));
            } else {
                Log::warning("Static attach HTTP failed for {$url} (status: ".$resp->status().")");
            }
        } catch (\Throwable $e) {
            Log::error("Static attach exception for {$rel}: ".$e->getMessage());
        }
    }

    // Dynamic image attach (from $this->image)
    if (!empty($this->image)) {
        $maybeRel = ltrim($this->image, '/');

        if (Storage::disk('public')->exists($maybeRel)) {
            $mail->attach(Storage::disk('public')->path($maybeRel));
        } else {
            $publicPath = public_path('storage/' . $maybeRel);
            if (file_exists($publicPath) && is_readable($publicPath)) {
                $mail->attach($publicPath);
            } else {
                // last-resort: fetch and attach via URL
                try {
                    $url = asset('storage/' . rawurlencode($maybeRel));
                    $resp = Http::get($url);
                    if ($resp->ok()) {
                        $mail->attachData($resp->body(), basename($maybeRel));
                    } else {
                        Log::warning("Dynamic image HTTP attach failed for {$url} (status: ".$resp->status().")");
                    }
                } catch (\Throwable $e) {
                    Log::error("Dynamic image attach exception for {$maybeRel}: ".$e->getMessage());
                }
            }
        }
    }

    return $mail;
}

}

