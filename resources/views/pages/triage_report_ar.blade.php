<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>MIDAS Report Arabic</title>

        <style>
         @font-face {
            font-family: 'Amiri';
            /* src: url("{{ storage_path('app/public/font/Amiri-Regular.ttf') }}"); */
            src: url("{{ asset('storage/font/Amiri-Regular.ttf') }}");
            font-weight: normal;
            font-style: normal;
        }

        @font-face {
            font-family: 'Amiri';
            /* src: url("{{ storage_path('app/public/font/Amiri-Bold.ttf') }}"); */
            src: url("{{ asset('storage/font/Amiri-Bold.ttf') }}");
            font-weight: bold;
            font-style: normal;
        }
            html, body {
                margin: 0;
                padding: 0;
                height: 100%;
                -webkit-print-color-adjust: exact;
            }

            .MainDivContainer {
                width: 90%;
                margin: 0 auto;
                padding-bottom: 40px;
                box-sizing: border-box;
            }

            body, table, td, th {
                font-size: 10px;
                line-height: 1.3;
            }

            .report_table {
                width: 80%;
                border-collapse: collapse;
                margin-bottom: 5px;
            }
            table.report_table th,
            table.report_table td {
                padding: 4px 6px;
                text-align: center;
                vertical-align: middle;
            }

            /* (other styles unchanged...) */
            .list-item { padding: 4px; font-size: 10px; }
            .list-item::before { content: ""; display:inline-block; width:12px; height:12px; margin-right:8px; }
            .list-item-green::before{ background:#28a745; }
            .list-item-orange::before{ background:#FFA500; }
            .list-item-red::before{ background:red; }

            .triage-scale {
                width: 100%;
                max-width: 600px;
                direction: ltr;
            }
            .triage-scale table {
                width: 100%;
                table-layout: fixed;
                border-collapse: collapse;
            }
            .triage-scale th {
                width: 33.33%;
                text-align: center;
                vertical-align: top;
            }
            .triage-scale th.list-item-green,
            .triage-scale th.list-item-red {
                width: 22%;
            }
            .triage-scale th.list-item-orange {
                width: 56%;
                white-space: nowrap;
            }
            .triage-scale .result-label {
                color: #FFA500;
                text-align: center;
                white-space: nowrap;
            }

            .qa-item { margin-bottom: 3px; }
            .qa-item .question { font-size: 9px; color: #333; }
            .qa-item .answer { font-size: 9px; font-weight: bold; color: #2e7d32; }

            .your-result-common { background:#2e7d32; color:white; font-size:12px; padding:2px 3px; border-radius:12px; float:right; }

            @page {
                margin: 80px 40px 100px 40px; /* <-- bottom margin = footer height */
            }

            /* @font-face {
                font-family: 'amiri';
                src: url({{ asset('storage/app/public/font/Amiri-Regular.ttf') }}) format('truetype');
                font-weight: normal;
                font-style: normal;
            } */

            body {
                margin: 0;
                padding: 7px 0 20px 0; /* add space at bottom to avoid overlap */
                /* font-family : 'DejaVu Sans', sans-serif; */
                /* font-family: 'amiri', sans-serif; */
                 font-family: 'Amiri', sans-serif;
                 direction: rtl;
            }

            .footer {
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                height: 40px;
                display: block;
            }

            .footer-content {
                height: 100%;
                box-sizing: border-box;
                padding-top: 12px;
                padding-bottom: 6px;  
                background-color: #003366; 
                color: #ffffff;
                text-align: center;
                font-size: 10px;
                line-height: 1;
                width: 100%;
            }
            .page-break { page-break-after: always; }
        </style>
    </head>

    <body>

        <div class="footer">
            <div class="footer-content">
                www.mulkmed.com
            </div>
        </div>
        <div class="MainDivContainer">
            <img src="{{ asset('/storage/uploads/triage_report_company_icon.png') }}" style="width:80px; height:auto;">
            <h3>تفاصيل المريض:</h3>
            <table class="report_table" style="border: 2px solid #63a163;">
                <thead>
                <tr>
                    <th>اسم</th>
                    <th>تاريخ الميلاد</th>
                    <th>تفاصيل الاتصال</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $patient_name }}</td>
                        <td>{{ !empty($date_of_birth) ? \Carbon\Carbon::parse($date_of_birth)->format('d-m-Y') : '' }}</td>
                        <td>{{ str_replace('null', '', $contact_details) }}</td>
                    </tr>
                </tbody>
            </table>
            <table class="report_table" style="border: 2px solid #63a163;">
                <thead>
                    <tr>
                        <th>عمر</th>
                        <th>الجنس</th>
                        <th>دولة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $age }}</td>
                            <td>{{ $report->sex == 'M' ? "Male":"Female" }}</td>
                            <td>{{ $country_name }}@if(!empty(trim((string) ($region_name ?? '')))) ({{ $region_name }})@endif</td>
                        </tr>
                    </tbody>

                <thead>
                    <tr>
                        <th colspan="3" style="border-top: 2px solid #63a163; text-align:left !important">
                            دخلت الأعراض
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td colspan="3" style=" text-align:left !important">{{ $report->text }}</td>
                    </tr>

                </tbody>

            </table>
            <div class="index-container">
                <h3 style="color: #63a163">أين يمكن الحصول على الرعاية؟</h3>

                <div class="triage-scale">
                    <table>
                    <thead>
                            <tr>
                        <th class="list-item list-item-green">مولك ميد اون لاين دكتور </th>
                        <th class="list-item list-item-orange">طبيب الأسرة / عيادة الرعاية العاجلة / وحدة الإصابات الطفيفة</th>
                        <th class="list-item list-item-red">خدمات الطوارئ</th>
                            </tr>
                    </thead>
                </table>    

                @php
                    $max = 150;
                    $percentage = (((float) ($report->score ?? 0)) / $max) * 100;
                @endphp

                    <div style="position:relative; width:100%; height:30px;">
                        <!-- Bar Image -->
                        <img src="{{ asset('/storage/uploads/triage_graph.png') }}"
                            style="width:100%; height:30%; object-fit:cover; display:block;" />

                        <!-- Thumb (slightly taller than bar) -->
                        <!-- Thumb (a bit taller) -->
                        <div style="
                            position:absolute;
                            left:{{ $percentage }}%;
                            top:-8px;           /* goes a touch higher above */
                            height:27px;        /* slightly taller than before */
                            width:2px;
                            background:#000;    /* black line */
                            transform:translateX(-50%);
                        "></div>

                    </div>
                </div>

                <table class="" style="margin-top: 10px; width:100%; text-align:center">
                    <thead>
                        <th class="" style="color:#FFA500; text-align:center">طبيب الأسرة / عيادة الرعاية العاجلة / وحدة الإصابات الطفيفة</th>    
                    </thead>
                </table> 
            </div>
            
            @if(isset($ranked_differential_diagnoses))

                @php
                    // Convert to collection so we can chunk
                    $collection = collect($ranked_differential_diagnoses);
                @endphp

                <h3 style="color:#63a163; margin-top:0;">نتائجك</h3>
                @foreach($collection->chunk(13) as $index => $chunk)
                    <table style="border:1px solid #ddd; border-radius:4px; width:350px; border-collapse:collapse;">
                        @foreach($chunk as $index => $rdd)
                            <tr style="page-break-inside: avoid; border-top:1px solid #ddd;">
                                <td style="padding:8px 12px; border-bottom:1px solid #ddd;">
                                    {{ $rdd['diagnosis_name'] ?? '' }}
                                    @if(isset($rdd['common_diagnosis']) && $rdd['common_diagnosis'] === "true")
                                        <span class="your-result-common">شائع</span>
                                    @endif
                                    @if(isset($rdd['red_flag']) && $rdd['red_flag'] === "true")
                                        <img src="{{ asset('/storage/uploads/red_flag.jpg') }}">
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </table>
                @endforeach

            @endif

            <br>
            <h3 style="color: #63a163;">أين يمكن الحصول على الرعاية؟ - الردود</h3>

            <div class="qa-container">
                @foreach($questions as $q)
                    <div class="qa-item">
                        <p class="question">{{ $q->question }}</p>
                        <p class="answer">{{ $q->answer ?? '—' }}</p>
                    </div>
                @endforeach
            </div>
            
        </div>
    </body>
</html>