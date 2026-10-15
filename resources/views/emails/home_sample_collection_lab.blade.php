<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <p>Dear {{ $labName }},</p>

    <p>A new home sample collection has been booked through Mulk Med. Details are below.</p>

    <table cellpadding="6" cellspacing="0" style="border-collapse: collapse; border: 1px solid #ddd;">
        <tr><td style="border: 1px solid #ddd;"><strong>Booking ID</strong></td><td style="border: 1px solid #ddd;">{{ $booking->id }}</td></tr>
        <tr><td style="border: 1px solid #ddd;"><strong>Patient</strong></td><td style="border: 1px solid #ddd;">{{ $user->fullname ?? '-' }}</td></tr>
        <tr><td style="border: 1px solid #ddd;"><strong>Mobile</strong></td><td style="border: 1px solid #ddd;">{{ $booking->country_code }} {{ $booking->mobile_number }}</td></tr>
        <tr><td style="border: 1px solid #ddd;"><strong>Package</strong></td><td style="border: 1px solid #ddd;">{{ $booking->package_title ?? '-' }}</td></tr>
        <tr><td style="border: 1px solid #ddd;"><strong>Order ID</strong></td><td style="border: 1px solid #ddd;">{{ $booking->order_id ?? '-' }}</td></tr>
        <tr><td style="border: 1px solid #ddd;"><strong>Address</strong></td><td style="border: 1px solid #ddd;">
            {{ implode(', ', array_filter([$booking->flat_no ? 'Flat ' . $booking->flat_no : null, $booking->building, $booking->area, $booking->emirate])) }}
            @if ($booking->latitude && $booking->longitude)
                <br><a href="https://www.google.com/maps?q={{ $booking->latitude }},{{ $booking->longitude }}">View on map</a>
            @endif
        </td></tr>
        <tr><td style="border: 1px solid #ddd;"><strong>Preferred Date</strong></td><td style="border: 1px solid #ddd;">{{ $booking->preferred_date?->format('d M, Y') }}</td></tr>
        <tr><td style="border: 1px solid #ddd;"><strong>Preferred Time</strong></td><td style="border: 1px solid #ddd;">{{ $booking->preferred_time_slot }}</td></tr>
        @if ($booking->notes)
            <tr><td style="border: 1px solid #ddd;"><strong>Notes</strong></td><td style="border: 1px solid #ddd;">{{ $booking->notes }}</td></tr>
        @endif
    </table>

    <p>Please contact the patient to confirm the visit.</p>

    <p>Regards,<br>Team Mulk Med</p>
</body>
</html>
