<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class SendWelcomeHnHMail extends Mailable
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
    // Base mail object
    $mail = $this->subject('Welcome to Mulk HnH Healthcare Discount Card- Your Card is Active.')
                 ->view('emails.send_hnh_welcome');

    // --- Attach static files (example list) ---
    $staticFiles = [
        'uploads/Mulk HnH Network List.pdf',
        // 'uploads/Mulk Med Member Benefits.pdf',
    ];

    foreach ($staticFiles as $rel) {
        // Preferred: file stored on disk via `public` disk (storage/app/public)
        if (Storage::disk('public')->exists($rel)) {
            $path = Storage::disk('public')->path($rel);
            if (is_readable($path)) {
                $mail->attach($path);
                continue;
            }
        }

        // Fallback: attempt to fetch from public URL and attach as binary
        $publicUrl = asset('storage/' . str_replace('%2F', '/', rawurlencode($rel)));
        try {
            $resp = Http::get($publicUrl);
            if ($resp->ok()) {
                $mail->attachData($resp->body(), basename($rel));
            } else {
                Log::warning("Attachment fallback failed (HTTP): {$publicUrl} status: ".$resp->status());
            }
        } catch (\Throwable $e) {
            Log::error("Attachment fallback exception for {$publicUrl}: ".$e->getMessage());
        }
    }

    // --- Attach dynamic image if present ($this->image) ---
    if (!empty($this->image)) {
        // If $this->image already contains "uploads/..." stored on the public disk
        $maybeRel = $this->image;
        if (Storage::disk('public')->exists($maybeRel)) {
            $mail->attach(Storage::disk('public')->path($maybeRel));
        } else {
            // Try public/storage path (if you kept the path as 'storage/yourfile.ext' in DB)
            $publicPath = public_path('storage/' . $maybeRel);
            if (file_exists($publicPath) && is_readable($publicPath)) {
                $mail->attach($publicPath);
            } else {
                // Last-resort: fetch and attach via URL
                $publicUrl = asset('storage/' . rawurlencode($maybeRel));
                try {
                    $resp = Http::get($publicUrl);
                    if ($resp->ok()) {
                        $mail->attachData($resp->body(), basename($maybeRel));
                    } else {
                        Log::warning("Dynamic image attach failed (HTTP) for: {$publicUrl} status: ".$resp->status());
                    }
                } catch (\Throwable $e) {
                    Log::error("Dynamic image attach exception for {$publicUrl}: ".$e->getMessage());
                }
            }
        }
    }

    return $mail;
}

}

