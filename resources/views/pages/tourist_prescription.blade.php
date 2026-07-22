<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Prescription Layout</title>
<style>
  body {
  
    font-family: Arial, sans-serif;
    display: flex;
    justify-content: center;

  }


  /* Header */
  .header {
    text-align: center;
    padding-bottom: 10px;
    margin-bottom: 20px;
  }
  .header h1 {
    margin: 0;
    font-size: 28px;
    letter-spacing: 2px;
  }
  .header h2 {
    margin: 5px 0;
    font-size: 16px;
    color: #555;
  }

  /* Info Section */
  .patient-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
    font-size: 16px;
  }
  .patient-info div {
    line-height: 1.6;
  }

  /* Common Table Style */
  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    margin-bottom: 30px;
  }
  table th, table td {
    border: 1px solid #000;
    padding: 8px 10px;
    vertical-align: top;
  }
  table th {
    background: #eaeaea;
    font-weight: bold;
    text-align: center;
  }

  /* Diagnosis Table */
  .diagnosis-table th:nth-child(1) { width: 180px; }
  .diagnosis-table th:nth-child(2) { width: auto; }

  /* Prescription Table */
  .prescription-table th:nth-child(1) { width: 160px; }
  .prescription-table th:nth-child(2) { width: 250px; }
  .prescription-table th:nth-child(3) { width: 160px; }
  .prescription-table th:nth-child(4) { width: 90px; text-align: center; }
  .prescription-table th:nth-child(5) { width: 60px; text-align: center; }
  .prescription-table th:nth-child(6) { width: 100px; }

  .prescription-table td:nth-child(4),
  .prescription-table td:nth-child(5) {
    text-align: center;
  }

  /* Notes */
  .notes {
     border-radius: 8px;
    padding: 10px 14px;
    background: #cce6f6;
    font-size: 16px;
    margin-top: 15px;
    border: 2px;
    color: rgb(104, 116, 138);
  }

  /* Footer */
  .footer {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
  }
  .doctor, .signature {
    width: 45%;
    font-size: 16px;
  }
  .signature {
    text-align: right;
  }
  .signature-line {
    display: inline-block;
    padding-top: 4px;
    font-weight: bold;
    font-size: 18px;
  }

  /* Bottom Note */
  .footer-note {
    text-align: center;
    margin-top: 20px;
    font-size: 12px;
    color: #555;
  }

  /* Print Optimization */
  @media print {
    body { background: none; padding: 0; }
    .prescription { box-shadow: none; width: 100%; margin: 0; }
  }

  .prescription {
  position: relative;
  background: #fff;
  overflow: hidden;
}


.prescription * {
  position: relative;
  z-index: 1; /* keep text above watermark */
}
.title-text{
    color: #0080cb !important;
}
.diagnosis-table {
  width: 100%;
  border-collapse: collapse;

  font-size: 16px;
}
  
.diagnosis-table td {
  border: 2px solid #6a95f3; /* slightly lighter blue for inner lines */
  padding: 6px 8px;
  color: #0f172a;
}

.prescription-table td {
  border: 2px solid #6a95f3; /* slightly lighter blue for inner lines */
  padding: 6px 8px;
  color: #0f172a;
}

.prescription-table thead th {
  background-color: #0080cb; /* blue background */
  color: #ffffff;            /* white text */
  border-top: 2px solid #ffffff;
  border-bottom: 2px solid #ffffff;
  border-left: 2px solid #ffffff;
  border-right: 2px solid #ffffff;
  padding: 8px 10px;
  font-weight: 600;
}
.prescription-table thead th:first-child {
  border-left: 2px solid #0080cb;
}

.prescription-table thead th:last-child {
  border-right: 2px solid #0080cb;
}

