<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Appointment Confirmation</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0;">
  <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: auto; background-color: #ffffff; border-radius: 8px; overflow: hidden;">
    <tr>
      <td style="padding: 20px; text-align: center; background-color: #0080cb; color: white;">
        <h2 style="margin: 0;">MulkMed Healthcare</h2>
      </td>
    </tr>
    <tr>
      <td style="padding: 20px;">
        <p style="font-size: 16px;">Dear <strong>{{ $tourist->first_name }}</strong>,</p>
        <p style="font-size: 16px;">Your appointment has been successfully booked with <strong>{{ $doctor->name }}</strong>.</p>

        <h3 style="margin-top: 30px; color: #0080cb;">Appointment Details:</h3>
        <ul style="font-size: 16px; padding-left: 20px;">
          <li><strong>Doctor:</strong> {{ $doctor->name }}.</li>
          <li><strong>Specialty:</strong> {{ $doctor->designation }}.</li>
          <li><strong>Date & Time:</strong> {{ $appointmentDate }}, {{ $appointmentTime }}.</li>
          <li><strong>Link: </strong>
            <a href="{{ $meetingLink }}" style="color: #0080cb; text-decoration: none;">Click here to join video call</a>
          </li>
        </ul>

        {{-- <h4 style="margin-top: 30px;"><strong>Before Your Appointment:</strong></h4>
        <p style="font-size: 16px;">Please join 5–10 minutes early to avoid any delays.</p> --}}

        <p style="font-size: 16px; margin-top: 40px;">Wishing you good health,</p>
        <p style="font-size: 16px;"><strong>MulkMed Healthcare.</strong></p>
      </td>
    </tr>
    <tr>
      <td style="background-color: #f0f0f0; text-align: center; padding: 10px; font-size: 12px; color: #888;">
         © {{ now()->year }} MulkMed Healthcare. All rights reserved.
    </td>
    </tr>
  </table>
</body>
</html>
