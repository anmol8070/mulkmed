<!DOCTYPE html>
<html lang="{{ $lang ?? 'en' }}" dir="{{ in_array(strtolower((string) ($lang ?? 'en')), ['ar', 'ur']) ? 'rtl' : 'ltr' }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Medical Report - 3 Pages</title>
@php
    $activeLang = strtolower((string) ($lang ?? 'en'));
    $fontNotoSansRegular = 'file:///' . str_replace('\\', '/', storage_path('fonts/NotoSans-Regular.ttf'));
    $fontNotoSansBold = 'file:///' . str_replace('\\', '/', storage_path('fonts/NotoSans-Bold.ttf'));
    $fontNotoArabicRegular = 'file:///' . str_replace('\\', '/', storage_path('fonts/NotoSansArabic-Regular.ttf'));
    $fontNotoArabicBold = 'file:///' . str_replace('\\', '/', storage_path('fonts/NotoSansArabic-Bold.ttf'));
    $fontNotoDevaRegular = 'file:///' . str_replace('\\', '/', storage_path('fonts/NotoSansDevanagari-Regular.ttf'));
    $fontNotoDevaBold = 'file:///' . str_replace('\\', '/', storage_path('fonts/NotoSansDevanagari-Bold.ttf'));
    $fontHindRegular = 'file:///' . str_replace('\\', '/', storage_path('fonts/Hind-Regular.ttf'));
    $fontHindBold = 'file:///' . str_replace('\\', '/', storage_path('fonts/Hind-Bold.ttf'));
    $fontNotoUrduRegular = 'file:///' . str_replace('\\', '/', storage_path('fonts/NotoNastaliqUrdu-Regular.ttf'));
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap" rel="stylesheet">

<style>
@font-face{
    font-family:'NotoSans';
    src:url("{{ $fontNotoSansRegular }}") format('truetype');
    font-weight:400;
    font-style:normal;
}
@font-face{
    font-family:'NotoSans';
    src:url("{{ $fontNotoSansBold }}") format('truetype');
    font-weight:700;
    font-style:normal;
}
@font-face{
    font-family:'NotoSansArabic';
    src:url("{{ $fontNotoArabicRegular }}") format('truetype');
    font-weight:400;
    font-style:normal;
}
@font-face{
    font-family:'NotoSansArabic';
    src:url("{{ $fontNotoArabicBold }}") format('truetype');
    font-weight:700;
    font-style:normal;
}
@font-face{
    font-family:'Hind';
@if($fontHindRegularB64 !== '')
    src:url("data:font/truetype;base64,{{ $fontHindRegularB64 }}") format('truetype');
@elseif($fontNotoDevaRegularB64 !== '')
    src:url("data:font/truetype;base64,{{ $fontNotoDevaRegularB64 }}") format('truetype');
@else
    src:url("{{ $fontHindRegular }}") format('truetype');
@endif
    font-weight:400;
    font-style:normal;
}
@font-face{
    font-family:'Hind';
@if($fontHindBoldB64 !== '')
    src:url("data:font/truetype;base64,{{ $fontHindBoldB64 }}") format('truetype');
@elseif($fontNotoDevaBoldB64 !== '')
    src:url("data:font/truetype;base64,{{ $fontNotoDevaBoldB64 }}") format('truetype');
@else
    src:url("{{ $fontHindBold }}") format('truetype');
@endif
    font-weight:700;
    font-style:normal;
}
@font-face{
    font-family:'NotoNastaliqUrdu';
    src:url("{{ $fontNotoUrduRegular }}") format('truetype');
    font-weight:400;
    font-style:normal;
}
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

