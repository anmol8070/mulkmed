<!DOCTYPE html>
<html>
<head><title>Meeting Not Started</title></head>
<body>
<h2>This meeting has not started yet.</h2>
<p>Start time: {{ \Carbon\Carbon::parse($start)->format('h:i A, d M Y') }}</p>
</body>
</html>