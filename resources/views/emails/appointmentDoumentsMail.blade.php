<!DOCTYPE html>
<html>
<body>
    <p><strong>Dear Team,</strong></p>

    <p>The patient {{ $user_name }} has submitted documents for [Second Medical Opinion/Procedure] 
       with {{ $doctor_name }} ({{ $clinic_name }}).</p>

    <p><strong>Contact Details:</strong></p>
    <p>Phone: {{ $phone_number }}</p>
    <p>Email: {{ $user_email }}</p>

    <p>Please find the attachments for review.</p>
    <p>Thank you.</p>
</body>
</html>