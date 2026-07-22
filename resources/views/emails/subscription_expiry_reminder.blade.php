<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Subscription Expiry Reminder</title>
</head>
<body>

<p>Dear {{ $user->name }},</p>

<p>
This is a reminder that your Mulk AI Health Shield Riders prepaid subscription will expire on
<strong>{{ \Carbon\Carbon::parse($expiryDate)->format('d M Y') }}</strong>.
</p>

<p>
Kindly renew in advance to continue uninterrupted access to our services.
</p>

<p>
Thank you,<br>
<strong>Team Mulk Med.</strong>
</p>

</body>
</html>
