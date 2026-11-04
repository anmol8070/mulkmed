<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <p>Dear Thumbay Labs Team,</p>

    <p>Please arrange a home blood sample collection for the patient below and reply to confirm.</p>

    <p>
        Package: {{ $packageLabel }}<br>
        Patient: {{ $patientName }}, {{ $patientGender }}, {{ $patientDob }}<br>
        Mobile: {{ $patientMobile }}<br>
        Email: {{ $patientEmail }}<br>
        Address: {{ $addressLine }}<br>
        Map:
        @if ($mapUrl)
            <a href="{{ $mapUrl }}">{{ $mapUrl }}</a>
        @else
            -
        @endif
        <br>
        Date and time: {{ $collectionDate }}, {{ $timeSlot }}
    </p>

    <p>Kindly call the patient and update them on the blood test requirements, such as fasting.</p>

    <p>Payment has been made to Mulk Med. Please do not collect any payment from the patient.</p>

    <p>Please email the results only to the patient at {{ $patientEmail }}.</p>

    <p>Thank you,<br>Mulk Med Longevity Hub Team</p>
</body>
</html>
