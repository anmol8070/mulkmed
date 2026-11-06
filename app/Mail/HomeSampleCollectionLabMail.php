<?php

namespace App\Mail;

use App\Models\Constants;
use App\Models\HomeSampleCollection;
use App\Services\SenoclockAiService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when a home sample collection is booked.
 */
class HomeSampleCollectionLabMail extends Mailable
{
    use Queueable, SerializesModels;

    public const TO = ['dipali.p@reapmind.com', 'sakshi.k@reapmind.com'];

    public const CC = ['anmol.n@reapmind.com'];

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
        $patientName = $this->user?->fullname ?? $this->user?->name ?? '-';
        $patientEmail = $this->user?->email ?? '-';
        $gender = $this->genderLabel();
        $dob = !empty($this->user?->dob)
            ? Carbon::parse($this->user->dob)->format('d M Y')
            : '-';

        $mobile = trim(($this->booking->country_code ?? '') . ' ' . ($this->booking->mobile_number ?? ''));
        $address = implode(', ', array_filter([
            $this->booking->flat_no,
            $this->booking->building,
            $this->booking->area,
            $this->booking->emirate,
        ]));

        $mapUrl = ($this->booking->latitude && $this->booking->longitude)
            ? 'https://www.google.com/maps?q=' . $this->booking->latitude . ',' . $this->booking->longitude
            : null;

        $date = $this->booking->preferred_date
            ? $this->booking->preferred_date->format('d M Y')
            : '-';

        return $this->subject('Home blood sample collection request #' . $this->booking->id)
            ->replyTo(self::CC[0])
            ->view('emails.home_sample_collection_lab', [
                'patientName' => $patientName,
                'patientGender' => $gender,
                'patientDob' => $dob,
                'patientEmail' => $patientEmail,
                'patientMobile' => $mobile !== '' ? $mobile : '-',
                'packageLabel' => $this->packageLabel(),
                'addressLine' => $address !== '' ? $address : '-',
                'mapUrl' => $mapUrl,
                'collectionDate' => $date,
                'timeSlot' => $this->booking->preferred_time_slot ?: '-',
            ]);
    }

    private function packageLabel(): string
    {
        $title = (string) ($this->booking->package_title ?? '');

        if (stripos($title, 'comprehensive') !== false) {
            return 'Comprehensive – ' . count(SenoclockAiService::ALL_SENOCLOCK_BIOMARKER_NAMES) . ' biomarkers';
        }

        if (stripos($title, 'basic') !== false) {
            return 'Basic – ' . count(SenoclockAiService::REQUIRED_SENOCLOCK_BIOMARKERS) . ' biomarkers';
        }

        return $title !== '' ? $title : '-';
    }

    private function genderLabel(): string
    {
        $gender = $this->user?->gender ?? null;
        if ($gender === null || $gender === '') {
            return '-';
        }

        if ((int) $gender === Constants::genderMale) {
            return 'Male';
        }

        if ((int) $gender === Constants::genderFemale || (int) $gender === 2) {
            return 'Female';
        }

        return '-';
    }
}
