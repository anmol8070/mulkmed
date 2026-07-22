<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Partner Portal Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Partner Portal CSS -->
    <link rel="stylesheet" href="{{ asset('asset/css/partnerportal/login.css') }}">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="login-wrapper">

    <!-- LEFT IMAGE SECTION -->
    <div class="login-left">
        <div class="illustration-wrapper">
            <img src="{{ asset('asset/image/Medical prescription-rafiki.png.png') }}"
                 alt="Partner Login Illustration">
        </div>
    </div>

    <!-- RIGHT LOGIN FORM -->
    <div class="login-right">
        <div class="login-box">

            <h2>Welcome Back!</h2>
            <p>Please login to continue</p>

            <form method="POST" action="{{ route('partner.login.submit') }}" id="loginForm">
                @csrf

                <div class="mb-3">
                    <label class="form-label fw-semibold">Email Address</label>
                    <input type="email"
                           name="email"
                           class="form-control"
                           placeholder="Enter Email Address"
                           required>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Password</label>
                    <input type="password"
                           name="password"
                           class="form-control"
                           placeholder="Enter Password"
                           required>
                </div>

                <!-- LOGIN BUTTON -->
                <button type="submit" class="btn btn-login">
                    Login
                </button>

            </form>

        </div>
    </div>

</div>

<!-- ✅ PARTNER LOGIN JS -->
<script>
    const domainUrl = "{{ url('/v2/') }}/";
</script>

<script src="{{ asset('asset/script/partnerportal/partner-login.js') }}" defer></script>

</body>
</html>
