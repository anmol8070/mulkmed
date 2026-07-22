<!DOCTYPE html>
<html>
<head><title>Meeting Ended</title></head>
<body>
<h2>This meeting has ended.</h2>
<p>It ended at: {{ \Carbon\Carbon::parse($end)->format('h:i A, d M Y') }}</p>
</body>
</html>