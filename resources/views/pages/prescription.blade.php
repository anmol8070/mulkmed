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
  $fontNotoDevaRegular = 'file:///' . str_replace('\\', '/', storage_path('fonts/NotoSansDevanagari-Regular.ttf'));
  $fontNotoDevaBold = 'file:///' . str_replace('\\', '/', storage_path('fonts/NotoSansDevanagari-Bold.ttf'));
  $fontNotoUrduRegular = 'file:///' . str_replace('\\', '/', storage_path('fonts/NotoNastaliqUrdu-Regular.ttf'));
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
  font-family: 'NotoNastaliqUrdu';
  src: url("{{ $fontNotoUrduRegular }}") format('truetype');
  font-weight: 400;
  font-style: normal;
}
@page {
  size: A4;
  margin: 20px;
}

body {
  font-family: 'NotoSans', DejaVu Sans, Arial, sans-serif;
  font-size: 13px;
  color: #0f172a;
}
@if($activeLang === 'hi')
html, body, body * {
  font-family: 'Hind', 'NotoSans', DejaVu Sans, sans-serif !important;
}
@elseif($activeLang === 'ar')
html, body, body * {
  font-family: 'NotoSansArabic', 'NotoSans', DejaVu Sans, sans-serif !important;
  direction: rtl;
  unicode-bidi: embed;
}
@elseif($activeLang === 'ur')
html, body, body * {
  font-family: 'NotoSansArabic', 'NotoSans', DejaVu Sans, sans-serif !important;
  direction: rtl;
  unicode-bidi: embed;
}
@endif
.lang-hi, .lang-hi * {
  font-family: 'Hind', 'NotoSans', DejaVu Sans, sans-serif !important;
}
.lang-ar, .lang-ar * {
  font-family: 'NotoSansArabic', 'NotoSans', DejaVu Sans, sans-serif !important;
  direction: rtl;
  unicode-bidi: embed;
}
.lang-ur, .lang-ur * {
  font-family: 'NotoSansArabic', 'NotoSans', DejaVu Sans, sans-serif !important;
  direction: rtl;
  unicode-bidi: embed;
}

/* MAIN CONTAINER */
.prescription {
  position: relative;
  background: #ffffff;
  padding: 10px;
}

/* WATERMARK */
.watermark {
  position: absolute;
  top: 45%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 420px;
  opacity: 0.06;
  z-index: 0;
}

/* HEADER */
.header {
  text-align: center;
  margin-bottom: 10px;
}
.header img {
  height: 80px;
}

/* TITLE COLOR */
.title-text {
  color: #0080cb;
  font-weight: bold;
}

/* PATIENT INFO */
.patient-info {
  width: 100%;
  margin-bottom: 10px;
  font-size: 13px;
}
.patient-left {
  float: left;
  width: 70%;
}
.patient-right {
  float: right;
  width: 30%;
  text-align: right;
}
.clear {
  clear: both;
}

/* SECTION TITLES */
h2 {
  font-size: 14px;
  margin: 8px 0 4px;
  color: #000;
}

/* TABLE COMMON */
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 5px;
}

/* DIAGNOSIS */
.diagnosis-table td {
  border: 1.5px solid #6a95f3;
  padding: 6px;
  font-size: 12px;
}

/* PRESCRIPTION */
.prescription-table th {
  background: #0080cb;
  color: #fff;
  font-size: 12px;
  padding: 6px;
}
.prescription-table td {
  border: 1.5px solid #6a95f3;
  padding: 6px;
  font-size: 12px;
}

/* NOTES */
.notes {
  background: #e6f2fb;
  padding: 10px;
  border-radius: 6px;
  font-size: 12px;
  color: #334155;
  margin-top: 5px;
}

/* SIGNATURE (aligned with pages/emr_report.blade.php) */
.signature-wrap {
  margin-top: 20px;
  width: 100%;
}

.sign-left {
  width: 65%;
  vertical-align: top;
}

.sign-right {
  width: 35%;
  text-align: center;
  vertical-align: top;
}

.signature-img {
  height: 65px;
  display: block;
}

