<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
@php
  $activeLang = strtolower((string) ($lang ?? 'en'));
  $hasValue = function ($value): bool {
      if (is_array($value)) {
          foreach ($value as $item) {
              if ($item !== null && trim((string) $item) !== '' && strtoupper(trim((string) $item)) !== 'N/A') {
                  return true;
              }
          }
          return false;
      }
      return $value !== null && trim((string) $value) !== '' && strtoupper(trim((string) $value)) !== 'N/A';
  };
  $fontNotoSansRegular = 'file:///' . str_replace('\\', '/', storage_path('fonts/NotoSans-Regular.ttf'));
  $fontNotoSansBold = 'file:///' . str_replace('\\', '/', storage_path('fonts/NotoSans-Bold.ttf'));
  $fontNotoDevaRegular = 'file:///' . str_replace('\\', '/', storage_path('fonts/NotoSansDevanagari-Regular.ttf'));
  $fontNotoDevaBold = 'file:///' . str_replace('\\', '/', storage_path('fonts/NotoSansDevanagari-Bold.ttf'));
  $fontHindRegular = 'file:///' . str_replace('\\', '/', storage_path('fonts/Hind-Regular.ttf'));
  $fontHindBold = 'file:///' . str_replace('\\', '/', storage_path('fonts/Hind-Bold.ttf'));
  $fontNotoDevaRegularB64 = '';
  $fontNotoDevaBoldB64 = '';
  $fontNotoDevaRegularPath = storage_path('fonts/NotoSansDevanagari-Regular.ttf');
  $fontNotoDevaBoldPath = storage_path('fonts/NotoSansDevanagari-Bold.ttf');
  if (is_readable($fontNotoDevaRegularPath)) {
      $raw = @file_get_contents($fontNotoDevaRegularPath);
      if ($raw !== false) {
          $fontNotoDevaRegularB64 = base64_encode($raw);
      }
  }
  if (is_readable($fontNotoDevaBoldPath)) {
      $raw = @file_get_contents($fontNotoDevaBoldPath);
      if ($raw !== false) {
          $fontNotoDevaBoldB64 = base64_encode($raw);
      }
  }
  $fontHindRegularB64 = '';
  $fontHindBoldB64 = '';
  $fontHindRegularPath = storage_path('fonts/Hind-Regular.ttf');
  $fontHindBoldPath = storage_path('fonts/Hind-Bold.ttf');
  if (is_readable($fontHindRegularPath)) {
      $raw = @file_get_contents($fontHindRegularPath);
      if ($raw !== false) {
          $fontHindRegularB64 = base64_encode($raw);
      }
  }
  if (is_readable($fontHindBoldPath)) {
      $raw = @file_get_contents($fontHindBoldPath);
      if ($raw !== false) {
          $fontHindBoldB64 = base64_encode($raw);
      }
  }
