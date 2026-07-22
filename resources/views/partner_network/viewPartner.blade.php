<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Partner Network</title>
  <style>

  </style>
</head>
<body>
    {{-- <h4 style="text-align: center">Mulk Partners Network</h4> --}}
    {{-- <h5 style="text-align: center">{{ $partner->title }}</h5> --}}
    <h3 style="text-align: center">{{ $partner->headline }}</h3>
    {{-- <img style="margin-left: auto;margin-right: auto; display: block;" src="{{ $imgUrl }}" alt="{{ $partner->title }}" width=""> --}}
    <img 
    src="{{ $imgUrl }}" 
    alt="{{ $partner->title }}" 
    style="margin-left:auto; margin-right:auto; display:block; width:200px; height:200px; object-fit:cover;"
    >

    <h5 style="text-align: center">{{ $partner->hospital_name }}</h5>
    <p><b>Address : </b> {{ $partner->address }}</p>
    <p><b>Website : </b> {{ $partner->website_link }}</p>

  <div class="terms-content">
    <b>About </b>
    {!! $partner->data !!}
  </div>
</body>
</html>
