<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SenoClock AI – Test Classification</title>
    <link rel="stylesheet" href="{{ asset('senoclock/css/test-classification.css') }}">
</head>
<body>
    <div class="page">
        <header class="page-header">
            <h1>SenoClock AI – Test Classification</h1>
            <p class="subtitle">Login and trigger the mulkmed classification API, then view the response below.</p>
        </header>

        <section class="card" id="login-section">
            <h2>1. Authentication</h2>
            <p class="hint">SenoClock API: <code>{{ $loginApiUrl }}</code></p>
            <div class="actions">
                <button type="button" id="btn-login" class="btn btn-secondary">Test Login</button>
            </div>
            <div id="login-result" class="result-panel hidden"></div>
        </section>

        <section class="card" id="classification-section">
            <h2>2. Trigger Classification</h2>
            <p class="hint">SenoClock API: <code>{{ $classificationApiUrl }}</code></p>
            <form id="classification-form">
                <div class="form-grid form-grid--3">
                    <div class="field">
                        <label>age</label>
                        <input type="number" data-payload-key="age" data-type="number" step="any">
                    </div>
                    <div class="field">
                        <label>sex</label>
                        <select data-payload-key="sex">
                            <option value="" selected></option>
                            <option value="male">male</option>
                            <option value="female">female</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>scan_date</label>
                        <input type="text" data-payload-key="scan_date">
                    </div>
                    <div class="field">
                        <label>patient_name</label>
                        <input type="text" data-payload-key="patient_name">
                    </div>
                    <div class="field">
                        <label>scenario_label</label>
                        <input type="text" data-payload-key="scenario_label">
                    </div>
                    <div class="field">
                        <label>Body Fat %</label>
                        <input type="number" data-payload-key="Body Fat %" data-type="number" step="any">
                    </div>
                    <div class="field">
                        <label>NAFLD Risk</label>
                        <input type="text" data-payload-key="NAFLD Risk">
                    </div>
                    <div class="field">
                        <label>Stress Index</label>
                        <input type="number" data-payload-key="Stress Index" data-type="number" step="any">
                    </div>
                    <div class="field">
                        <label>Vascular Age</label>
                        <input type="number" data-payload-key="Vascular Age" data-type="number" step="any">
                    </div>
                    <div class="field">
                        <label>Diabetes Risk</label>
                        <input type="text" data-payload-key="Diabetes Risk">
                    </div>
                    <div class="field">
                        <label>Breathing Rate</label>
                        <input type="number" data-payload-key="Breathing Rate" data-type="number" step="any">
                    </div>
                    <div class="field">
                        <label>Wellness Score</label>
                        <input type="number" data-payload-key="Wellness Score" data-type="number" step="any">
                    </div>
                    <div class="field">
                        <label>Heart Rate (HR)</label>
                        <input type="number" data-payload-key="Heart Rate (HR)" data-type="number" step="any">
                    </div>
                    <div class="field">
                        <label>Cardiac Workload</label>
                        <input type="number" data-payload-key="Cardiac Workload" data-type="number" step="any">
                    </div>
                    <div class="field">
                        <label>Hypertension Risk</label>
                        <input type="text" data-payload-key="Hypertension Risk">
                    </div>
                    <div class="field">
                        <label>Conicity Index (CI)</label>
                        <input type="number" data-payload-key="Conicity Index (CI)" data-type="number" step="any">
                    </div>
                    <div class="field">
                        <label>Body Mass Index (BMI)</label>
                        <input type="number" data-payload-key="Body Mass Index (BMI)" data-type="number" step="any">
                    </div>
                    <div class="field">
                        <label>Parasympathetic Activity</label>
                        <input type="number" data-payload-key="Parasympathetic Activity" data-type="number" step="any">
                    </div>
                    <div class="field">
                        <label>A Body Shape Index (ABSI)</label>
                        <input type="number" data-payload-key="A Body Shape Index (ABSI)" data-type="number" step="any">
                    </div>
                    <div class="field">
                        <label>Basal Metabolic Rate (BMR)</label>
                        <input type="number" data-payload-key="Basal Metabolic Rate (BMR)" data-type="number" step="any">
                    </div>
                    <div class="field">
                        <label>Body Roundness Index (BRI)</label>
                        <input type="number" data-payload-key="Body Roundness Index (BRI)" data-type="number" step="any">
                    </div>
                    <div class="field">
                        <label>Cardiovascular Disease Risk</label>
                        <input type="number" data-payload-key="Cardiovascular Disease Risk" data-type="number" step="any">
                    </div>
                    <div class="field">
                        <label>Hard and Fatal Events Risks</label>
                        <input type="number" data-payload-key="Hard and Fatal Events Risks" data-type="number" step="any">
                    </div>
                    <div class="field">
                        <label>Heart Rate Variability (HRV)</label>
                        <input type="number" data-payload-key="Heart Rate Variability (HRV)" data-type="number" step="any">
                    </div>
                    <div class="field">
                        <label>Waist-to-Height Ratio (WHtR)</label>
                        <input type="number" data-payload-key="Waist-to-Height Ratio (WHtR)" data-type="number" step="any">
                    </div>
                    <div class="field">
                        <label>Total Daily Energy Expenditure (TDEE)</label>
                        <input type="number" data-payload-key="Total Daily Energy Expenditure (TDEE)" data-type="number" step="any">
                    </div>
                    <div class="field">
                        <label>Cardiovascular Risk Score (Framingham FRS)</label>
                        <input type="number" data-payload-key="Cardiovascular Risk Score (Framingham FRS)" data-type="number" step="any">
                    </div>
                </div>

                <h3 class="section-title">Blood Pressure</h3>
                <div class="form-grid form-grid--2">
                    <div class="field">
                        <label>systolic</label>
                        <input type="number" data-bp-key="systolic" data-type="number" step="any">
                    </div>
                    <div class="field">
                        <label>diastolic</label>
                        <input type="number" data-bp-key="diastolic" data-type="number" step="any">
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" id="btn-classify" class="btn btn-primary">Test Classification</button>
                </div>
            </form>
        </section>

        <section class="card" id="response-section">
            <h2>3. Response</h2>
            <div id="response-status" class="status-badge hidden"></div>
            <pre id="response-output" class="response-output">No request sent yet.</pre>
        </section>
    </div>

    <script>
        window.SENOCLOCK_TEST = {
            loginApiUrl: @json($loginApiUrl),
            classificationApiUrl: @json($classificationApiUrl),
            auth: {
                email: @json(config('services.senoclock.email')),
                password: @json(config('services.senoclock.password')),
            },
        };
    </script>
    <script src="{{ asset('senoclock/js/test-classification.js') }}?v=1.3"></script>
</body>
</html>