/* =======================================================
BODY FIXED
======================================================= */
html,body{
    margin:0;
    padding:0;
    background:#ffffff !important;
    font-family:'NotoSans', DejaVu Sans, Arial, Helvetica, sans-serif;
}
@if($activeLang === 'hi')
html, body, body *{
    font-family:'Hind', 'NotoSans', DejaVu Sans, sans-serif !important;
}
@elseif($activeLang === 'ar')
html, body, body *{
    font-family:'NotoSansArabic', 'NotoSans', DejaVu Sans, sans-serif !important;
    direction:rtl;
    unicode-bidi:plaintext;
    text-align:right;
}
@elseif($activeLang === 'ur')
html, body, body *{
    font-family:'NotoNastaliqUrdu', 'NotoSansArabic', 'NotoSans', DejaVu Sans, sans-serif !important;
    direction:rtl;
    unicode-bidi:plaintext;
    text-align:right;
}
@endif
.lang-hi, .lang-hi *{
    font-family:'Hind', 'NotoSans', DejaVu Sans, sans-serif !important;
}
.lang-ar, .lang-ar *{
    font-family:'NotoSansArabic', 'NotoSans', DejaVu Sans, sans-serif !important;
    direction:rtl;
    unicode-bidi:plaintext;
    text-align:right;
}
.lang-ur, .lang-ur *{
    font-family:'NotoNastaliqUrdu', 'NotoSansArabic', 'NotoSans', DejaVu Sans, sans-serif !important;
    direction:rtl;
    unicode-bidi:plaintext;
    text-align:right;
}

/* Preserve readability for mixed LTR values (numbers/units) inside RTL reports */
.lang-ar .vital-val,
.lang-ur .vital-val,
.lang-ar .list-val,
.lang-ur .list-val,
.lang-ar .doc-val,
.lang-ur .doc-val,
.lang-ar .date strong,
.lang-ur .date strong{
    direction:ltr;
    unicode-bidi:isolate;
    display:inline-block;
}

@page{
    size:A4 portrait;
    margin-top:24mm;
    margin-right:8mm;
    margin-bottom:14mm;
    margin-left:8mm;
}

/* Explicitly keep identical margins for all page types in PDF engines */
@page :first{
    margin-top:0mm;
    margin-right:8mm;
    margin-bottom:14mm;
    margin-left:8mm;
}

@page :left{
    margin-top:24mm;
    margin-right:8mm;
    margin-bottom:14mm;
    margin-left:8mm;
}

@page :right{
    margin-top:24mm;
    margin-right:8mm;
    margin-bottom:14mm;
    margin-left:8mm;
}

/* =======================================================
PAGE FIXED
======================================================= */
.page{
    width:100%;
    min-height:auto;
    height:auto;
    background:transparent !important;
    position:relative;
    overflow:visible;
    page-break-after:auto;
}

.page:last-child{
    page-break-after:auto;
}

/* =======================================================
FIRST PAGE HEADER (logo + title above wave)
======================================================= */
.first-page-header{
    height:92px;
    position:relative;
    overflow:hidden;
    background:transparent !important;
    padding:0;
    margin-bottom:6px;
    z-index:30;
}

.header-wave{
    position:absolute;
    left:-8%;
    top:0;
    right:-8%;
    bottom:0;
    width:108%;
    height:100%;
    max-width:none;
    display:block;
    z-index:1;
}

/* Repeat top wave image on every PDF page */
.page-wave-repeat{
    position:fixed;
    left:-10mm;
    right:-10mm;
    top:0;
    height:92px;
    width:calc(100% + 20mm);
    z-index:0;
}

.page-wave-repeat img{
    width:100%;
    height:100%;
    display:block;
}

.first-page-header .logo{
    position:absolute;
    left:28px;
    top:9px;
    height:96px;
    width:auto;
    display:block;
    z-index:31;
}

.first-page-header .title{
    position:absolute;
    width:100%;
    top:33px;
    text-align:center;
    font-size:28px;
    font-weight:700;
    color:#111;
    z-index:31;
}

/* =======================================================
CONTENT
======================================================= */
.content{
    padding:18px 28px 36px;
}

@media print{
    html,body{
        margin:0 !important;
        padding:0 !important;
    }

    .page{
        margin:0 !important;
        padding:0 !important;
    }

    .footer{
        position:fixed !important;
        left:8mm !important;
        right:8mm !important;
        bottom:6mm !important;
        display:block !important;
        visibility:visible !important;
    }

    .page-wave-repeat{
        position:fixed !important;
        left:-10mm !important;
        right:-10mm !important;
        top:0 !important;
        height:92px !important;
        width:calc(100% + 20mm) !important;
        z-index: 0 !important;
    }

    .section,
    .patient-row,
    .vital-table,
    .table,
    .signature-wrap,
    .rx-block{
        page-break-inside:avoid;
        break-inside:avoid-page;
    }
}

