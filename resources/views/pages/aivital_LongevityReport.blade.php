<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width" />
    <title>VitalScan Report</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #102027;
            background: #fff;
            -webkit-print-color-adjust: exact;
        }

        .page {
            width: 190mm;
            margin: 0 auto;
            box-sizing: border-box;
            padding: 0 4mm;
        }

        .page-break {
            page-break-after: always;
            break-after: page;
        }

        .header {
            background: #0b78b0;
            color: #fff;
            padding: 12px 14px;
            display: block;
            margin-bottom: 8px;
        }

        .header-inner {
            width: 100%;
        }

        .logo {
            float: left;
            width: 80px;
            margin-top: 15px;
            margin-left: 40px;
            margin-bottom: 15px;
        }

        .title {
            margin-left: 90px;
            font-size: 28px;
            font-weight: 800;
            text-align: right;
            margin-top: 15px;
            margin-right: 20px;
        }

        .clear {
            clear: both;
        }

        .summary {
            margin: 8px 0;
            background: #fbfdff;
            border: 1px solid #eef3f6;
            border-radius: 8px;
            padding: 12px;
        }

        .summary .left {
            float: left;
            width: 62%;
        }

        .summary .right {
            float: right;
            width: 34%;
            text-align: right;
        }

        .meta {
            font-weight: 700;
            margin-bottom: 6px;
        }

        .small {
            color: #6b7280;
            font-size: 13px;
        }

        .indices-small{
            color: #4b5563;
        }

        .clearfix {
            clear: both;
        }

        .table-head {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-family: Arial, Helvetica, sans-serif;
        }

        .table-head th {
            background: #f7fbfd;
            /* light background like screenshot */
            border: 1px solid #e6edf2;
            padding: 10px 12px;
            font-weight: 700;
            font-size: 13px;
            color: #0f1724;
            text-align: left;
            font-size: 24px;
        }

        .table-head th:nth-child(2),
        .table-head th:nth-child(3),
        .table-head th:nth-child(4) {
            text-align: center;
        }

        .th-col {
            flex: 1;
            text-align: center;
        }

        .th-col:first-child {
            flex: 2.5;
            /* Measurement column wider */
            text-align: left;
        }

        .panel {
            border: 2px solid #eef3f6;
            border-radius: 8px;
            padding: 6px;
            margin-top: 6px;
        }

        .row {
            display: block;
            border-bottom: 2px solid #f1f5f8;
            padding: 12px 8px;
            overflow: hidden;
        }

        .row:last-child {
            border-bottom: none;
        }

        .row .left-col {
            width: 60%;
            float: left;
        }

        .row .mid-col {
            width: 18%;
            float: left;
            text-align: center;
            font-size: 16px;
        }

        .row .unit-col {
            width: 10%;
            float: left;
            text-align: left;
            padding-left: 6px;
            color: #6b7280;
            font-size: 16px;
            margin-top: 5px;
        }

        .row .right-col {
            width: 12%;
            float: right;
            font-weight: 700;
            color: #0f1724;
            font-size: 16px;
        }

        .metric-title {
            font-weight: 700;
            margin-bottom: 6px;
        }

        .metric-desc {
            color: #333943;
            font-size: 18px;
            line-height: 1.4;
        }

        .pill {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 14px;
            background: #f3f7fb;
            font-weight: 700;
            min-width: 60px;
        }

        .row .clear-row {
            clear: both;
            display: block;
            height: 0;
        }

        .indices {
            display: block;
            margin-top: 12px;
        }

        .indices-wrap {
            width: 100%;
        }

        .index-line {
            display: block;
            border: 1px solid #eef3f6;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            overflow: hidden;
        }

        .index-line .left {
            float: left;
            width: 78%;
        }

        .index-line .right {
            float: right;
            width: 20%;
            text-align: center;
        }

        .index-line-heading {
            display: block;
            padding: 12px;
            margin-bottom: 10px;
            overflow: hidden;
        }

        .index-line-heading .left {
            float: left;
            width: 78%;
        }

        .index-line-heading .right {
            float: right;
            width: 20%;
            text-align: center;
        }

        .index-title {
            font-weight: 700;
            margin-bottom: 6px;
        }

        .index-desc {
              color: #333943;
            font-size: 18px;
            line-height: 1.4;
        }

        .result-box {
            background: #eef2f6;
            border-radius: 8px;
            padding: 12px;
            font-weight: 800;
        }

        .legend {
            margin-top: 18px;
        }

        .legend .chip {
            display: inline-block;
            border: 1px solid #e6e9ee;
            border-radius: 999px;
            padding: 6px 12px;
            margin-right: 8px;
            font-weight: 700;
            font-size: 16px;
        }

        .disclaimer {
            color: #6b7280;
            font-size: 16px;
            margin-top: 10px;
          
        }

        img {
            max-width: 100%;
            height: auto;
        }

        .mb-12 {
            margin-bottom: 12px;
        }

        /* PAGE 4 — summary table + triggers */
        .p4-table-head-wrap {
            border: 1px solid #e5e7eb;
            border-radius: 0px;
            overflow: hidden;
            margin-top: 10px;
            background: #f3f5f7;
        }

        .p4-table-body-wrap {
            border: 1px solid #e5e7eb;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 5px;
            background: #fff;
        }

        .p4-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            font-size: 13px;
            table-layout: fixed;
        }

        .p4-table th {
            background: #f3f5f7;
            padding: 12px 10px;
            font-weight: 700;
            color: #0b1f33;
            text-align: left;
            font-size: 13px;
        }

        .p4-table th.center,
        .p4-table td.center {
            text-align: center;
        }

        .p4-table td {
            border-bottom: 1px solid #eceff2;
            padding: 14px 10px;
            vertical-align: middle;
            color: #0b1f33;
            font-size: 13px;
            background: #fff;
        }

        .p4-table-body-wrap .p4-table tbody tr:last-child td {
            border-bottom: none;
        }

        .p4-metric-name {
            font-weight: 700;
            color: #0b1f33;
            white-space: nowrap;
        }

        .p4-metric-icon {
            width: 26px;
            height: 26px;
            vertical-align: middle;
            margin-right: 8px;
            object-fit: contain;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            line-height: 1.3;
        }

        .status-attention {
            background: #FFE8CC;
            color: #111827;
        }

        .status-low,
        .status-high {
            background: #FFD6D6;
            color: #111827;
        }

        .status-normal {
            background: #D4F5E4;
            color: #111827;
        }

        .trigger-wrap {
            margin-top: 18px;
            overflow: hidden;
        }

        .trigger-card {
            float: left;
            width: 47%;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 14px;
            box-sizing: border-box;
            min-height: 190px;
            background: #fff;
        }

        .trigger-card.right {
            float: right;
        }

        .trigger-title {
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 8px;
            color: #0f1724;
        }

        .trigger-title img {
            width: 28px;
            height: 28px;
            vertical-align: middle;
            margin-right: 8px;
            object-fit: contain;
            border-radius: 6px;
        }

        .p4-patient {
            margin: 4px 0 14px;
            padding: 0;
            background: transparent;
            border: none;
        }

        .p4-patient .meta {
            font-weight: 700;
            font-size: 14px;
            color: #0b1f33;
            margin: 0 0 8px;
            line-height: 1.35;
        }

        .p4-patient .meta span {
            font-weight: 700;
            color: #0b1f33;
        }

        .trigger-desc {
            color: #6b7280;
            font-size: 12px;
            line-height: 1.45;
            margin-bottom: 12px;
        }

        .trigger-matched {
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 6px;
            color: #0f1724;
        }

        .trigger-list {
            margin: 0;
            padding-left: 18px;
            color: #374151;
            font-size: 12px;
            line-height: 1.55;
        }

        .trigger-list li {
            margin-bottom: 10px;
        }

        .mc-name {
            font-weight: 700;
            color: #0f1724;
            margin-bottom: 2px;
        }

        .mc-detail {
            color: #4b5563;
            font-size: 11.5px;
            line-height: 1.4;
            padding-left: 2px;
        }

        /* small responsive-like adjustments for printing */
        @media print {
            .title {
                font-size: 26px;
            }

            .metric-desc {
                font-size: 13px;
            }
        }
    </style>