@endphp
<style>
  @font-face {
    font-family: 'NotoSans';
    src: url("{{ $fontNotoSansRegular }}") format('truetype');
    font-weight: 400;
    font-style: normal;
  }
  @font-face {
    font-family: 'NotoSans';
    src: url("{{ $fontNotoSansBold }}") format('truetype');
    font-weight: 700;
    font-style: normal;
  }
  @font-face {
    font-family: 'Hind';
    @if($fontHindRegularB64 !== '')
    src: url("data:font/truetype;base64,{{ $fontHindRegularB64 }}") format('truetype');
    @elseif($fontNotoDevaRegularB64 !== '')
    src: url("data:font/truetype;base64,{{ $fontNotoDevaRegularB64 }}") format('truetype');
    @else
    src: url("{{ $fontHindRegular }}") format('truetype');
    @endif
    font-weight: 400;
    font-style: normal;
  }
  @font-face {
    font-family: 'Hind';
    @if($fontHindBoldB64 !== '')
    src: url("data:font/truetype;base64,{{ $fontHindBoldB64 }}") format('truetype');
    @elseif($fontNotoDevaBoldB64 !== '')
    src: url("data:font/truetype;base64,{{ $fontNotoDevaBoldB64 }}") format('truetype');
    @else
    src: url("{{ $fontHindBold }}") format('truetype');
    @endif
    font-weight: 700;
    font-style: normal;
  }
  @font-face {
    font-family: 'NotoDeva';
    src: url("{{ $fontNotoDevaRegular }}") format('truetype');
    font-weight: 400;
    font-style: normal;
  }
  @font-face {
    font-family: 'NotoDeva';
    src: url("{{ $fontNotoDevaBold }}") format('truetype');
    font-weight: 700;
    font-style: normal;
  }

  body { font-family: 'NotoSans', DejaVu Sans, Arial, sans-serif; font-size: 15px; color: #111; margin: 0; padding: 0; }
  @page { margin-top: 24mm; margin-right: 8mm; margin-bottom: 22mm; margin-left: 8mm; margin-header: 0mm; margin-footer: 4mm; header: emrPageHeader; footer: emrPageFooter; }
  @page :first { margin-top: 0mm; margin-right: 8mm; margin-bottom: 22mm; margin-left: 8mm; margin-header: 0mm; }
  @if($activeLang === 'hi')
  html, body, body * { font-family: 'NotoDeva', 'Hind', 'NotoSans', DejaVu Sans, sans-serif !important; }
  @endif
  h2 {
  background: #DFF1FF;
  color: #1A2B3C;
  padding: 9px 12px;
  font-size: 16px;
  font-weight: 700 !important;   /* force bold */
  font-family: Arial, sans-serif !important;
}
@font-face {
  font-family: 'NotoSans';
  src: url("...NotoSans-Regular.ttf");
  font-weight: 400;
}

@font-face {
  font-family: 'NotoSans';
  src: url("...NotoSans-Bold.ttf");
  font-weight: 700;
}
  .meta { margin-bottom: 8px; line-height: 1.6; }
  .page-wave-repeat { display: none; }
  .page-wave-repeat img { width: 100%; height: 100%; display: block; }
  .page-wave-header { width: 230mm; height: 92px; margin: 0 16mm 0 -16mm; padding: 0; overflow: visible; }
  .page-wave-header img { width: 230mm; height: 92px; display: block; max-width: none; object-fit: cover; }
  .first-page-header{ height:96px; position:relative; overflow:hidden; background:transparent; margin:0 -8mm 20px -8mm; z-index:30; }
  .header-wave-img { position:absolute; left:-8%; top:0; right:-8%; width:108%; height:100%; z-index:1; }
  .header-overlay { position: absolute; top: 0; left: 0; right: 0; height: 92px; z-index: 31; }
  .logo { position:absolute; left:28px; top:9px; height:96px; width:auto; z-index:31; }
  .report-title { position:absolute; width:100%; top:28px; text-align:center; font-size:36px; font-weight:700; line-height:1.15; color:#111; z-index:31; margin:0; }
  .date { position:absolute; right:24px; bottom:8px; padding-right:24px; font-size:15px; font-weight:700; color:#5a7d9a; text-align:right; min-width:240px; z-index:31; }
  .date strong{ font-weight:700; color:#1a2b3c; }
  .patient-top { margin: 14px 0 12px; border-collapse: collapse; border-spacing: 0; }
  .patient-top td { border: none; padding: 0; vertical-align: middle; }
  .profile-photo { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; border: 1px solid #d4dbe3; margin-right: 10px; }
  .photo-placeholder { width: 56px; height: 56px; border-radius: 50%; border: 1px solid #d4dbe3; background: #d9eaf7; margin-right: 10px; }
  .patient-name { font-size: 22px; font-weight: 700; color: #1a2b3c; margin: 4px 0; }
  .small { font-size: 15px; font-weight: 700; color: #1a2b3c; line-height: 1.5; }
  .lang-hi .report-title { top: 18px; font-family: 'Hind', 'NotoDeva', 'NotoSans', DejaVu Sans, sans-serif !important; line-height: 1; }
  .vital-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 4px;
  }
  .vital-table td { border: none !important; width:50%; padding:3px 16px 3px 0; vertical-align:top; }
  .vital-label { font-size:15px; font-weight:600; color:#607198; }
  .vital-val { font-size:15px; font-weight:700; color:#2B3855; }
  .muted { color:#5a7d9a; }
  ul { margin:4px 0 0 18px; padding-left:18px; }
  li { margin:3px 0; font-size:15px; font-weight:600; color:#2B3855; line-height:1.5; }
  table { width: 100%; border-collapse: collapse; margin-top: 4px; }
  th, td { border: 1px solid #d6eaf8; padding: 7px; vertical-align: top; font-size:15px; }
  th { background: #f5fbff; color:#607198; font-weight:700; text-align: left; }
  .rx-block { margin-bottom: 12px; }
  .sign-wrap { margin-top: 16px; width: 100%; }
  .sign-wrap { page-break-inside: avoid; break-inside: avoid-page; }
  .sign-wrap td { border: none; padding: 4px; vertical-align: top; }
  .sign-img { height: 65px; }
  .stamp-img { height: 115px; display:block; margin:0 auto; }
  .page-footer-bar {
    display:block;
    width:100%;
    background:#8a96a3;
    color:#ffffff;
    text-align:center;
    font-size:10px;
    line-height:18px;
    padding:0 6px;
  }
  /* .page-break {
    page-break-before: always;
    } */
    .bold{
      font-weight: 700 !important;
    }
</style>
</head>
<body class="lang-{{ $activeLang }}">
@php
  $labels = is_array($labels ?? null) ? $labels : [];
  $t = function (string $key, string $fallback) use ($labels) {
      return (string) ($labels[$key] ?? $fallback);
  };
  $fmtDate = function ($value) use ($hasValue) {
      if (!$hasValue($value)) {
          return '';
      }
      try {
          return \Carbon\Carbon::parse($value)->format('d-m-Y');
      } catch (\Throwable $e) {
          return (string) $value;
      }
  };
  $consultDateFormatted = $fmtDate($consultDate ?? null);
  $logoSrc = $emrLogoSrc ?? '';
  $signatureSrc = $emrSignatureSrc ?? '';
  $stampSrc = $emrStampSrc ?? '';
  $patientPhotoSrc = $emrPatientPhotoSrc ?? '';
  $emrTopWaveDisplay = '';
  $emrTopWavePngPath = public_path('storage/uploads/emr.png');
  if (is_file($emrTopWavePngPath) && is_readable($emrTopWavePngPath)) {
      $rawTopWave = @file_get_contents($emrTopWavePngPath);
      if ($rawTopWave !== false) {
          $emrTopWaveDisplay = 'data:image/png;base64,' . base64_encode($rawTopWave);
      }
  }
  if (!$hasValue($emrTopWaveDisplay)) {
      $emrTopWaveDisplay = asset('storage/uploads/emr.png');
  }
  $vitalPairs = [];
  $vitalMap = [
      ['label' => $t('blood_pressure', 'Blood Pressure'), 'value' => $vitalDetails['blood_pressure'] ?? null],
      ['label' => $t('spo2', 'SpO2'), 'value' => $vitalDetails['spo2'] ?? null],
      ['label' => $t('pulse_rate', 'Pulse Rate'), 'value' => $vitalDetails['pulse_rate'] ?? null],
      ['label' => $t('temperature', 'Temperature'), 'value' => $vitalDetails['temperature'] ?? null],
      ['label' => $t('breathing_rate', 'Breathing Rate'), 'value' => $vitalDetails['breathing_rate'] ?? null],
      ['label' => $t('weight', 'Weight'), 'value' => $vitalDetails['weight'] ?? null],
  ];
  foreach ($vitalMap as $vitalItem) {
      if ($hasValue($vitalItem['value'])) {
          $vitalPairs[] = $vitalItem;
      }
  }
  $hasVitals = !empty($vitalPairs);
  $chiefComplaints = array_values(array_filter((array) ($chiefComplaints ?? []), fn($item) => $hasValue($item)));
  $symptoms = array_values(array_filter((array) ($symptoms ?? []), fn($item) => $hasValue($item)));
  $allergies = array_values(array_filter((array) ($allergies ?? []), fn($item) => $hasValue($item)));
  $diagnosis = array_values(array_filter((array) ($diagnosis ?? []), function ($row) use ($hasValue) {
      return $hasValue($row['code'] ?? null) || $hasValue($row['name'] ?? null) || $hasValue($row['type'] ?? null);
  }));
  $prescriptions = array_values(array_filter((array) ($prescriptions ?? []), function ($rx) use ($hasValue) {
      return $hasValue($rx['drug_name'] ?? null) || $hasValue($rx['frequency'] ?? null) || $hasValue($rx['duration'] ?? null) || $hasValue($rx['total_quantity'] ?? null);
  }));
@endphp

<htmlpageheader name="emrPageHeader">
  <div class="page-wave-header">
    @if($hasValue($emrTopWaveDisplay))
      <img src="{{ $emrTopWaveDisplay }}" alt="wave">
    @endif
  </div>
</htmlpageheader>
<sethtmlpageheader name="emrPageHeader" value="on" show-this-page="0" />

<htmlpagefooter name="emrPageFooter"> 
  <div class="page-footer-bar">www.mulkmed.com</div>
</htmlpagefooter>
<sethtmlpagefooter name="emrPageFooter" value="on" show-this-page="1" />

<div class="page-wave-repeat">
  @if($hasValue($emrTopWaveDisplay))
    <img src="{{ $emrTopWaveDisplay }}" alt="wave">
  @endif
</div>

<div class="first-page-header">
  <div class="header-overlay" @if($hasValue($emrTopWaveDisplay)) style="background-image:url('{{ $emrTopWaveDisplay }}'); background-repeat:no-repeat; background-size:100% 100%; background-position:center top;" @endif>
    @if($hasValue($logoSrc))
      <img src="{{ $logoSrc }}" class="logo" alt="logo">
    @endif
    <div class="report-title">{{ $t('title', 'Medical Report') }}</div>
    <div class="date">
      @if($hasValue($consultDateFormatted))
        {{ $t('consultation_date', 'Consultation Date') }}: <strong>{{ $consultDateFormatted }}</strong>
      @endif
    </div>
  </div>
</div>
<table class="patient-top">
  <tr>
    <td style="width:68px;">
      @if($hasValue($patientPhotoSrc))
        <img src="{{ $patientPhotoSrc }}" class="profile-photo" alt="patient photo">
      @else
        <div class="photo-placeholder"></div>
      @endif
    </td>
    <td>
      @if($hasValue($patientName ?? null))
        <div class="patient-name">{{ $patientName }}</div>
      @endif
      <div class="small">
      @if($hasValue($patientAge ?? null))
        {{ $patientAge }} {{ $t('years', 'Years') }}
      @endif
      @if($hasValue($patientGender ?? null))
        , {{ $patientGender }}
      @endif
      @if($hasValue($mrnNo ?? null))
        <br>{{ $t('mrn_no', 'MRN NO') }} : {{ $mrnNo }}
      @endif
      </div>
    </td>
  </tr>
</table>
<div class="meta">
 
</div>

@if($hasVitals)
<h2 class="bold">{{ $t('vital_details', 'Vital Details') }}</h2>
<table class="vital-table">
  @foreach(array_chunk($vitalPairs, 2) as $vitalRow)
    <tr>
      <td>
        @if(isset($vitalRow[0]))
          <span class="vital-label">{{ $vitalRow[0]['label'] }}:</span>
          <span class="vital-val">{{ $vitalRow[0]['value'] }}</span>
        @endif
      </td>
      <td>
        @if(isset($vitalRow[1]))
          <span class="vital-label">{{ $vitalRow[1]['label'] }}:</span>
          <span class="vital-val">{{ $vitalRow[1]['value'] }}</span>
        @endif
      </td>
    </tr>
  @endforeach
</table>
@endif

@if(!empty($chiefComplaints))
<h2>{{ $t('chief_complaints', 'Chief Complaints') }}</h2>
<ul>
  @foreach($chiefComplaints as $item)
    <li>{{ $item }}</li>
  @endforeach
</ul>
@endif

@if(!empty($symptoms))
<h2>{{ $t('symptoms', 'Symptoms') }}</h2>
<ul>
  @foreach($symptoms as $item)
    <li>{{ $item }}</li>
  @endforeach
</ul>
@endif

@if(!empty($allergies))
<h2>{{ $t('allergies', 'Allergies') }}</h2>
<ul>
  @foreach($allergies as $item)
    <li>{{ $item }}</li>
  @endforeach
</ul>
@endif

@if(!empty($historyText))
<h2>{{ $t('history_of_present_illness', 'History of Present Illness') }}</h2>
<div>{{ $historyText }}</div>
@endif


@if(!empty($diagnosis))
<h2>{{ $t('icd_diagnosis', 'ICD Diagnosis') }}</h2>
<table>
  <tr>
    <th>{{ $t('diagnosis', 'Diagnosis') }}</th>
    
    <th>Name</th>
  </tr>
  @foreach($diagnosis as $row)
    <tr>
      <td>{{ $row['type'] ?? $t('diagnosis', 'Diagnosis') }}</td>
     
      <td>{{ $row['name'] ?? '-' }}</td>
    </tr>
  @endforeach
</table>
@endif

@if(!empty($labOrders))
<h2>{{ $t('lab_order', 'Lab Orders') }}</h2>

<ul>
  @foreach($labOrders as $order)
    <li>{{ $order ?? '-' }}</li>
  @endforeach
</ul>
@endif

@if(!empty($radiologyOrders))
<h2>{{ $t('radiology_tests', 'Radiology Orders') }}</h2>

<ul>
  @foreach($radiologyOrders as $order)
    <li>{{ $order ?? '-' }}</li>
  @endforeach
</ul>
@endif


@if(!empty($prescriptions))

<h2>{{ $t('prescription', 'Prescription') }}</h2>
@foreach($prescriptions as $rx)
<div class="rx-block">
<table>
  <tr>
    <th>{{ $t('drug_name', 'Drug Name') }}</th>
    <th>{{ $t('unit', 'Unit') }}</th>
    <th>{{ $t('frequency', 'Frequency') }}</th>
    <th>{{ $t('duration', 'Duration') }}</th>
    <th>{{ $t('total_quantity', 'Total Quantity') }}</th>
    <th>{{ $t('route_of_admin', 'Route Of Admin') }}</th>
  </tr>
  <tr>
    <td>{{ $rx['drug_name'] ?? '-' }}</td>
    <td>{{ $rx['unit'] ?? '-' }}</td>
    <td>{{ $rx['frequency'] ?? '-' }}</td>
    <td>{{ $rx['duration'] ?? '-' }}</td>
    <td>{{ $rx['total_quantity'] ?? '-' }}</td>
    <td>{{ $rx['route_of_admin'] ?? '-' }}</td>
  </tr>
  @if(!empty($rx['special_instruction']))
  <tr>
    <td colspan="6"><strong>{{ $t('special_instruction', 'Special Instruction') }}:</strong> {{ $rx['special_instruction'] }}</td>
  </tr>
  @endif
</table>
</div>
@endforeach
@endif

@if(!empty($referral))
<h2>{{ $t('speciality_hospital_referral', 'Speciality / Hospital reference') }}</h2>
<div>{{$referral}}</div>

@endif

@if(!empty($followUpDate))
    <h2>{{ $t('follow_up', 'Follow Up Date') }}</h2>
    <div>{{ \Carbon\Carbon::parse($followUpDate)->format('d M Y') }}</div>
@endif








<table class="sign-wrap">
  <tr>
    <td style="width:65%;">
      @if($hasValue($signatureSrc))
        <img src="{{ $signatureSrc }}" class="sign-img" alt="signature"><br>
      @endif
      <strong>{{ $t('doctor_signature', 'Doctor Signature') }}</strong><br>
      <span class="muted">{{ $t('doctor_name', 'Doctors Name') }}:</span> {{ $doctorName ?? $t('na', 'N/A') }}<br>
      <span class="muted">{{ $t('doctor_registration_number', 'Doctor Registration Number') }}:</span> {{ $doctorRegNo ?? $t('na', 'N/A') }}
    </td>
    <td style="width:35%; text-align:right;">
      @if($hasValue($stampSrc))
        <img src="{{ $stampSrc }}" class="stamp-img" alt="stamp"><br>
      @endif
      <strong>{{ $t('doctor_seal', 'Doctor Seal') }}</strong>
    </td>
  </tr>
</table>
</body>
</html>
