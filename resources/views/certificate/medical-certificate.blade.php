<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">

<style>

body{
    margin:0;
    padding:0;
    font-family: DejaVu Sans, sans-serif;
    background:#e0f2fe;
}

.page{
    width:100%;
    padding-top:80px;
}

.card{
    width:430px;
    margin:0 auto;
    background:#ffffff;
    border-radius:20px;
    padding:30px;
    text-align:center;
    border:1px solid #dbeafe;
}

.badge-image{
    width:110px;
    margin-bottom:15px;
}

.title{
    font-size:20px;
    font-weight:bold;
    color:#020617;
    margin:0;
}

.success{
    color:#22c55e;
    font-size:16px;
    margin-top:8px;
}

.divider-box{
    margin-top:22px;
    border-radius:14px;
    border:1px solid #93c5fd;
    padding:15px;
    background:#f1f5f9;
}

.congrats{
    font-size:16px;
    font-weight:bold;
    margin:0 0 6px 0;
    color:#0f172a;
}

.desc{
    font-size:13px;
    color:#64748b;
    line-height:1.5;
    margin:0;
}

</style>
</head>

<body>

<div class="page">

    <div class="card">

        <img class="badge-image" src="{{ base_path('resources/views/certificate/Frame 1116610583.png') }}">

        <p class="title">
            {{ $title ?? 'Mulk Medical Travel Coverage Certificate' }}
        </p>

        <p class="success">
            {{ $status ?? 'Activation Successful' }}
        </p>

        <div class="divider-box">
            <p class="congrats">Congratulations!</p>

            <p class="desc">
                Enjoy peace of mind on your trip. Explore the exclusive benefits included in your plan.
            </p>
        </div>

    </div>

</div>

</body>
</html>