.prescription-table{
  font-size: 18px;
}
 .prescription {
      position: relative;          /* container for absolute watermark */
      min-height: 800px;          /* ensure container tall enough for PDF page */
      padding: 10px;
      box-sizing: border-box;
      background: #fff;
      font-family: Arial, sans-serif;
    }

    /* watermark image */
    .prescription .watermark {
      position: absolute;
      top: 40%;
      left: 50%;
      transform: translate(-50%, -50%); /* center */
      width: 500px;               /* adjust as needed */
      height: auto;
      opacity: 0.30;              /* faint watermark */
      z-index: 0;                 /* behind content */
      display: block;
      pointer-events: none;       /* don't block text selection */
      margin: 0;
    }

    /* main content should sit above watermark */
    .prescription .content {
      position: relative;
      z-index: 1;
      /* example styling so you can see overlay */
      background: transparent;
    }

    /* ensure tables, headings etc not hidden behind watermark */
    .prescription .content * {
      position: relative;
      z-index: 1;
    }
    .footer {
    margin-top: 25px;
    font-family: Arial, sans-serif;
  }

  .footer-table {
    width: 100%;
    border-collapse: collapse;
    border: none; /* no outer border */
  }

  .footer-table td {
    border: none; /* remove inside borders */
    padding: 0;
  }

  .footer-left {
    width: 70%;
    vertical-align: top;
  }

  /* make the right cell (stamp) vertically centered */
  .footer-right {
    width: 30%;
    text-align: right;
    vertical-align: middle;
  }

  .doctor-details {
    line-height: 1.5;
    font-size: 16px;
  }

  .signature-wrapper {
    margin-top: 10px;
    text-align: left;
  }

  .signature-img {
    height: 120px;
    width: 120px;
    object-fit: contain;
    display: block;
    margin-bottom: 5px;
  }

  .stamp-img {
    height: 120px;
    width: 120px;
    object-fit: contain;
    display: inline-block;    
  }
  

</style>
</head>
<body>

<div class="prescription">
  <img src="{{url("/storage/uploads/mulkmed_presciption_watermark.png")}}" class="watermark" alt="">
  
  <div class="header">
    <img style="width: 100; height: 100;" src={{url("/storage/uploads/prescription_logo.png")}} class="" alt="">
  </div>

  @php
    $medicineData = json_decode($prescription['medicine'] ?? '{}', true);
  @endphp
  
  <div class="patient-info">  
    <div style="width: 100%;">
  <div style="display: inline-block; width: 70%; vertical-align: top;">
    <h2 class="title-text" style="margin: 0;">eRx No.: {{ $medicineData['erx'] ?? '' }}</h2> 
    <h2 class="title-text" style="margin-bottom: 2px; line-height: 1.1;">Patient Details</h2>
    <span >{{ $tourist['first_name'] ?? '' }} {{ $tourist['last_name'] ?? '' }}</span><br>
    {{-- <span>{{ isset($user['gender']) ? ($user['gender'] == 1 ? 'Male' : 'Female') : 'N/A' }}</span><br>
    <span>DOB: {{ !empty($user['dob']) ? \Carbon\Carbon::parse($user['dob'])->format('d/m/Y') : 'N/A' }}</span><br>
    <span>Emirates ID: {{ $user['ref_id'] ?? '' }}</span><br> --}}
    <span>MRN No: {{ $prescription['appointment']['appointment_number'] ?? '' }}</span>
  </div>  

  <div style="display: inline-block; width: 28%; text-align: right; vertical-align: top;">
    <h2 class="title-text" style="margin: 0;">
    Date:
    {{ !empty($prescription['created_at']) ? \Carbon\Carbon::parse($prescription['created_at'])->format('d M Y') : 'N/A' }}
    </h2>
  </div>
</div>


  <!-- Diagnosis Table -->
   <h2>Diagnosis</h2>
 @php
  $medicineData = json_decode($prescription['medicine'] ?? '{}', true);
  $diagnoses = $medicineData['diagnosis'] ?? [];
@endphp

