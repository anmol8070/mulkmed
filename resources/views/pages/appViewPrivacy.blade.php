<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Privacy Policy</title>
  <style>

  </style>
</head>
<body>
  <h1 style="color: #085294">
      @switch($lang)
          @case('hi')
              उपयोग की शर्तें
              @break

          @case('fr')
              Conditions d’utilisation
              @break

          @case('ar')
              شروط الاستخدام
              @break

          @case('ur')
              استعمال کی شرائط
              @break

          @default
              Terms of Use
      @endswitch
  </h1>
  <div class="terms-content" style="margin: 8px">

    {!! $data !!}
  </div>
</body>
</html>
