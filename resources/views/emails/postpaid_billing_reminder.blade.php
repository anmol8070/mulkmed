<p>Dear {{ $user->name }},</p>

<p>
This is to inform you that the payment for your Mulk AI HealthShield Riders postpaid subscription is due by
<strong>{{ \Carbon\Carbon::parse($expiryDate)->format('d M Y') }}</strong>.
</p>



<p>
Kindly arrange the payment at the earliest. If already paid, please ignore this message.
</p>


<p>
Thank you,<br>
<strong>Team Mulk Med.</strong>
</p>
