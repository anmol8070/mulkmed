<!DOCTYPE html>
<html>
<head>
    <title>Redirecting to CCAvenue</title>
</head>
<body onload="document.forms['ccavenueForm'].submit();">

<form method="POST"
      name="ccavenueForm"
      action="{{ $payment_url }}">
</form>

<p>Redirecting to payment gateway, please wait...</p>

</body>
</html>