.stamp-img {
  height: 115px;
  display: block;
  margin: 0 auto;
}

.label {
  font-size: 12px;
  font-weight: 600;
  color: #2b3855;
  margin-top: 5px;
}

.seal-label {
  display: block;
  width: 100%;
  text-align: center;
  font-size: 12px;
  font-weight: 600;
  color: #2b3855;
}

.doc-meta {
  margin-top: 12px;
  font-size: 11px;
  line-height: 1.7;
}

.doc-label {
  font-size: 12px;
  font-weight: 500;
  color: #607198;
}

.doc-val {
  font-weight: 600;
  color: #2b3855;
}
.medicine-block.block-dark thead th {
  background-color: #0080cb;  /* dark blue */
  color: #fff;
}

.medicine-block.block-light thead th {
  background-color: #cfe8ff;  /* light blue */
  color: #000;
}
.prescription-table th {
  text-align: center;
  vertical-align: middle;
}

.prescription-table td {
  text-align: center;
  vertical-align: middle;
}
.instruction-row td {
  border: none !important;
  text-align: left !important;
  padding-top: 8px;
}
/* Keep Drug Name left aligned only */
/* .prescription-table td:first-child {
  text-align: left;
} */
</style>
</head>

<body class="lang-{{ $lang ?? 'en' }}">

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
  $pimg = is_array($prescriptionPdfImages ?? null) ? $prescriptionPdfImages : [];
  $medicineData = json_decode($prescription['medicine'] ?? '{}', true) ?? [];
  $diagnoses = array_values(array_filter((array) ($medicineData['diagnosis'] ?? []), function ($diag) use ($hasValue) {
      return $hasValue($diag['title'] ?? null) || $hasValue($diag['icd'] ?? null) || $hasValue($diag['description'] ?? null);
  }));
  $addMedicine = array_values(array_filter((array) ($medicineData['addMedicine'] ?? []), function ($med) use ($hasValue) {
      return $hasValue($med['drugCode'] ?? null) || $hasValue($med['title'] ?? null) || $hasValue($med['dosage'] ?? null) || $hasValue($med['quantity'] ?? null) || $hasValue($med['notes'] ?? null);
  }));
  $dobFormatted = $fmtDate($user['dob'] ?? null);
  $createdDate = $fmtDate($prescription['created_at'] ?? null);

  $doctorSignatureDisplay = $pimg['signature'] ?? '';
  if ($doctorSignatureDisplay === '' || $doctorSignatureDisplay === null) {
      $sig = $prescription['appointment']['doctor']['digital_signature'] ?? null;
      if (!empty($sig)) {
          $doctorSignatureDisplay = url('/storage/' . ltrim((string) $sig, '/\\'));
      }
  }
  if ($doctorSignatureDisplay === '' || $doctorSignatureDisplay === null) {
      $doctorSignatureDisplay = url('/images/no-signature.png');
  }

  $stampSrc = $pimg['stamp'] ?? url('/storage/uploads/mulkmed_prescription_stamp.png');
@endphp

<div class="prescription">

<img src="{{ $pimg['watermark'] ?? url('/storage/uploads/mulkmed_presciption_watermark.png') }}" class="watermark">

<!-- HEADER -->
<div class="header">
  <img src="{{ $pimg['logo'] ?? url('/storage/uploads/prescription_logo.png') }}">
</div>