/* =======================================================
PATIENT
======================================================= */
.patient-row{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    margin-bottom:12px;
}

/* Table layout: DomPDF does not lay out flex reliably; photo + details must stay side-by-side in PDF */
.patient-left{
    border-collapse:collapse;
    border-spacing:0;
    table-layout:auto;
}

.patient-photo-cell{
    vertical-align:middle;
    padding:0 12px 0 0;
    width:56px;
}

.patient-details-cell{
    vertical-align:middle;
    padding:0;
}

.photo{
    display:block;
    width:56px;
    height:56px;
    border-radius:50%;
    object-fit:cover;
}

.patient-name{
    font-size:16px;
    font-weight:700;
    color:#1a2b3c;
    margin-top:4px;
    margin-bottom:4px;
}

.small{
    font-size:12px;
    font-weight:700;
    color:#1a2b3c;
    line-height:1.45;
}

.date{
    font-size:12px;
    font-weight:400;
    color:#5a7d9a;
    margin-top:0;
    margin-left:auto;
    text-align:right;
    min-width:220px;
    align-self:flex-start;
}

.date strong{
    font-weight:800;
    color:#1a2b3c;
}

/* =======================================================
SECTION
======================================================= */
.section{
    margin-top:6px;
    margin-bottom:10px;
}

.content > .section:first-child{
    margin-top:0;
}

.section-title{
    background:#DFF1FF;
    color:#1A2B3C;
    padding:8px 10px;
    font-family:inherit;
    font-size:14px;
    font-weight:700;
    font-style:normal;
    line-height:100%;
    letter-spacing:0;
    margin-bottom:9px;
    page-break-after:avoid;
    break-after:avoid-page;
}

/* Keep section header with the immediate next content block */
.section-title + .rx-block,
.section-title + .note,
.section-title + ul,
.section-title + .text,
.section-title + table{
    page-break-before:avoid;
    break-before:avoid-page;
}

.grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:4px 20px;
    font-size:12px;
}

/* Vital Details: table layout for DomPDF */
.vital-table{
    width:100%;
    border-collapse:collapse;
    font-size:12px;
    line-height:1.5;
}

.vital-table td{
    width:50%;
    padding:3px 16px 3px 0;
    vertical-align:top;
}

.vital-label{
    font-size:12px;
    font-weight:400;
    color:#607198;
}

.vital-val{
    font-size:12px;
    font-weight:700;
    color:#2B3855;
}