<table class="diagnosis-table" style="">
 
  <tbody>
    @if(count($diagnoses) === 0)
      <tr>
        <td colspan="3" style="text-align:center; padding:10px;">No diagnosis available</td>
      </tr>
    @else
      @foreach($diagnoses as $diag)
        @php
          $title = $diag['title'] ?? 'Diagnosis';
          // normalize label (optional): show 'Principal' instead of 'Principle' if needed
          if (strtolower(trim($title)) === 'principle') {
            $label = 'Principal';
          } else {
            $label = (stripos($title, 'principal') !== false || stripos($title, 'primary') !== false)
                      ? 'Principal'
                      : (ucfirst($title) . '');
          }

          $icds = is_array($diag['icd'] ?? null) ? $diag['icd'] : ($diag['icd'] ? [$diag['icd']] : []);
          $descs = is_array($diag['description'] ?? null) ? $diag['description'] : ($diag['description'] ? [$diag['description']] : []);
          $rows = max(count($icds), count($descs), 1);
        @endphp

        @for($i = 0; $i < $rows; $i++)
          <tr>
            {{-- print label only on the first row and use rowspan for neat grouping --}}
            @if($i === 0)
              <td style="vertical-align:top; padding:6px;" rowspan="{{ $rows }}">
                {{ $label }}
              </td>
            @endif

            <td style="padding:6px;">
              ICD: <strong>{{ $icds[$i] ?? '-' }}</strong>
            </td>

            <td style="padding:6px;">
              {{ $descs[$i] ?? '-' }}
            </td>
          </tr>
        @endfor

      @endforeach
    @endif
  </tbody>
</table>


  <!-- Prescription Table -->
   <h2>Prescription</h2>

<table class="prescription-table">
  <thead>
    <tr>
      <th>Drug Code</th>
      <th style="width: 5%;">Description</th>
      <th>Dosage</th>
      <th>Duration</th>
      <th>Qty</th>
      <th style="width: 10%;">Remarks</th>
    </tr>
  </thead>
  <tbody>
    @if(isset($medicineData['addMedicine']) && count($medicineData['addMedicine']) > 0)
      @foreach($medicineData['addMedicine'] as $med)
        <tr>
          <td>{{ $med['drugCode'] ?? '-' }}</td>
          <td style="width: 5%;">{{ $med['title'] ?? '-' }}</td>
          <td>{{ $med['mealTime'] == 1 ? 'After Meal' : 'Before Meal' }}</td>
          <td>{{ $med['dosage'] ?? '-' }}</td>
          <td>{{ $med['quantity'] ?? '-' }}</td>
          <td style="width: 10%;">{{ $med['notes'] ?? '-' }}</td>
        </tr>
      @endforeach
    @else
      <tr>
        <td colspan="6" style="text-align:center;">No medicines prescribed</td>
      </tr>
    @endif
  </tbody>
</table>

  @if(!empty($medicineData['notes']))
    <strong class="title-text">Notes:</strong><br> 

    <div class="notes">      
        <span>{{ $medicineData['notes'] }}</span>
    </div>
  @endif 


  <div class="footer">
  <table class="footer-table">
    <tr>
      <!-- Left side: Doctor + signature -->
      <td class="footer-left">
        <div class="doctor-details">
          <strong class="title-text" style="font-size: 15px;">Doctor Details</strong><br><br>
          {{ $prescription['appointment']['doctor']['name'] ?? ''}} <br>
          DHA Reg. No.: {{ $prescription['appointment']['doctor']['dha_registration_number'] ?? '' }}
        </div>

        <div class="signature-wrapper">
         <img
          class="signature-img"
          src="{{ !empty($prescription['appointment']['doctor']['digital_signature']) 
              ? url('/storage/'.$prescription['appointment']['doctor']['digital_signature']) 
              : asset('images/no-signature.png') }}" 
          alt="Doctor Signature"><br>

          <div class="signature-line">Doctor Signature</div>
        </div>
      </td>

      <!-- Right side: Stamp -->
      <td class="footer-right">
        <img
          class="stamp-img"
          src={{ url("/storage/uploads/mulkmed_prescription_stamp.png") }}
          alt="Stamp">
      </td>
    </tr>
  </table>
</div>

</body>  
</html>     
