<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
@php
  $activeLang = strtolower((string) ($lang ?? 'en'));
  $fontNotoSansRegular = 'file:///' . str_replace('\\', '/', storage_path('fonts/NotoSans-Regular.ttf'));
  $fontNotoSansBold = 'file:///' . str_replace('\\', '/', storage_path('fonts/NotoSans-Bold.ttf'));
  $fontNotoArabicRegular = 'file:///' . str_replace('\\', '/', storage_path('fonts/NotoSansArabic-Regular.ttf'));
  $fontNotoArabicBold = 'file:///' . str_replace('\\', '/', storage_path('fonts/NotoSansArabic-Bold.ttf'));
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
    font-family: 'NotoSansArabic';
    src: url("{{ $fontNotoArabicRegular }}") format('truetype');
    font-weight: 400;
    font-style: normal;
  }
  @font-face {
    font-family: 'NotoSansArabic';
    src: url("{{ $fontNotoArabicBold }}") format('truetype');
    font-weight: 700;
    font-style: normal;
  }
  body { font-family: 'NotoSans', DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111; }
  @if(in_array($activeLang, ['ar', 'ur']))
  html, body, body * {
    font-family: 'NotoSansArabic', 'NotoSans', DejaVu Sans, sans-serif !important;
    direction: rtl;
    unicode-bidi: embed;
  }
  @endif
  h2 { font-size: 14px; margin: 14px 0 6px; background: #e8f2fb; padding: 6px; }
  table { width: 100%; border-collapse: collapse; margin-top: 5px; }
  .diagnosis-table td { border: 1.5px solid #6a95f3; padding: 6px; font-size: 12px; }
  .prescription-table th { background: #0080cb; color: #fff; font-size: 12px; padding: 6px; text-align: left; }
  .prescription-table td { border: 1.5px solid #6a95f3; padding: 6px; font-size: 12px; vertical-align: top; }
  .meta { line-height: 1.6; }
  .top-logo { text-align: center; margin: 0 0 12px 0; }
  .logo { height: 70px; }
  .patient-head-table { width: 100%; border-collapse: collapse; margin: 0 0 10px 0; }
  .patient-head-table td { border: none; padding: 0; vertical-align: top; }
  .patient-left { width: 70%; text-align: left; line-height: 1.45; }
  .patient-right { width: 30%; text-align: right; }
  .title-text { color: #0080cb; font-weight: 700; }
  .sign-wrap { margin-top: 14px; width: 100%; }
  .sign-wrap td { border: none; padding: 4px; vertical-align: top; }
  .sign-img { height: 55px; }
  .stamp-img { height: 90px; }
</style>
</head>
<body>
@php
  $labels = is_array($labels ?? null) ? $labels : [];
  $t = function (string $key, string $fallback) use ($labels) {
      return (string) ($labels[$key] ?? $fallback);
  };
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
  $medicineData = json_decode($prescription['medicine'] ?? '{}', true) ?? [];
  $diagnoses = array_values(array_filter((array) ($medicineData['diagnosis'] ?? []), function ($diag) use ($hasValue) {
      return $hasValue($diag['title'] ?? null) || $hasValue($diag['icd'] ?? null) || $hasValue($diag['description'] ?? null);
  }));
  $addMedicine = array_values(array_filter((array) ($medicineData['addMedicine'] ?? []), function ($med) use ($hasValue) {
      return $hasValue($med['drugCode'] ?? null) || $hasValue($med['title'] ?? null) || $hasValue($med['dosage'] ?? null) || $hasValue($med['quantity'] ?? null) || $hasValue($med['notes'] ?? null);
  }));
  $createdDate = $fmtDate($prescription['created_at'] ?? null);
  $dobFormatted = $fmtDate($user['dob'] ?? null);
  $genderText = '';
  if (isset($user['gender']) && $hasValue($user['gender'])) {
      $genderText = ((int) $user['gender'] === 1) ? $t('male', 'Male') : $t('female', 'Female');
  }
  $pimg = is_array($prescriptionPdfImages ?? null) ? $prescriptionPdfImages : [];
  $logoSrc = $pimg['logo'] ?? '';
  $signatureSrc = $pimg['signature'] ?? '';
  $stampSrc = $pimg['stamp'] ?? '';
@endphp

<div class="top-logo">
  @if($hasValue($logoSrc))
    <img src="{{ $logoSrc }}" class="logo" alt="logo">
  @endif
</div>

<table class="patient-head-table">
  <tr>
    <td class="patient-left">
      <div class="title-text">{{ $t('patient_details', 'Patient Details') }}</div>
      @if($hasValue($user['fullname'] ?? null))
        <div>{{ $user['fullname'] }}</div>
      @endif
      @if($hasValue($genderText))
        <div>{{ $genderText }}</div>
      @endif
      @if($hasValue($dobFormatted))
        <div>{{ $t('dob', 'DOB') }}: {{ $dobFormatted }}</div>
      @endif
      @if($hasValue($user['ref_id'] ?? null))
        <div>{{ $t('emirates_id', 'Emirates ID') }}: {{ $user['ref_id'] }}</div>
      @endif
      @if($hasValue($mrnNo ?? null) || $hasValue($prescription['appointment']['appointment_number'] ?? null))
        <div>{{ $t('mrn_no', 'MRN NO') }}: {{ $mrnNo ?? ($prescription['appointment']['appointment_number'] ?? '') }}</div>
      @endif
    </td>
    <td class="patient-right">
      @if($hasValue($createdDate))
        <div class="title-text">{{ $t('date', 'Date') }}: {{ $createdDate }}</div>
      @endif
    </td>
  </tr>
</table>

@if(!empty($diagnoses))
<h2>{{ $t('diagnosis', 'Diagnosis') }}</h2>
<table class="diagnosis-table">
  @foreach($diagnoses as $diag)
    <tr>
      <td>{{ $diag['title'] ?? '' }}</td>
      <td>{{ is_array($diag['description'] ?? null) ? (($diag['description'][0] ?? '-') ) : ($diag['description'] ?? '-') }}</td>
    </tr>
  @endforeach
</table>
@endif

@if(!empty($addMedicine))
<h2>{{ $t('prescription', 'Prescription') }}</h2>
@foreach($addMedicine as $med)
  @php
    $frequency = $med['frequency'] ?? (($med['mealTime'] ?? null) == 1 ? $t('after_lunch', 'After Lunch') : $t('after_dinner', 'After Dinner'));
    $unit = $med['unit'] ?? '';
    $duration = $med['duration'] ?? ($med['dosage'] ?? '');
    $totalQuantity = $med['total_quantity'] ?? ($med['quantity'] ?? '');
    $routeOfAdmin = $med['route_of_admin'] ?? ($med['drugCode'] ?? '');
    $specialInstruction = $med['special_instruction'] ?? ($med['notes'] ?? '');
  @endphp

  <table class="prescription-table">
    <tr>
      <th>{{ $t('drug_name', 'Drug Name') }}</th>
      <th>{{ $t('unit', 'Unit') }}</th>
      <th>{{ $t('frequency', 'Frequency') }}</th>
      <th>{{ $t('duration', 'Duration') }}</th>
      <th>{{ $t('total_quantity', 'Total Quantity') }}</th>
    </tr>
    <tr>
      <td>{{ $med['title'] ?? '' }}</td>
      <td>{{ $unit }}</td>
      <td>{{ $frequency }}</td>
      <td>{{ $duration }}</td>
      <td>{{ $totalQuantity }}</td>
    </tr>
  </table>

  <table class="prescription-table" style="margin-top: 4px;">
    <tr>
      <th>{{ $t('route_of_admin', 'Route Of Admin') }}</th>
      <th>{{ $t('special_instruction', 'Special Instruction') }}</th>
    </tr>
    <tr>
      <td>{{ $routeOfAdmin }}</td>
      <td>{{ $specialInstruction }}</td>
    </tr>
  </table>
@endforeach
@endif

<table class="sign-wrap">
  <tr>
    <td style="width:65%;">
      @if($hasValue($signatureSrc))
        <img src="{{ $signatureSrc }}" class="sign-img" alt="signature"><br>
      @endif
      <strong>{{ $t('doctor_signature', 'Doctor Signature') }}</strong><br>
      <span>{{ $t('doctor_name', 'Doctors Name') }}:</span> {{ $prescription['appointment']['doctor']['name'] ?? $t('na', 'N/A') }}<br>
      <span>{{ $t('doctor_registration_number', 'Doctor Registration Number') }}:</span> {{ $prescription['appointment']['doctor']['dha_registration_number'] ?? $t('na', 'N/A') }}
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