</head>

<body>

    @php
        if (!function_exists('getPillColor')) {
            function getPillColor($value, $min = null, $max = null, $tolerance = 10)
            {
                if (is_null($value)) {
                    return '#cbd5e1'; // missing value
                }

                if (!is_null($min) && !is_null($max)) {
                    if ($value >= $min && $value <= $max) {
                        return '#28a745'; // within range
                    } elseif ($value > $max && $value <= $max + $tolerance) {
                        return '#ffbf47'; // slightly above
                    } else {
                        return '#f0524d'; // out of range
                    }
                }

                return '#28a745'; // default green if no range
            }
        }
    @endphp

    @php
        if (!function_exists('worstColor')) {
            function worstColor($colors)
            {
                // severity ranking
                $rank = [
                    '#f0524d' => 3, // red - worst
                    '#ffbf47' => 2, // amber - medium
                    '#28a745' => 1, // green - good
                    '#cbd5e1' => 0, // gray - missing
                ];

                $worst = '#28a745';
                $worstRank = -1;

                foreach ($colors as $c) {
                    $c = strtolower($c);
                    $r = $rank[$c] ?? -1;
                    if ($r > $worstRank) {
                        $worstRank = $r;
                        $worst = $c;
                    }
                }

                return $worst;
            }
        }
    @endphp

    <!-- PAGE 1 -->
    <div class="header">
        <div class="header-inner">
            <div class="logo">
                <img src="{{ asset('/storage/uploads/vital_scan_report_pdf_company_logo.png') }}"
                    style="width:50px;height:70px" />
                {{-- <div
                        style="width:70px;height:70px;background:#fff;color:#0b78b0;border-radius:6px;font-weight:700;display:flex;align-items:center;justify-content:center">
                        MM</div> --}}
            </div>
            <div class="title">Health Check Results</div>
            <div class="clear"></div>
        </div>
    </div>
    <div class="page">


        <div class="summary">
            <div class="left">
                <div class="meta">Name : <span>{{ $user->fullname ?? '' }}</span></div>
                <div class="meta">DOB : {{ !empty($user->dob) ? \Carbon\Carbon::parse($user->dob)->format('d-m-Y') : '' }}</div>
                <div class="meta" style="margin-top:6px">
                    Age : {{ \Carbon\Carbon::parse($user->dob)->age ?? '' }}
                </div>
                <div class="meta" style="margin-top:6px">
                    Scan Date : {{ \Carbon\Carbon::parse($scan_date)->format('d/m/Y , H:i:s') }}
                </div>
            </div>
            <div class="clearfix"></div>
        </div>

        <table class="table-head">
            <tr>
                <th style="width:60%">Measurement results</th>
                <th style="width:15%">Result</th>
                <th style="width:10%">Unit</th>
                <th style="width:15%">Normal range</th>
            </tr>
        </table>

        <div class="panel">
            <!-- Pulse -->
            <div class="row">
                <div class="left-col">
                    <div class="metric-title"><img  src="{{ asset('/storage/uploads/vitalscanheart.png') }}">&nbsp;Pulse (HR)</div>
                    <div class="metric-desc">Measures the average number of heartbeats per minute, reflecting autonomic
                        nervous system state.</div>
                </div>
                <div class="mid-col">
                    <div class="pill"><span
                            style="display:inline-block;width:10px;height:10px;background:{{ getPillColor(getMetricValue($report->heartRate ?? 0), 60, 100) }};border-radius:50%;margin-right:6px;"></span>{{ getMetricDisplay($report->heartRate ?? 0, 'round') }}
                    </div>
                </div>
                <div class="unit-col">bpm</div>
                <div class="right-col">60 - 100</div>
                <div class="clear-row"></div>
            </div>

            <!-- Blood Pressure -->
            <div class="row">
                <div class="left-col">
                    <div class="metric-title"><img  src="{{ asset('/storage/uploads/BP_vital_scan_icon.png') }}">&nbsp;Blood Pressure</div>
                    <div class="metric-desc">Blood pressure readings consist of two key numbers that indicate
                        cardiovascular health (systolic/diastolic).</div>
                </div>
                <div class="mid-col">
                                        @php
                       $systolic = 0;
                        $diastolic = 0;
                        $bpDisplay = '- / -';
                        $bpValStr = '';

                        if (!empty($report?->bloodPressure)) {
                            $bpValStr = getMetricDisplay($report->bloodPressure);
                            
                            // fallback for old format if it was simply string "120/80"
                            if (str_contains($bpValStr, '/')) {
                                [$rawSystolic, $rawDiastolic] = explode('/', $bpValStr);

                                // Convert safely to integers
                                $systolic = (int) filter_var($rawSystolic, FILTER_SANITIZE_NUMBER_INT);
                                $diastolic = (int) filter_var($rawDiastolic, FILTER_SANITIZE_NUMBER_INT);

                                $sColor = getPillColor($systolic, 90, 120);
                                $dColor = getPillColor($diastolic, 60, 70);

                                $bpColor = worstColor([$sColor, $dColor]);
                                
                                // If the original extracted result was already "111 / 74", it'll be preserved in $bpValStr
                            } else {
                                $bpColor = getPillColor(0, 0, 0); // safe default
                            }
                        } else {
                            $bpColor = getPillColor(0, 0, 0); // safe default
                        }
                    @endphp
                    <div class="pill"><span
                            style="display:inline-block;width:10px;height:10px;background:{{ $bpColor }};border-radius:50%;margin-right:6px;"></span>


                        {{ $bpValStr }}
                    </div>
                </div>
                <div class="unit-col">mmHg</div>
                <div class="right-col">SBP 90 - 120, DBP 60 - 70</div>
                <div class="clear-row"></div>
            </div>

            <!-- Heart Rate Variability -->
            <div class="row">
                <div class="left-col">
                    <div class="metric-title"><img  src="{{ asset('/storage/uploads/Heart_Rate_VitalScan.png') }}">&nbsp;Heart Rate Variability (HRV)</div>
                        <div class="metric-desc">Measures the variation in time intervals between heartbeats, reflecting
                            autonomic nervous system state.</div>
                        </div>
                <div class="mid-col">
                    <div class="pill"><span
                            style="display:inline-block;width:10px;height:10px;background:#28a745;border-radius:50%;margin-right:6px;"></span>{{ getMetricDisplay($report->hrvSdnnMs ?? 0, 'round') }}
                    </div>
                </div>
                <div class="unit-col">ms</div>
                <div class="right-col">N/A*</div>
                <div class="clear-row"></div>
            </div>

            <!-- Breathing Rate -->
            <div class="row">
                <div class="left-col">
                    <div class="metric-title"><img  src="{{ asset('/storage/uploads/Breathing_Rate_vital_scan_icon.png') }}">&nbsp;Breathing Rate (BR)</div>
                    <div class="metric-desc">Counts breaths per minute and reflects respiratory status and stress level.
                    </div>
                </div>
                <div class="mid-col">
                    <div class="pill"><span
                            style="display:inline-block;width:10px;height:10px;background:{{getPillColor(getMetricValue($report->respiratoryRate ?? 0), 12, 20)}};border-radius:50%;margin-right:6px;"></span>{{ getMetricDisplay($report->respiratoryRate ?? 0, 'round') }}
                    </div>
                </div>
                <div class="unit-col">bpm</div>
                <div class="right-col">12 - 20</div>
                <div class="clear-row"></div>
            </div>

            <!-- Stress Index -->
            <div class="row">
                <div class="left-col">
                    <div class="metric-title"><img  src="{{ asset('/storage/uploads/stress_mesa_logo.png') }}">&nbsp;Stress Index</div>
                    <div class="metric-desc">Indicates whether the heart is working in a stressed or relaxed manner.
                    </div>
                </div>
                <div class="mid-col">
                    <div class="pill"><span
                            style="display:inline-block;width:10px;height:10px;background:{{getPillColor(getMetricValue($report->stressLevel ?? 0), 0, 4)}};border-radius:50%;margin-right:6px;"></span>{{ getMetricDisplay($report->stressLevel ?? 0, 'round') }}
                    </div>
                </div>
                <div class="unit-col">-</div>
                <div class="right-col">0 - 4</div>
                <div class="clear-row"></div>
            </div>

            <!-- Parasympathetic Activity -->
            <div class="row">
                <div class="left-col">
                    <div class="metric-title"><img  src="{{ asset('/storage/uploads/para_mesa_logo.png') }}">&nbsp;Parasympathetic Activity</div>
                    <div class="metric-desc">Assesses activity of the parasympathetic nervous system responsible for
                        relaxation and recovery.</div>
                </div>
                <div class="mid-col">
                    <div class="pill"><span
                            style="display:inline-block;width:10px;height:10px;background:#28a745;border-radius:50%;margin-right:6px;"></span>{{ getMetricDisplay($report->parasympatheticActivity ?? 0, 'round') }}
                    </div>
                </div>
                <div class="unit-col">{{ getMetricUnit($report->parasympatheticActivity ?? 0, '%') }}</div>
                <div class="right-col">{{ getMetricRange($report->parasympatheticActivity ?? 0, 'N/A*') }}</div>
                <div class="clear-row"></div>
            </div>

            <!-- Cardiac Workload -->
            <div class="row">
                <div class="left-col">
                    <div class="metric-title"><img  src="{{ asset('/storage/uploads/cardiac_mesa_logo.png') }}">&nbsp;Cardiac Workload</div>
                    <div class="metric-desc">Indicates the work that the heart needs to do to pump blood.</div>
                </div>
                <div class="mid-col">
                    <div class="pill"><span
                            style="display:inline-block;width:10px;height:10px;background:{{getPillColor(getMetricValue($report->cardiacWorkload ?? 0), 90, 216)}};border-radius:50%;margin-right:6px;"></span>{{ getMetricDisplay($report->cardiacWorkload ?? 0, 'round') }}
                    </div>
                </div>
                <div class="unit-col">a.u.</div>
                <div class="right-col">90 - 216</div>
                <div class="clear-row"></div>
            </div>

            <!-- BMI -->
            <div class="row">
                <div class="left-col">
                    <div class="metric-title"><img  src="{{ asset('/storage/uploads/bodymass_mesa_logo.png') }}">&nbsp;Body Mass Index (BMI)</div>
                    <div class="metric-desc">Body Mass Index indicates if weight is appropriate for height. Derived via
                        AI facial estimation in this report.</div>
                </div>
                <div class="mid-col">
                    <div class="pill"><span
                            style="display:inline-block;width:10px;height:10px;background:{{getPillColor(getMetricValue($report->bmi ?? 0), 18.5, 24.9)}};border-radius:50%;margin-right:6px;"></span>{{ getMetricDisplay($report->bmi ?? 0, 'round') }}
                    </div>
                </div>
                <div class="unit-col">-</div>
                <div class="right-col">18.5 - 24.9</div>
                <div class="clear-row"></div>
            </div>

        </div> <!-- /panel -->

        <div class="legend">
            <div class="chip"><span
                    style="display:inline-block;width:10px;height:10px;background:#28a745;border-radius:50%;margin-right:8px"></span>
                result within normal range</div>
            <div class="chip"><span
                    style="display:inline-block;width:10px;height:10px;background:#ffbf47;border-radius:50%;margin-right:8px"></span>
                mild deviation</div>
            <div class="chip"><span
                    style="display:inline-block;width:10px;height:10px;background:#f0524d;border-radius:50%;margin-right:8px"></span>
                major deviation</div>
            <div class="chip"><span
                    style="display:inline-block;width:10px;height:10px;background:#cbd5e1;border-radius:50%;margin-right:8px"></span>
                missing result</div>
        </div>

        <div class="disclaimer">This report is intended for informational purposes only and does not constitute medical advice. It is not a substitute for professional medical judgment, diagnosis, or treatment. Always consult with a qualified healthcare professional regarding any medical concerns or decisions.</div>

    </div><!-- end page 1 -->

    <div class="page-break"></div>

    <!-- PAGE 2 (Indices 1) -->
     <div class="header">
            <div class="header-inner">
                <div class="logo">
                   <img src="{{ asset('/storage/uploads/vital_scan_report_pdf_company_logo.png') }}"
                    style="width:50px;height:70px" />
                </div>
                <div class="title">Health Indices — part 1 of 2</div>
                <div class="clear"></div>
            </div>
        </div>
    <div class="page">
       

        <div class="indices">
            <div class="indices-wrap">
                <br>

                <div class="index-line-heading">
                    <div class="left">
                        <div class="index-title indices-small">&nbsp;Index description</div>
                    </div>
                    <div class="right">
                        <div class="index-title" style="height: 19px"></div>
                        <div class="index-title indices-small">Result</div>
                    </div>
                    <div class="clearfix"></div>
                </div>
                
                <div class="index-line">
                    <div class="left">
                        <div class="index-title">Wellness Score</div>
                        <div class="index-desc">This score provides acomprehensive view of your overal welness, integrating key health and metabolic factors ot give you asingle, actionable metric.</div>
                        <br>
                        <div class="index-desc">Interpretation of the result</div>
                        <div class="index-desc">This range indicates a critical state of wellness, often characterized by multiple significant health risks or conditions. Immediate medical attention and lifestyle changes are likely required ot improve overal health. Key metrics ni this range may be severely out fo balance.</div>
                    </div>

                    <div class="right">
                        <div class="result-box">{{ getMetricDisplay($report->healthIndices?->wellnessScore ?? 0, 'format2') }}</div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <br>

                <div class="index-line-heading">
                    <div class="left">
                        <div class="index-title">Health Risk Indices</div>
                        <div class="index-title indices-small">&nbsp;Index description</div>
                    </div>
                    <div class="right">
                        <div class="index-title" style="height: 19px"></div>
                        <div class="index-title indices-small">Result</div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title"><img  src="{{ asset('/storage/uploads/vascular_mesa_logo.png') }}">&nbsp;Vascular Age</div>
                        <div class="index-desc">Estimated apparent age of blood vessels as a way of showing the overall
                            cardiovascular risk.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">{{ getMetricDisplay($report->healthIndices?->vascularAge ?? 0, 'format2') }}</div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title"><img  src="{{ asset('/storage/uploads/cardiovascular_mesa_logo.png') }}">&nbsp;Cardiovascular Disease Risk</div>
                        <div class="index-desc">Estimated risk of a first hard atherosclerotic cardiovascular event in
                            the next 10 years.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">{{ getMetricDisplay($report->healthIndices?->cvDiseases?->overallRisk ?? 0, 'format2') }}</div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title"><img  src="{{ asset('/storage/uploads/cardiovascular_mesa_logo.png') }}">&nbsp;Cardiovascular Risk Score</div>
                        <div class="index-desc">Point-based measure of cardiovascular risk in the next 10 years (e.g.,
                            Framingham).</div>
                    </div>
                    <div class="right">
                        <div class="result-box">{{ getMetricDisplay($report->healthIndices?->totalCVMortalityRisk ?? 0, 'format2') }}</div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title"><img  src="{{ asset('/storage/uploads/hard_mesa_logo.png') }}">&nbsp;Hard and Fatal Events Risks</div>
                        <div class="index-desc">Estimated risk of hard or fatal cardiovascular events in the next 10
                            years.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">{{ getMetricDisplay($report->healthIndices?->hardAndFatalEvents?->hardCVEventRisk ?? 0, 'format2') }}</div>
                    </div>
                    <div class="clearfix"></div>
                </div>

            </div>
        </div>

        <div style="margin-top:18px" class="legend">
            <div class="chip"><span
                    style="display:inline-block;width:10px;height:10px;background:#28a745;border-radius:50%;margin-right:8px"></span>
                result within normal range</div>
            <div class="chip"><span
                    style="display:inline-block;width:10px;height:10px;background:#ffbf47;border-radius:50%;margin-right:8px"></span>
                mild deviation</div>
            <div class="chip"><span
                    style="display:inline-block;width:10px;height:10px;background:#f0524d;border-radius:50%;margin-right:8px"></span>
                major deviation</div>
            <div class="chip"><span
                    style="display:inline-block;width:10px;height:10px;background:#cbd5e1;border-radius:50%;margin-right:8px"></span>
                missing result</div>
        </div>

        <div class="disclaimer">This report is intended for informational purposes only and does not constitute medical advice. It is not a substitute for professional medical judgment, diagnosis, or treatment. Always consult with a qualified healthcare professional regarding any medical concerns or decisions.</div>
    </div>

    <div class="page-break"></div>

    <!-- PAGE 3 (Chronic & Body Composition) -->
    <div class="header">
            <div class="header-inner">
                <div class="logo">
                   <img src="{{ asset('/storage/uploads/vital_scan_report_pdf_company_logo.png') }}"
                    style="width:50px;height:70px" />
                </div>
                <div class="title">Health Indices — part 2 of 2</div>
                <div class="clear"></div>
            </div>
        </div>
    <div class="page">
        

        <div class="indices">
            <div class="indices-wrap">


                <br>

                <div class="index-line-heading">
                    <div class="left">
                        <div class="index-title">Chronic Disease Risk Indices</div>
                        <div class="index-title indices-small">&nbsp;Index description</div>
                    </div>
                    <div class="right">
                        <div class="index-title" style="height: 19px"></div>
                        <div class="index-title indices-small">Result</div>
                    </div>
                    <div class="clearfix"></div>
                </div>
                
                
                <div class="index-line">
                    <div class="left">
                        <div class="index-title"><img  src="{{ asset('/storage/uploads/hypertension_mesa_logo.png') }}">&nbsp;Hypertension Risk</div>
                        <div class="index-desc">Assesses risk of high blood pressure that can lead to heart disease and
                            stroke.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">
                            <div>
                                @php
                                    $hypertensionRisk = getMetricDisplay($report->healthIndices?->hypertensionRisk ?? 0, 'format2');
                                @endphp

                                @if($hypertensionRisk < 5)
                                    Low
                                @elseif($hypertensionRisk >= 5 && $hypertensionRisk < 10)
                                    Medium
                                @else
                                    High
                                @endif
                            </div>
                            <div>
                            {{ getMetricDisplay($report->healthIndices?->hypertensionRisk ?? 0, 'format2') }}%
                            </div>
                            
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title"><img  src="{{ asset('/storage/uploads/diabetes_mesa_logo.png') }}">&nbsp;Diabetes Risk</div>
                        <div class="index-desc">Evaluates risk of diabetes and supports lifestyle changes to prevent or
                            delay disease.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">
                            <div>
                                @php
                                    $diabetesRisk = getMetricDisplay($report->healthIndices?->diabetesRisk ?? 0, 'format2');
                                @endphp

                                @if($diabetesRisk < 5)
                                    Low
                                @elseif($diabetesRisk >= 5 && $diabetesRisk < 10)
                                    Medium
                                @else
                                    High
                                @endif
                            </div>
                            <div>
                            {{ getMetricDisplay($report->healthIndices?->diabetesRisk ?? 0, 'format2')}}%
                            </div>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title"><img  src="{{ asset('/storage/uploads/fatty_mesa_logo.png') }}">&nbsp;Fatty Liver Disease Risk (NAFLD)</div>
                        <div class="index-desc">Identifies risk of liver fat buildup that can lead to serious issues if
                            unmanaged.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">{{ ucfirst(getMetricDisplay($report->healthIndices?->nonAlcoholicFattyLiverDiseaseRisk ?? '0.00')) }}</div>
                    </div>
                    <div class="clearfix"></div>
                </div>
                <br>
                <!-- Body Composition group -->
               

                <div class="index-line-heading">
                    <div class="left">
                         <div class="index-title">Body Composition and Metabolic Indices</div>
                            <div class="index-title indices-small">&nbsp;Index description</div>
                    </div>
                    <div class="right">
                        <div class="index-title" style="height: 19px"></div>
                        <div class="index-title indices-small">Result</div>
                    </div>
                    <div class="clearfix"></div>
                </div>
             
                <div class="index-line">
                    <div class="left">
                        <div class="index-title"><img  src="{{ asset('/storage/uploads/waist_mesa_logo.png') }}">&nbsp;Waist-to-Height Ratio (WHtR)</div>
                        <div class="index-desc">Assesses obesity-related risks via ratio of waist circumference to
                            height.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">{{ getMetricDisplay($report->healthIndices?->waistToHeightRatio ?? 0, 'format2') }}<div
                                style="font-weight:600;color:#6b7280;font-size:11px;margin-top:6px">Normal 0 - 0.53
                            </div>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title"><img  src="{{ asset('/storage/uploads/body_fat_mesa_logo.png') }}">&nbsp;Body Fat Percentage (BFP)</div>
                        <div class="index-desc">Proportion of body fat relative to total body weight.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">{{ getMetricDisplay($report->healthIndices?->bodyFatPercentage ?? 0, 'format2') }}<div
                                style="font-weight:600;color:#6b7280;font-size:11px;margin-top:6px">Normal 7 - 23%
                            </div>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title"><img  src="{{ asset('/storage/uploads/body_roundness_mesa_logo.png') }}">&nbsp;Body Roundness Index (BRI)</div>
                        <div class="index-desc">Distribution of body fat focusing on abdominal fat using waist
                            circumference and height.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">{{ getMetricDisplay($report->healthIndices?->bodyRoundnessIndex ?? 0, 'format2') }}<div
                                style="font-weight:600;color:#6b7280;font-size:11px;margin-top:6px">Normal 0 - 3.85
                            </div>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title"><img  src="{{ asset('/storage/uploads/body_shape_index_mesa_logo.png') }}">&nbsp;A Body Shape Index (ABSI)</div>
                        <div class="index-desc">Uses waist circumference, weight, height, and body fat distribution to
                            estimate risks.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">{{ getMetricDisplay($report->healthIndices?->aBodyShapeIndex ?? 0, 'format2') }}<div
                                style="font-weight:600;color:#6b7280;font-size:11px;margin-top:6px">Normal 0 - 0.083
                            </div>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title"><img  src="{{ asset('/storage/uploads/coincity_mesa_logo.png') }}">&nbsp;Conicity Index (CI)</div>
                        <div class="index-desc">Identifies visceral fat using waist circumference, weight, and height.
                        </div>
                    </div>
                    <div class="right">
                        <div class="result-box">{{ getMetricDisplay($report->healthIndices?->conicityIndex ?? 0, 'format2') }}<div
                                style="font-weight:600;color:#6b7280;font-size:11px;margin-top:6px">Normal 0 - 1.275
                            </div>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title"><img  src="{{ asset('/storage/uploads/besal_mesa_logo.png') }}">&nbsp;Basal Metabolic Rate (BMR)</div>
                        <div class="index-desc">Calories your body needs at rest for essential functions.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">{{ getMetricDisplay($report->healthIndices?->basalMetabolicRate ?? 0, 'format2') }}</div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title"><img  src="{{ asset('/storage/uploads/total_mesa_logo.png') }}">&nbsp;Total Daily Energy Expenditure (TDEE)</div>
                        <div class="index-desc">Total daily calorie usage including BMR, activity, digestion, and other
                            functions.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">{{ getMetricDisplay($report->healthIndices?->totalDailyEnergyExpenditure ?? 0, 'format2') }}</div>
                    </div>
                    <div class="clearfix"></div>
                </div>

            </div>
        </div>

        <div style="margin-top:18px" class="legend">
            <div class="chip"><span
                    style="display:inline-block;width:10px;height:10px;background:#28a745;border-radius:50%;margin-right:8px"></span>
                result within normal range</div>
            <div class="chip"><span
                    style="display:inline-block;width:10px;height:10px;background:#ffbf47;border-radius:50%;margin-right:8px"></span>
                mild deviation</div>
            <div class="chip"><span
                    style="display:inline-block;width:10px;height:10px;background:#f0524d;border-radius:50%;margin-right:8px"></span>
                major deviation</div>
            <div class="chip"><span
                    style="display:inline-block;width:10px;height:10px;background:#cbd5e1;border-radius:50%;margin-right:8px"></span>
                missing result</div>
        </div>

        <div class="disclaimer">This report is intended for informational purposes only and does not constitute medical advice. It is not a substitute for professional medical judgment, diagnosis, or treatment. Always consult with a qualified healthcare professional regarding any medical concerns or decisions.</div>

    </div><!-- end page 3 -->

    <div class="page-break"></div>

    <!-- PAGE 4 — Summary & Triggers -->
    @php
        // Normalize possible array/string payloads so object access is safe
        if (empty($shen_ai) || is_string($shen_ai)) {
            $shen_ai = is_string($shen_ai) && $shen_ai !== '' ? (json_decode($shen_ai) ?: new \stdClass()) : new \stdClass();
        } elseif (is_array($shen_ai)) {
            $shen_ai = json_decode(json_encode($shen_ai));
        }
        if (empty($report) || is_string($report)) {
            $report = is_string($report) && $report !== '' ? (json_decode($report) ?: new \stdClass()) : new \stdClass();
        } elseif (is_array($report)) {
            $report = json_decode(json_encode($report));
        }

        // Try to get from shen_ai first, fallback to report
        $wellnessScore = getMetricValue($shen_ai->healthIndices->wellnessScore ?? $shen_ai->wellnessScore ?? $report->healthIndices?->wellnessScore ?? 0);
        $hrvValue = getMetricValue($shen_ai->hrvSdnnMs ?? $shen_ai->hrv_sdnn_ms ?? $report->hrvSdnnMs ?? 0);
        $bmiValue = getMetricValue($shen_ai->bmi ?? $report->bmi ?? 0);
        $bmrValue = getMetricValue($shen_ai->healthIndices->basalMetabolicRate ?? $shen_ai->basalMetabolicRate ?? $report->healthIndices?->basalMetabolicRate ?? 0);
        $tdeeValue = getMetricValue($shen_ai->healthIndices->totalDailyEnergyExpenditure ?? $shen_ai->totalDailyEnergyExpenditure ?? $report->healthIndices?->totalDailyEnergyExpenditure ?? 0);

        $pctDev = function ($value, $target) {
            if (empty($target) || $target == 0) {
                return '-';
            }
            $pct = (($value - $target) / $target) * 100;
            return ($pct >= 0 ? '+' : '') . round($pct) . '%';
        };

        $wellnessStatus = $wellnessScore >= 70
            ? ['Normal', 'status-normal']
            : ($wellnessScore >= 45 ? ['Needs Attention', 'status-attention'] : ['Low', 'status-low']);

        $hrvStatus = $hrvValue >= 70
            ? ['Normal', 'status-normal']
            : ['Low', 'status-low'];

        if ($bmiValue >= 18.5 && $bmiValue <= 24.9) {
            $bmiStatus = ['Normal', 'status-normal'];
        } elseif ($bmiValue > 24.9) {
            $bmiStatus = ['High', 'status-high'];
        } else {
            $bmiStatus = ['Low', 'status-low'];
        }

        $bmrStatus = ['Normal', 'status-normal'];
        $tdeeStatus = ['Normal', 'status-normal'];

        $summaryRows = [
            [
                'icon' => asset('/storage/uploads/wellness.png'),
                'name' => 'Wellness Score',
                'value' => number_format($wellnessScore, 2),
                'unit' => '-',
                'deviation' => $pctDev($wellnessScore, 47.5),
                'status' => $wellnessStatus,
            ],
            [
                'icon' => asset('/storage/uploads/hrv.png'),
                'name' => 'HRV (Heart Rate Variability)',
                'value' => round($hrvValue),
                'unit' => 'ms',
                'deviation' => $pctDev($hrvValue, 74),
                'status' => $hrvStatus,
            ],
            [
                'icon' => asset('/storage/uploads/bmi.png'),
                'name' => 'BMI',
                'value' => number_format($bmiValue, 1),
                'unit' => '-',
                'deviation' => $pctDev($bmiValue, 25),
                'status' => $bmiStatus,
            ],
            [
                'icon' => asset('/storage/uploads/bmr.png'),
                'name' => 'BMR (Kcal)',
                'value' => number_format($bmrValue, 1) . ' Kcal',
                'unit' => 'Kcal',
                'deviation' => $pctDev($bmrValue, 1335),
                'status' => $bmrStatus,
            ],
            [
                'icon' => asset('/storage/uploads/tdee.png'),
                'name' => 'TDEE (Kcal)',
                'value' => number_format($tdeeValue, 1) . ' Kcal',
                'unit' => 'Kcal',
                'deviation' => $pctDev($tdeeValue, 1805),
                'status' => $tdeeStatus,
            ],
        ];
    @endphp

    <div class="header">
        <div class="header-inner">
            <div class="logo">
                <img src="{{ asset('/storage/uploads/vital_scan_report_pdf_company_logo.png') }}"
                    style="width:50px;height:70px" />
            </div>
            <div class="title">Health Check Results</div>
            <div class="clear"></div>
        </div>
    </div>
    <div class="page">



        <div class="p4-table-head-wrap">
            <table class="p4-table">
                <thead>
                    <tr>
                        <th style="width:40%">Measurement Results</th>
                        <th class="center" style="width:14%">Value</th>
                        <th class="center" style="width:10%">Unit</th>
                        <th class="center" style="width:16%">Percentage Deviation</th>
                        <th class="center" style="width:20%">Status</th>
                    </tr>
                </thead>
            </table>
        </div>

        <div class="p4-table-body-wrap">
            <table class="p4-table">
                <tbody>
                    @foreach ($summaryRows as $row)
                        <tr>
                            <td style="width:40%; white-space: nowrap;">
                                <img class="p4-metric-icon" src="{{ $row['icon'] }}" alt="" />
                                <span class="p4-metric-name">{{ $row['name'] }}</span>
                            </td>
                            <td class="center" style="width:14%">{{ $row['value'] }}</td>
                            <td class="center" style="width:10%">{{ $row['unit'] }}</td>
                            <td class="center" style="width:16%">{{ $row['deviation'] }}</td>
                            <td class="center" style="width:20%">
                                <span class="status-badge {{ $row['status'][1] }}">{{ $row['status'][0] }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @php
            /**
             * Clean matched-condition lines for PDF (never show raw JSON).
             * Handles: arrays, JSON strings, and "Label {json}" leftovers.
             */
            $cleanMatchedLine = function ($mc) use (&$cleanMatchedLine) {
                if (is_object($mc)) {
                    $mc = json_decode(json_encode($mc), true) ?: [];
                }

                $compose = function ($label, $result, $unit, $range) {
                    $label = trim((string) $label);
                    $unit = trim((string) $unit);
                    $range = trim((string) $range);
                    $parts = [];
                    if ($label !== '') {
                        $parts[] = $label;
                    }
                    if ($result !== null && $result !== '') {
                        $value = trim((string) $result);
                        if ($unit !== '' && !in_array(strtolower($unit), ['n/a', '-', '/', '/ '], true)) {
                            $value .= ' ' . $unit;
                        }
                        $parts[] = $value;
                    }
                    if ($range !== '' && strtolower($range) !== 'n/a') {
                        $parts[] = '(' . $range . ')';
                    }
                    return trim(implode(' ', $parts));
                };

                $decodeJson = function ($value) {
                    if (!is_string($value)) {
                        return $value;
                    }
                    $current = trim($value);
                    for ($i = 0; $i < 3; $i++) {
                        if ($current === '' || ($current[0] !== '{' && $current[0] !== '[')) {
                            return $current;
                        }
                        $decoded = json_decode($current, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            return $current;
                        }
                        if (is_array($decoded)) {
                            return $decoded;
                        }
                        if (is_string($decoded)) {
                            $current = trim($decoded);
                            continue;
                        }
                        return $current;
                    }
                    return $current;
                };

                if (is_string($mc)) {
                    $text = trim($mc);
                    if ($text === '') {
                        return '';
                    }

                    // Exact leftover format from screenshot: Label {"name":"...","result":"..."}
                    if (preg_match('/^(.*?)\s*(\{[\\s\\S]*\})$/', $text, $m)) {
                        $decoded = $decodeJson($m[2]);
                        if (is_array($decoded)) {
                            $label = trim($m[1]);
                            if ($label === '' && !empty($decoded['name'])) {
                                $label = ucwords((string) $decoded['name']);
                            }
                            return $compose(
                                $label,
                                $decoded['result'] ?? $decoded['value'] ?? null,
                                $decoded['unit'] ?? '',
                                $decoded['normal_range'] ?? ''
                            );
                        }
                    }

                    $decoded = $decodeJson($text);
                    if (is_array($decoded)) {
                        return $cleanMatchedLine($decoded);
                    }

                    // Strip any leftover JSON object text
                    $text = preg_replace('/\{[^{}]*"(?:name|result|unit|normal_range)"[^{}]*\}/', '', $text);
                    return trim((string) $text);
                }

                if (!is_array($mc)) {
                    return '';
                }

                // Metric object directly
                if (!isset($mc['matched_condition']) && (isset($mc['result']) || isset($mc['value']))) {
                    return $compose(
                        $mc['parameter_name'] ?? $mc['name'] ?? '',
                        $mc['result'] ?? $mc['value'] ?? null,
                        $mc['unit'] ?? '',
                        $mc['normal_range'] ?? ''
                    );
                }

                $paramName = trim((string) ($mc['parameter_name'] ?? ''));
                $condition = $mc['matched_condition'] ?? null;
                if (is_object($condition)) {
                    $condition = json_decode(json_encode($condition), true);
                }
                if (is_string($condition)) {
                    $condition = $decodeJson($condition);
                }

                if (is_array($condition)) {
                    if ($paramName === '' && !empty($condition['name'])) {
                        $paramName = ucwords((string) $condition['name']);
                    }
                    return $compose(
                        $paramName,
                        $condition['result'] ?? $condition['value'] ?? null,
                        $condition['unit'] ?? '',
                        $condition['normal_range'] ?? ''
                    );
                }

                $condText = is_scalar($condition) ? trim((string) $condition) : '';
                if ($paramName !== '' && $condText !== '') {
                    return $cleanMatchedLine(trim($paramName . ' ' . $condText));
                }
                return $paramName !== '' ? $paramName : $cleanMatchedLine($condText);
            };

            $pdfClinicalTriggers = [];
            $sourceTriggers = [];

            if (!empty($clinicalTriggers) && is_array($clinicalTriggers)) {
                $sourceTriggers = $clinicalTriggers;
            } else {
                $toArray = function ($value) {
                    if (empty($value)) {
                        return [];
                    }
                    if (is_string($value)) {
                        $decoded = json_decode($value, true);
                        return is_array($decoded) ? $decoded : [];
                    }
                    if (is_object($value)) {
                        return json_decode(json_encode($value), true) ?: [];
                    }
                    return is_array($value) ? $value : [];
                };

                $sData = $toArray($senoclock_ai_response ?? null);
                $shenData = $toArray($shen_ai ?? null);
                $reportArr = $toArray($report ?? null);

                foreach ([
                    $sData['data']['trigger'] ?? null,
                    $sData['trigger'] ?? null,
                    $sData['data']['triggers'] ?? null,
                    $sData['triggers'] ?? null,
                    $shenData['data']['trigger'] ?? null,
                    $shenData['trigger'] ?? null,
                    $shenData['triggers'] ?? null,
                    $reportArr['trigger'] ?? null,
                ] as $candidate) {
                    if (!empty($candidate) && is_array($candidate)) {
                        $sourceTriggers = $candidate;
                        break;
                    }
                }
            }

            foreach ($sourceTriggers as $trigger) {
                if (!is_array($trigger)) {
                    continue;
                }

                $icon = $trigger['icon'] ?? 'wellness.png';
                $icon = basename(str_replace('\\', '/', (string) $icon));
                if ($icon === '' || $icon === 'uploads') {
                    $icon = 'wellness.png';
                }

                $rawConditions = $trigger['matched_conditions'] ?? [];
                // Raw senoclock trigger shape
                if (empty($rawConditions) && isset($trigger['trigger_name'])) {
                    // keep empty; title/description come from trigger fields below
                }

                $cleanConditions = [];
                if (is_array($rawConditions)) {
                    foreach ($rawConditions as $mc) {
                        // Prefer structured fields from controller; otherwise parse raw object/JSON/string
                        $normalized = null;
                        if (is_array($mc) && isset($mc['name']) && array_key_exists('result', $mc) && array_key_exists('unit', $mc) && array_key_exists('normal_range', $mc) && !isset($mc['matched_condition'])) {
                            $normalized = $mc;
                        } else {
                            // Reuse string cleaner output only as a last-resort label source; parse fields below
                            $raw = $mc;
                        }

                        if ($normalized === null) {
                            if (is_object($mc)) {
                                $mc = json_decode(json_encode($mc), true) ?: [];
                            }

                            $fallbackName = '';
                            $payload = null;

                            if (is_string($mc)) {
                                $trimmed = trim($mc);
                                if (preg_match('/^(.*?)\s*(\{[\s\S]*\})$/u', $trimmed, $mm)) {
                                    $fallbackName = trim($mm[1]);
                                    $obj = trim($mm[2]);
                                } else {
                                    $obj = $trimmed;
                                }
                                $decoded = json_decode($obj, true);
                                if (!is_array($decoded) && str_starts_with($obj, '{') && str_contains($obj, "'")) {
                                    $decoded = json_decode(preg_replace('/(?<!\\\\)\'/', '"', $obj), true);
                                }
                                if (!is_array($decoded) && str_starts_with($obj, '{')) {
                                    $decoded = [];
                                    foreach (['name', 'parameter_name', 'result', 'value', 'unit', 'normal_range', 'range'] as $key) {
                                        if (preg_match('/[\'"]' . preg_quote($key, '/') . '[\'"]\s*:\s*[\'"]([^\'"]*)[\'"]/u', $obj, $km)) {
                                            $decoded[$key] = $km[1];
                                        }
                                    }
                                    if (empty($decoded)) {
                                        $decoded = null;
                                    }
                                }
                                $payload = is_array($decoded) ? $decoded : null;
                            } elseif (is_array($mc)) {
                                $fallbackName = trim((string) ($mc['parameter_name'] ?? $mc['name'] ?? ''));
                                $condition = $mc['matched_condition'] ?? null;
                                if (is_object($condition)) {
                                    $condition = json_decode(json_encode($condition), true);
                                }
                                if (is_string($condition)) {
                                    $condTrim = trim($condition);
                                    if (preg_match('/^(.*?)\s*(\{[\s\S]*\})$/u', $condTrim, $mm)) {
                                        if ($fallbackName === '') {
                                            $fallbackName = trim($mm[1]);
                                        }
                                        $condTrim = trim($mm[2]);
                                    }
                                    $decoded = json_decode($condTrim, true);
                                    if (!is_array($decoded) && str_starts_with($condTrim, '{') && str_contains($condTrim, "'")) {
                                        $decoded = json_decode(preg_replace('/(?<!\\\\)\'/', '"', $condTrim), true);
                                    }
                                    if (!is_array($decoded) && str_starts_with($condTrim, '{')) {
                                        $decoded = [];
                                        foreach (['name', 'parameter_name', 'result', 'value', 'unit', 'normal_range', 'range'] as $key) {
                                            if (preg_match('/[\'"]' . preg_quote($key, '/') . '[\'"]\s*:\s*[\'"]([^\'"]*)[\'"]/u', $condTrim, $km)) {
                                                $decoded[$key] = $km[1];
                                            }
                                        }
                                        if (empty($decoded)) {
                                            $decoded = null;
                                        }
                                    }
                                    $payload = is_array($decoded) ? $decoded : null;
                                } elseif (is_array($condition)) {
                                    $payload = $condition;
                                } else {
                                    $payload = $mc;
                                }
                            }

                            if (!is_array($payload)) {
                                continue;
                            }

                            $name = trim((string) ($fallbackName !== '' ? $fallbackName : ($payload['parameter_name'] ?? $payload['name'] ?? '')));
                            if ($name === '') {
                                $name = 'Parameter';
                            }

                            $dash = function ($v) {
                                if ($v === null) {
                                    return '—';
                                }
                                $t = trim((string) $v);
                                return ($t === '' || $t === '-' || $t === '/' || strtolower($t) === 'n/a' || strtolower($t) === 'null') ? '—' : $t;
                            };

                            $normalized = [
                                'name' => $name,
                                'result' => $dash($payload['result'] ?? $payload['value'] ?? null),
                                'unit' => $dash($payload['unit'] ?? null),
                                'normal_range' => $dash($payload['normal_range'] ?? $payload['range'] ?? null),
                            ];
                        } else {
                            $dash = function ($v) {
                                if ($v === null) {
                                    return '—';
                                }
                                $t = trim((string) $v);
                                return ($t === '' || $t === '-' || $t === '/' || strtolower($t) === 'n/a' || strtolower($t) === 'null') ? '—' : $t;
                            };
                            $normalized = [
                                'name' => trim((string) ($normalized['name'] ?? 'Parameter')) ?: 'Parameter',
                                'result' => $dash($normalized['result'] ?? null),
                                'unit' => $dash($normalized['unit'] ?? null),
                                'normal_range' => $dash($normalized['normal_range'] ?? null),
                            ];
                        }

                        $blob = $normalized['name'] . $normalized['result'] . $normalized['unit'] . $normalized['normal_range'];
                        if (str_contains($blob, '{') || str_contains($blob, "'name'") || str_contains($blob, '"name"')) {
                            continue;
                        }
                        $cleanConditions[] = $normalized;
                    }
                }

                $pdfClinicalTriggers[] = [
                    'title' => $trigger['title'] ?? $trigger['trigger_name'] ?? 'Trigger',
                    'description' => $trigger['description'] ?? $trigger['trigger_description'] ?? '',
                    'matched_conditions' => $cleanConditions,
                    'icon' => $icon,
                ];
            }

            $pdfClinicalTriggers = array_slice($pdfClinicalTriggers, 0, 5);
        @endphp

        @if(count($pdfClinicalTriggers) > 0)
        <div class="trigger-wrap">
            @foreach($pdfClinicalTriggers as $index => $trigger)
            <div class="trigger-card {{ $index % 2 != 0 ? 'right' : '' }}" style="page-break-inside: avoid; break-inside: avoid;">
                <div class="trigger-title">
                    <img src="{{ asset('/storage/uploads/' . $trigger['icon']) }}" alt="" />
                    {{ $trigger['title'] }}
                </div>
                <div class="trigger-desc">
                    {{ $trigger['description'] }}
                </div>
                <div class="trigger-matched">Matched Conditions</div>
                <ul class="trigger-list">
                    @foreach($trigger['matched_conditions'] as $mc)
                        <li>
                            <div class="mc-name">{{ is_array($mc) ? ($mc['name'] ?? 'Parameter') : $mc }}</div>
                            @if(is_array($mc))
                                @php
                                    $mcResult = trim((string) ($mc['result'] ?? ''));
                                    if ($mcResult === '' || strtolower($mcResult) === 'n/a' || $mcResult === 'null') {
                                        $mcResult = '—';
                                    }
                                    $mcUnit = trim((string) ($mc['unit'] ?? ''));
                                    $mcUnitUsable = $mcUnit !== ''
                                        && $mcUnit !== '—'
                                        && $mcUnit !== '-'
                                        && $mcUnit !== '/'
                                        && strtolower($mcUnit) !== 'n/a'
                                        && strtolower($mcUnit) !== 'null';
                                    $mcResultLine = $mcUnitUsable ? ($mcResult . ' ' . $mcUnit) : $mcResult;
                                    $mcRange = trim((string) ($mc['normal_range'] ?? ''));
                                    if ($mcRange === '' || strtolower($mcRange) === 'n/a' || $mcRange === 'null') {
                                        $mcRange = '—';
                                    }
                                @endphp
                                <div class="mc-detail">Result: {{ $mcResultLine }}</div>
                                <div class="mc-detail">Normal Range: {{ $mcRange }}</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
            @if($index % 2 != 0)
                <div class="clearfix"></div>
            @endif
            @endforeach
            @if(count($pdfClinicalTriggers) % 2 != 0)
                <div class="clearfix"></div>
            @endif
        </div>
        @endif

        <div class="disclaimer" style="margin-top:22px">
            This report is intended for informational purposes only and does not constitute medical advice. It is not a substitute for professional medical judgment, diagnosis, or treatment. Always consult with a qualified healthcare professional regarding any medical concerns or decisions.
        </div>

    </div><!-- end page 4 -->

</body>

</html>