.gray{color:#5a7d9a;font-weight:400;}
.bold{font-weight:700;color:#1a2b3c;}

ul{
    padding-left:18px;
}

li{
    font-size:12px;
    font-weight:600;
    color:#2B3855;
    line-height:1.4;
    margin-bottom:2px;
}

.text{
    font-size:12px;
    font-weight:400;
    color:#333333;
    line-height:1.48;
}

.icd{
    width:100%;
    border-collapse:collapse;
}

.icd td{
    font-size:12px;
    padding:2px 0;
}

.icd td:first-child{
    width:80px;
    font-weight:400;
    color:#5a7d9a;
}

.icd td:last-child{
    font-weight:700;
    color:#1a2b3c;
}

/* =======================================================
TABLE
======================================================= */
.table{
    width:100%;
    border-collapse:collapse;
    margin-bottom:6px;
}

.table th{
    border:1px solid #d6eaf8;
    background:#f5fbff;
    color:#607198;
    font-weight:500;
    font-size:12px;
    padding:5px 3px;
}

.table td{
    border:1px solid #d6eaf8;
    font-size:12px;
    font-weight:500;
    color:#2B3855;
    padding:6px 3px;
    text-align:center;
}

.table td.left{
    text-align:left;
    font-weight:400;
}

.note{
    border:1px solid #d6eaf8;
    border-top:none;
    font-size:12px;
    font-weight:500;
    color:#2B3855;
    padding:5px 7px;
    margin-top:-6px;
    margin-bottom:8px;
}

.note-label{
    color:#607198;
    font-weight:500;
}

/* Keep each prescription item together across page breaks */
.rx-block{
    page-break-inside:avoid;
    break-inside:avoid-page;
}

.list-label{
    font-weight:400;
    color:#5a7d9a;
}

.list-val{
    font-weight:700;
    color:#1a2b3c;
}

/* =======================================================
PAGE 3
======================================================= */
.signature-wrap{
    margin-top:16px;
    width:100%;
}

.sign-left{
    width:65%;
    vertical-align:top;
}

.sign-right{
    width:35%;
    text-align:center;
    vertical-align:top;
}

.signature-img{
    height:65px;
}

.stamp-img{
    height:115px;
    display:block;
    margin:0 auto;
}

.label{
    font-size:12px;
    font-weight:600;
    color:#2B3855;
    margin-top:5px;
}

.seal-label{
    display:block;
    width:100%;
    text-align:center;
    font-size:12px;
    font-weight:600;
    color:#2B3855;
}

.doc-meta{
    margin-top:12px;
    font-size:11px;
    line-height:1.7;
}

.doc-label{
    font-size:12px;
    font-weight:500;
    color:#607198;
}

.doc-val{
    font-weight:600;
    color:#2B3855;
}

/* =======================================================
FOOTER
======================================================= */
.footer{
    position:fixed;
    left:8mm;
    right:8mm;
    bottom:6mm;
    display:block;
    visibility:visible;
    background:#8a96a3;
    color:#ffffff;
    text-align:center;
    font-size:10px;
    line-height:18px;
    padding:0 6px;
    z-index:9999;
}
</style>
</head>

<body class="lang-{{ $lang ?? 'en' }}">
<div class="footer">www.mulkmed.com</div>
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

    $genderText = $t('na', 'N/A');
    if ($patientGender !== null && $patientGender !== '') {
        $genderText = ((int) $patientGender === 1) ? $t('male', 'Male') : $t('female', 'Female');
    }

    /* $vitalDetails from PDF controller: normalized + AI gap-fill + display formatting (units) */
    $bp = $vitalDetails['blood_pressure'] ?? 'N/A';
    $spo2 = $vitalDetails['spo2'] ?? 'N/A';
    $pulse = $vitalDetails['pulse_rate'] ?? 'N/A';
    $temp = $vitalDetails['temperature'] ?? 'N/A';
    $resp = $vitalDetails['breathing_rate'] ?? 'N/A';
    $weight = $vitalDetails['weight'] ?? 'N/A';
    $height = $vitalDetails['height'] ?? 'N/A';
    $vitalPairs = [];
    $vitalMap = [
        ['label' => $t('blood_pressure', 'Blood Pressure'), 'value' => $bp],
        ['label' => $t('spo2', 'SpO2'), 'value' => $spo2],
        ['label' => $t('pulse_rate', 'Pulse Rate'), 'value' => $pulse],
        ['label' => $t('temperature', 'Temperature'), 'value' => $temp],
        ['label' => $t('breathing_rate', 'Breathing Rate'), 'value' => $resp],
        ['label' => $t('weight', 'Weight'), 'value' => $weight],
        ['label' => $t('height', 'Height'), 'value' => $height],
    ];
    foreach ($vitalMap as $vitalItem) {
        if ($hasValue($vitalItem['value'])) {
            $vitalPairs[] = $vitalItem;
        }
    }

    $diagnosisRows = is_array($diagnosis) ? $diagnosis : [];
    $diagnosisRows = array_values(array_filter($diagnosisRows, function ($row) use ($hasValue) {
        return $hasValue($row['name'] ?? null) || $hasValue($row['code'] ?? null) || $hasValue($row['type'] ?? null);
    }));
    $chiefComplaints = array_values(array_filter((array) ($chiefComplaints ?? []), fn($item) => $hasValue($item)));
    $symptoms = array_values(array_filter((array) ($symptoms ?? []), fn($item) => $hasValue($item)));
    $allergies = array_values(array_filter((array) ($allergies ?? []), fn($item) => $hasValue($item)));
    $labOrders = array_values(array_filter((array) ($labOrders ?? []), fn($item) => $hasValue($item)));
    $radiologyOrders = array_values(array_filter((array) ($radiologyOrders ?? []), fn($item) => $hasValue($item)));
    $prescriptions = array_values(array_filter((array) ($prescriptions ?? []), function ($rx) use ($hasValue) {
        return $hasValue($rx['drug_name'] ?? null) || $hasValue($rx['unit'] ?? null) || $hasValue($rx['frequency'] ?? null) || $hasValue($rx['duration'] ?? null) || $hasValue($rx['total_quantity'] ?? null) || $hasValue($rx['route_of_admin'] ?? null) || $hasValue($rx['special_instruction'] ?? null);
    }));
    $consultDate = $fmtDate($consultDate ?? null);
    $followUpDate = $fmtDate($followUpDate ?? null);
    $referralLines = [];
    if (!empty($referral)) {
        $referralLines = array_values(array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", (string) $referral))));
    }

    // Display current doctor's digital signature in signature-wrap.
    $doctorSignatureDisplay = $emrSignatureSrc ?? '';
    if (empty($doctorSignatureDisplay) && !empty($doctor?->digital_signature)) {
        $doctorSignatureDisplay = url('/storage/' . ltrim((string) $doctor->digital_signature, '/'));
    }

    $emrTopWaveDisplay = '';
    $emrTopWavePngPath = public_path('storage/uploads/emr.png');
    if (is_file($emrTopWavePngPath) && is_readable($emrTopWavePngPath)) {
        $emrTopWaveDisplay = 'data:image/png;base64,' . base64_encode((string) file_get_contents($emrTopWavePngPath));
    } else {
        $emrTopWaveDisplay = asset('storage/uploads/emr.png');
    }
@endphp

<div class="page-wave-repeat">
    <img src="{{ $emrTopWaveDisplay }}" alt="">
</div>

<!-- 
PAGE 1 -->
<div class="page">

<div class="first-page-header">
    <img src="{{ $emrLogoSrc ?? '' }}" class="logo">
    <div class="title">{{ $t('title', 'Medical Report') }}</div>
</div>

<div class="content">
<div class="date">
@if($hasValue($consultDate))
{{ $t('consultation_date', 'Consultation Date') }}: <strong>{{ $consultDate }}</strong>
@endif
</div>

<div class="patient-row">
<table class="patient-left" role="presentation" cellspacing="0" cellpadding="0">
<tr>
<td class="patient-photo-cell">
@if(!empty($emrPatientPhotoSrc))
<img src="{{ $emrPatientPhotoSrc }}" class="photo" alt="">
@else
<div class="photo" style="background:#d9eaf7;"></div>
@endif
</td>
<td class="patient-details-cell">
<div class="patient-name">{{ $patientName }}</div>
<div class="small">
@if($hasValue($patientAge ?? null)){{ $patientAge }} {{ $t('years', 'Years') }}@endif
@if($hasValue($genderText)) , {{ $genderText }}@endif
<br>
@if($hasValue($mrnNo ?? null)){{ $t('mrn_no', 'MRN NO') }} : {{ $mrnNo }}@endif
</div>
</td>
</tr>
</table>
</div>

@if(!empty($vitalPairs))
<div class="section vital-details">
<div class="section-title">{{ $t('vital_details', 'Vital Details') }}</div>

<table class="vital-table" cellspacing="0" cellpadding="0">
@foreach(array_chunk($vitalPairs, 2) as $vitalRow)
<tr>
<td><span class="vital-label">{{ $vitalRow[0]['label'] }}:</span> <span class="vital-val">{{ $vitalRow[0]['value'] }}</span></td>
<td>
    @if(isset($vitalRow[1]))
        <span class="vital-label">{{ $vitalRow[1]['label'] }}:</span> <span class="vital-val">{{ $vitalRow[1]['value'] }}</span>
    @endif
</td>
</tr>
@endforeach
</table>
</div>
@endif

@if(!empty($chiefComplaints))
<div class="section">
<div class="section-title">{{ $t('chief_complaints', 'Chief Complaints') }}</div>
<ul>
@foreach($chiefComplaints as $item)
<li>{{ $item }}</li>
@endforeach
</ul>
</div>
@endif

@if(!empty($symptoms))
<div class="section">
<div class="section-title">{{ $t('symptoms', 'Symptoms') }}</div>
<ul>
@foreach($symptoms as $item)
<li>{{ $item }}</li>
@endforeach
</ul>
</div>
@endif

@if(!empty($allergies))
<div class="section">
<div class="section-title">{{ $t('allergies', 'Allergies') }}</div>
<ul>
@foreach($allergies as $item)
<li>{{ $item }}</li>
@endforeach
</ul>
</div>
@endif

@if(!empty($historyText))
<div class="section">
<div class="section-title">{{ $t('history_of_present_illness', 'History of Present Illness') }}</div>
<div class="text">
{{ $historyText }}
</div>
</div>
@endif

@if(!empty($diagnosisRows))
<div class="section">
<div class="section-title">{{ $t('icd_diagnosis', 'ICD Diagnosis') }}</div>

<table class="icd">
@foreach($diagnosisRows as $row)
<tr>
<td>{{ $row['type'] ?? $t('diagnosis', 'Diagnosis') }}:</td>
<td>{{ $row['name'] ?? '-' }}</td>
</tr>
@endforeach
</table>
</div>
@endif

<!-- Continue content in natural flow (no forced new page) -->

@if(!empty($labOrders))
<div class="section">
<div class="section-title">{{ $t('lab_order', 'Lab Order') }}</div>
<ul>
@foreach($labOrders as $item)
<li>{{ $item }}</li>
@endforeach
</ul>
</div>
@endif

@if(!empty($radiologyOrders))
<div class="section">
<div class="section-title">{{ $t('radiology_tests', 'Radiology Tests') }}</div>
<ul>
@foreach($radiologyOrders as $item)
<li>{{ $item }}</li>
@endforeach
</ul>
</div>
@endif

@if(!empty($prescriptions))
<div class="section">
<div class="section-title">{{ $t('prescription', 'Prescription') }}</div>

@foreach($prescriptions as $rx)
<div class="rx-block">
<table class="table">
<tr>
<th>{{ $t('drug_name', 'Drug Name') }}</th>
<th>{{ $t('unit', 'Unit') }}</th>
<th>{{ $t('frequency', 'Frequency') }}</th>
<th>{{ $t('duration', 'Duration') }}</th>
<th>{{ $t('total_quantity', 'Total Quantity') }}</th>
<th>{{ $t('route_of_admin', 'Route of Admin') }}</th>
</tr>
<tr>
<td class="left">{{ $rx['drug_name'] ?? '-' }}</td>
<td>{{ $rx['unit'] ?? '-' }}</td>
<td>{{ $rx['frequency'] ?? '-' }}</td>
<td>{{ $rx['duration'] ?? '-' }}</td>
<td>{{ $rx['total_quantity'] ?? '-' }}</td>
<td>{{ $rx['route_of_admin'] ?? '-' }}</td>
</tr>
</table>
@if(!empty($rx['special_instruction']))
<div class="note"><span class="note-label">{{ $t('special_instruction', 'Special Instruction') }}:</span> {{ $rx['special_instruction'] }}</div>
@endif
</div>
@endforeach

</div>
@endif

@if(!empty($referralLines))
<div class="section">
<div class="section-title">{{ $t('speciality_hospital_referral', 'Speciality / Hospital Referral') }}:</div>
<ul>
@foreach($referralLines as $line)
<li>{{ $line }}</li>
@endforeach
</ul>
</div>
@endif

@if($hasValue($followUpDate))
<div class="section">
<div class="section-title">{{ $t('follow_up', 'Follow Up') }}</div>
<ul>
<li><span class="list-label">{{ $t('follow_up_scheduled', 'Follow-up scheduled') }}:</span> <span class="list-val">{{ $followUpDate }}</span></li>
</ul>
</div>
@endif

<div class="signature-wrap">
<table style="width:100%; border-collapse:collapse;">
<tr>
   
<td class="sign-left">
<img src="{{ $doctorSignatureDisplay }}" class="signature-img">
<h1></h1>
<div class="label">{{ $t('doctor_signature', 'Doctor Signature') }}</div>

<div class="doc-meta">
<span class="doc-label">{{ $t('doctor_name', 'Doctors Name') }}:</span> <span class="doc-val">{{ $doctorName ?? $t('na', 'N/A') }}</span><br>
<span class="doc-label">{{ $t('doctor_registration_number', 'Doctor Registration Number') }}:</span> <span class="doc-val">{{ $doctorRegNo ?? $t('na', 'N/A') }}</span>
</div>
</td>

<td class="sign-right">
<img src="{{ $emrStampSrc ?? '' }}" class="stamp-img">

<div class="label seal-label">{{ $t('doctor_seal', 'Doctor Seal') }}</div>
</td>
</tr>
</table>

</div>

</div>

</div>

</body>
</html>