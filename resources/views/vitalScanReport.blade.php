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
        }

        .title {
            margin-left: 90px;
            font-size: 28px;
            font-weight: 800;
            text-align: right;
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
    background: #f7fbfd;   /* light background like screenshot */
    border: 1px solid #e6edf2;
    padding: 10px 12px;
    font-weight: 700;
    font-size: 13px;
    color: #0f1724;
    text-align: left;
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
    flex: 2.5; /* Measurement column wider */
    text-align: left;
  }

        .panel {
            border: 1px solid #eef3f6;
            border-radius: 8px;
            padding: 6px;
            margin-top: 6px;
        }

        .row {
            display: block;
            border-bottom: 1px solid #f1f5f8;
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
            color: #6b7280;
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

        .index-title {
            font-weight: 700;
            margin-bottom: 6px;
        }

        .index-desc {
            color: #6b7280;
            font-size: 16px;
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
            font-size: 12px;
        }

        .disclaimer {
            color: #6b7280;
            font-size: 12px;
            margin-top: 10px;
            text-align: center;
        }

        img {
            max-width: 100%;
            height: auto;
        }

        .mb-12 {
            margin-bottom: 12px;
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

    <!-- PAGE 1 -->
    <div class="page">
        <div class="header">
            <div class="header-inner">
                <div class="logo">
                    <!-- If using local file, use: <img src="{{ public_path('images/logo.png') }}" style="width:70px;height:70px" /> -->
                    <div
                        style="width:70px;height:70px;background:#fff;color:#0b78b0;border-radius:6px;font-weight:700;display:flex;align-items:center;justify-content:center">
                        MM</div>
                </div>
                <div class="title">Health Check Results</div>
                <div class="clear"></div>
            </div>
        </div>

        <div class="summary">
            <div class="left">
                <div class="meta">Name : <span>Shraddha banchhode</span></div>
                <div class="meta">DOB : 1999-04-25</div>
                <div class="meta" style="margin-top:6px">Age : 26</div>
                <div class="meta" style="margin-top:6px">Scan Date : 08/09/2025 , 23:59:28</div>
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
                    <div class="metric-title">Pulse (HR)</div>
                    <div class="metric-desc">Measures the average number of heartbeats per minute, reflecting autonomic
                        nervous system state.</div>
                </div>
                <div class="mid-col">
                    <div class="pill"><span
                            style="display:inline-block;width:10px;height:10px;background:#28a745;border-radius:50%;margin-right:6px;"></span>79.2
                    </div>
                </div>
                <div class="unit-col">bpm</div>
                <div class="right-col">60 - 100</div>
                <div class="clear-row"></div>
            </div>

            <!-- Blood Pressure -->
            <div class="row">
                <div class="left-col">
                    <div class="metric-title">Blood Pressure</div>
                    <div class="metric-desc">Blood pressure readings consist of two key numbers that indicate
                        cardiovascular health (systolic/diastolic).</div>
                </div>
                <div class="mid-col">
                    <div class="pill"><span
                            style="display:inline-block;width:10px;height:10px;background:#ffbf47;border-radius:50%;margin-right:6px;"></span>103.1
                        / 72.8</div>
                </div>
                <div class="unit-col">mmHg</div>
                <div class="right-col">SBP 90 - 120, DBP 60 - 70</div>
                <div class="clear-row"></div>
            </div>

            <!-- Heart Rate Variability -->
            <div class="row">
                <div class="left-col">
                    <div class="metric-title">Heart Rate Variability (HRV)</div>
                    <div class="metric-desc">Measures the variation in time intervals between heartbeats, reflecting
                        autonomic nervous system state.</div>
                </div>
                <div class="mid-col">
                    <div class="pill"><span
                            style="display:inline-block;width:10px;height:10px;background:#28a745;border-radius:50%;margin-right:6px;"></span>32.8
                    </div>
                </div>
                <div class="unit-col">ms</div>
                <div class="right-col">N/A*</div>
                <div class="clear-row"></div>
            </div>

            <!-- Breathing Rate -->
            <div class="row">
                <div class="left-col">
                    <div class="metric-title">Breathing Rate (BR)</div>
                    <div class="metric-desc">Counts breaths per minute and reflects respiratory status and stress level.
                    </div>
                </div>
                <div class="mid-col">
                    <div class="pill"><span
                            style="display:inline-block;width:10px;height:10px;background:#ffbf47;border-radius:50%;margin-right:6px;"></span>20.0
                    </div>
                </div>
                <div class="unit-col">bpm</div>
                <div class="right-col">12 - 20</div>
                <div class="clear-row"></div>
            </div>

            <!-- Stress Index -->
            <div class="row">
                <div class="left-col">
                    <div class="metric-title">Stress Index</div>
                    <div class="metric-desc">Indicates whether the heart is working in a stressed or relaxed manner.
                    </div>
                </div>
                <div class="mid-col">
                    <div class="pill"><span
                            style="display:inline-block;width:10px;height:10px;background:#28a745;border-radius:50%;margin-right:6px;"></span>3.2
                    </div>
                </div>
                <div class="unit-col">-</div>
                <div class="right-col">0 - 4</div>
                <div class="clear-row"></div>
            </div>

            <!-- Parasympathetic Activity -->
            <div class="row">
                <div class="left-col">
                    <div class="metric-title">Parasympathetic Activity</div>
                    <div class="metric-desc">Assesses activity of the parasympathetic nervous system responsible for
                        relaxation and recovery.</div>
                </div>
                <div class="mid-col">
                    <div class="pill"><span
                            style="display:inline-block;width:10px;height:10px;background:#28a745;border-radius:50%;margin-right:6px;"></span>50.7
                    </div>
                </div>
                <div class="unit-col">%</div>
                <div class="right-col">N/A*</div>
                <div class="clear-row"></div>
            </div>

            <!-- Cardiac Workload -->
            <div class="row">
                <div class="left-col">
                    <div class="metric-title">Cardiac Workload</div>
                    <div class="metric-desc">Indicates the work that the heart needs to do to pump blood.</div>
                </div>
                <div class="mid-col">
                    <div class="pill"><span
                            style="display:inline-block;width:10px;height:10px;background:#28a745;border-radius:50%;margin-right:6px;"></span>136
                    </div>
                </div>
                <div class="unit-col">a.u.</div>
                <div class="right-col">90 - 216</div>
                <div class="clear-row"></div>
            </div>

            <!-- BMI -->
            <div class="row">
                <div class="left-col">
                    <div class="metric-title">Body Mass Index (BMI)</div>
                    <div class="metric-desc">Body Mass Index indicates if weight is appropriate for height. Derived via
                        AI facial estimation in this report.</div>
                </div>
                <div class="mid-col">
                    <div class="pill"><span
                            style="display:inline-block;width:10px;height:10px;background:#28a745;border-radius:50%;margin-right:6px;"></span>19.2
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

        <div class="disclaimer">This report is intended for informational purposes only and does not constitute medical
            advice.</div>

    </div><!-- end page 1 -->

    <div class="page-break"></div>

    <!-- PAGE 2 (Indices 1) -->
    <div class="page">
        <div class="header">
            <div class="header-inner">
                <div class="logo">
                    <div
                        style="width:70px;height:70px;background:#fff;color:#0b78b0;border-radius:6px;font-weight:700;display:flex;align-items:center;justify-content:center">
                        MM</div>
                </div>
                <div class="title">Health Indices — part 1 of 2</div>
                <div class="clear"></div>
            </div>
        </div>

        <div class="indices">
            <div class="indices-wrap">

                <div class="index-line">
                    <div class="left">
                        <div class="index-title">Wellness Score</div>
                        <div class="index-desc">Comprehensive view of overall wellness, integrating key health and
                            metabolic factors into a single, actionable metric.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">38.7</div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title">Vascular Age</div>
                        <div class="index-desc">Estimated apparent age of blood vessels as a way of showing the overall
                            cardiovascular risk.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">-</div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title">Cardiovascular Disease Risk</div>
                        <div class="index-desc">Estimated risk of a first hard atherosclerotic cardiovascular event in
                            the next 10 years.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">-</div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title">Cardiovascular Risk Score</div>
                        <div class="index-desc">Point-based measure of cardiovascular risk in the next 10 years (e.g.,
                            Framingham).</div>
                    </div>
                    <div class="right">
                        <div class="result-box">-</div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title">Hard and Fatal Events Risks</div>
                        <div class="index-desc">Estimated risk of hard or fatal cardiovascular events in the next 10
                            years.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">-</div>
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

        <div class="disclaimer">This report is intended for informational purposes only and does not constitute medical
            advice.</div>
    </div>

    <div class="page-break"></div>

    <!-- PAGE 3 (Chronic & Body Composition) -->
    <div class="page">
        <div class="header">
            <div class="header-inner">
                <div class="logo">
                    <div
                        style="width:70px;height:70px;background:#fff;color:#0b78b0;border-radius:6px;font-weight:700;display:flex;align-items:center;justify-content:center">
                        MM</div>
                </div>
                <div class="title">Health Indices — part 2 of 2</div>
                <div class="clear"></div>
            </div>
        </div>

        <div class="indices">
            <div class="indices-wrap">

                <div class="index-line">
                    <div class="left">
                        <div class="index-title">Hypertension Risk</div>
                        <div class="index-desc">Assesses risk of high blood pressure that can lead to heart disease and
                            stroke.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">-</div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title">Diabetes Risk</div>
                        <div class="index-desc">Evaluates risk of diabetes and supports lifestyle changes to prevent or
                            delay disease.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">-</div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title">Fatty Liver Disease Risk (NAFLD)</div>
                        <div class="index-desc">Identifies risk of liver fat buildup that can lead to serious issues if
                            unmanaged.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">-</div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <!-- Body Composition group -->
                <div style="margin-top:8px;font-weight:800;color:#102027">Body Composition and Metabolic Indices</div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title">Waist-to-Height Ratio (WHtR)</div>
                        <div class="index-desc">Assesses obesity-related risks via ratio of waist circumference to
                            height.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">-<div
                                style="font-weight:600;color:#6b7280;font-size:11px;margin-top:6px">Normal 0 - 0.53
                            </div>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title">Body Fat Percentage (BFP)</div>
                        <div class="index-desc">Proportion of body fat relative to total body weight.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">-<div
                                style="font-weight:600;color:#6b7280;font-size:11px;margin-top:6px">Normal 7 - 23%
                            </div>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title">Body Roundness Index (BRI)</div>
                        <div class="index-desc">Distribution of body fat focusing on abdominal fat using waist
                            circumference and height.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">-<div
                                style="font-weight:600;color:#6b7280;font-size:11px;margin-top:6px">Normal 0 - 3.85
                            </div>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title">A Body Shape Index (ABSI)</div>
                        <div class="index-desc">Uses waist circumference, weight, height, and body fat distribution to
                            estimate risks.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">-<div
                                style="font-weight:600;color:#6b7280;font-size:11px;margin-top:6px">Normal 0 - 0.083
                            </div>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title">Conicity Index (CI)</div>
                        <div class="index-desc">Identifies visceral fat using waist circumference, weight, and height.
                        </div>
                    </div>
                    <div class="right">
                        <div class="result-box">-<div
                                style="font-weight:600;color:#6b7280;font-size:11px;margin-top:6px">Normal 0 - 1.275
                            </div>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title">Basal Metabolic Rate (BMR)</div>
                        <div class="index-desc">Calories your body needs at rest for essential functions.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">-</div>
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="index-line">
                    <div class="left">
                        <div class="index-title">Total Daily Energy Expenditure (TDEE)</div>
                        <div class="index-desc">Total daily calorie usage including BMR, activity, digestion, and other
                            functions.</div>
                    </div>
                    <div class="right">
                        <div class="result-box">-</div>
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

        <div class="disclaimer">This report is intended for informational purposes only and does not constitute medical
            advice.</div>

    </div><!-- end page 3 -->

</body>

</html>