<!-- PATIENT INFO -->
<div class="patient-info">
  <div class="patient-left">
    @if($hasValue($user['fullname'] ?? null) || $hasValue($user['gender'] ?? null) || $hasValue($dobFormatted) || $hasValue($user['ref_id'] ?? null) || $hasValue($mrnNo ?? null) || $hasValue($prescription['appointment']['appointment_number'] ?? null))
    <div class="title-text" style="margin-top:5px;">{{ $t('patient_details', 'Patient Details') }}</div>

    @if($hasValue($user['fullname'] ?? null))
      {{ $user['fullname'] }} <br>
    @endif
    @if(isset($user['gender']) && $hasValue($user['gender']))
      {{ $user['gender']==1?$t('male', 'Male'):$t('female', 'Female') }} <br>
    @endif
    @if($hasValue($dobFormatted))
      {{ $t('dob', 'DOB') }}: {{ $dobFormatted }} <br>
    @endif
    @if($hasValue($user['ref_id'] ?? null))
      {{ $t('emirates_id', 'Emirates ID') }}: {{ $user['ref_id'] }} <br>
    @endif
    @if($hasValue($mrnNo ?? null) || $hasValue($prescription['appointment']['appointment_number'] ?? null))
      {{ $t('mrn_no', 'MRN No') }}: {{ $mrnNo ?? ($prescription['appointment']['appointment_number'] ?? '') }}
    @endif
    @endif
  </div>

  <div class="patient-right">
    @if($hasValue($createdDate))
      <div class="title-text">
        {{ $t('date', 'Date') }}: {{ $createdDate }}
      </div>
    @endif
  </div>
</div>

<div class="clear"></div>

@if(!empty($diagnoses))
<!-- DIAGNOSIS -->
<h2>{{ $t('diagnosis', 'Diagnosis') }}</h2>

<table class="diagnosis-table">
<tbody>
@if(count($diagnoses))
@foreach($diagnoses as $diag)
<tr>
  <td width="35%">{{ ucfirst($diag['title'] ?? '') }}</td>
  <td>{{ is_array($diag['description']) ? $diag['description'][0] : $diag['description'] }}</td>
</tr>
@endforeach
@endif
</tbody>
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

  $blockClass = $loop->index % 2 == 0 ? 'block-dark' : 'block-light';
@endphp

<div class="medicine-block {{ $blockClass }}">

<table class="prescription-table">
<thead>
<tr>
  <th>{{ $t('drug_name', 'Drug Name') }}</th>
  <th>{{ $t('unit', 'Unit') }}</th>
  <th>{{ $t('frequency', 'Frequency') }}</th>
  <th>{{ $t('duration', 'Duration') }}</th>
  <th>{{ $t('total_quantity', 'Total Quantity') }}</th>
   <th>{{ $t('route_of_admin', 'Route Of Admin') }}</th>
</tr>
</thead>
<tbody>
<tr>
  <td>{{ $med['title'] ?? '' }}</td>
  <td>{{ $unit }}</td>
  <td>{{ $frequency }}</td>
  <td>{{ $duration }}</td>
  <td>{{ $totalQuantity }}</td>
 <td>{{ $routeOfAdmin }}</td>
</tr>

       <tr class="instruction-row">
  <td colspan="6">
    <strong>{{ $t('special_instruction', 'Special Instruction') }}:</strong>
    {{ $specialInstruction }}
  </td>
</tr>
</tbody>
</table>




</div>

@endforeach

@endif

<!-- NOTES -->
@if(!empty($medicineData['notes']))
<div class="title-text" style="margin-top:8px;">{{ $t('notes', 'Notes') }}</div>
<div class="notes">
  {{ $medicineData['notes'] }}
</div>
@endif

<!-- DOCTOR SIGNATURE & DETAILS (same structure as EMR report) -->
<div class="signature-wrap">
<table style="width:100%; border-collapse:collapse;">
<tr>
<td class="sign-left">
<img src="{{ $doctorSignatureDisplay }}" class="signature-img" alt="">
<div class="label">{{ $t('doctor_signature', 'Doctor Signature') }}</div>

<div class="doc-meta">
<span class="doc-label">{{ $t('doctor_name', 'Doctors Name') }}:</span> <span class="doc-val">{{ $prescription['appointment']['doctor']['name'] ?? $t('na', 'N/A') }}</span><br>
<span class="doc-label">{{ $t('doctor_registration_number', 'Doctor Registration Number') }}:</span> <span class="doc-val">{{ $prescription['appointment']['doctor']['dha_registration_number'] ?? $t('na', 'N/A') }}</span>
</div>
</td>

<td class="sign-right">
<img src="{{ $stampSrc }}" class="stamp-img" alt="">

<div class="label seal-label">{{ $t('doctor_seal', 'Doctor Seal') }}</div>
</td>
</tr>
</table>
</div>

</div>

</body>
</html>