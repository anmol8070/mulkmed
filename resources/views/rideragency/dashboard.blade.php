@extends('include.app')

@section('header')
<link rel="stylesheet" href="{{ asset('asset/css/rideragency/dashboard.css') }}">

<!-- ✅ CSRF TOKEN (VERY IMPORTANT) -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<script>
    window.domainUrl = "{{ url('/') }}/";
</script>
@endsection

@section('content')
<div class="dashboard-content">

    <!-- TOTAL AGENCIES CARD -->
    <div class="card stats-card">
        <div class="stats-left">
            <div class="stats-icon">
                <i class="far fa-building"></i>
            </div>
            <span>Total Agencies</span>
        </div>
        <div class="stats-value" id="totalAgencyCount">—</div>
    </div>

    <!-- FILTER CARD -->
    <div class="card usage-card">
        <div class="card-title">Partners Usage Tracking</div>
        <div class="card-subtitle">
            Monitor healthcare rider service utilization
        </div>

        <div class="divider"></div>

        <div class="filter-row">
            <div>
                <label>Agency Name</label>
                <select id="agencyDropdown">
                    <option selected disabled>Loading agencies...</option>
                </select>
            </div>

            <div>
                <label>Agent Type</label>
                <select id="agencyTypeDropdown">
                    <option selected disabled>Loading types...</option>
                </select>
            </div>

            <button type="button" class="btn-show" onclick="showResult()">Show</button>
        </div>
    </div>

    <!-- RESULT SECTION -->
    <div id="resultSection" class="result-wrapper" style="display:none;">
        <div class="result-cards">
            <div class="result-card">
                <small>Total Riders</small>
                <h2 id="totalRiders">—</h2>
            </div>

            <div class="result-card">
                <small>Used Riders</small>
                <h2 id="usedRiders" style="color:#ef4444;">—</h2>
            </div>

            <div class="result-card">
                <small>Remaining Riders</small>
                <h2 id="remainingRiders" style="color:#0ea5e9;">—</h2>
            </div>
        </div>

        <div class="validity">
            <i class="far fa-calendar"></i>
            Valid Till 31<sup>st</sup> Jan 2026
        </div>
    </div>

</div>

{{-- INLINE SCRIPT --}}
<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ===============================
       LOAD TOTAL AGENCY COUNT
    =============================== */
    fetch(window.domainUrl + 'getAgencyCount', {
        headers: { 'Accept': 'application/json' }
    })
    .then(res => res.json())
    .then(res => {
        document.getElementById('totalAgencyCount').textContent =
            (res.status && typeof res.agency_count === 'number')
                ? res.agency_count
                : '0';
    })
    .catch(() => {
        document.getElementById('totalAgencyCount').textContent = '0';
    });


    /* ===============================
       AGENCY DROPDOWN
    =============================== */
    fetch(window.domainUrl + 'agencies-dropdown', {
        headers: { 'Accept': 'application/json' }
    })
    .then(res => res.json())
    .then(res => {

        if (!res.status || !Array.isArray(res.data)) {
            document.getElementById('agencyDropdown').innerHTML =
                '<option disabled>No agencies found</option>';
            return;
        }

        let options = '<option selected disabled>Select Agency</option>';
        res.data.forEach(a => {
            options += `<option value="${a.id}">${a.agency_name}</option>`;
        });

        document.getElementById('agencyDropdown').innerHTML = options;
    });


    /* ===============================
       AGENCY TYPE DROPDOWN
    =============================== */
    fetch(window.domainUrl + 'getAgencyType', {
        headers: { 'Accept': 'application/json' }
    })
    .then(res => res.json())
    .then(res => {

        if (!res.success || !Array.isArray(res.agency_types)) {
            document.getElementById('agencyTypeDropdown').innerHTML =
                '<option disabled>No types found</option>';
            return;
        }

        let options = '<option selected disabled>Select Type</option>';
        res.agency_types.forEach(t => {
            options += `<option value="${t.id}">${t.name}</option>`;
        });

        document.getElementById('agencyTypeDropdown').innerHTML = options;
    });

});


/* ===============================
   SHOW RESULT – API CALL
=============================== */
function showResult() {

    const agencyId     = document.getElementById('agencyDropdown').value;
    const agencyTypeId = document.getElementById('agencyTypeDropdown').value;

    if (!agencyId || !agencyTypeId) {
        alert('Please select Agency and Agent Type');
        return;
    }

    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        .getAttribute('content');

    document.getElementById('resultSection').style.display = 'block';

    fetch(window.domainUrl + 'getAgencyInfo', {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            agency_id: agencyId,
            agency_type_id: agencyTypeId
        })
    })
    .then(res => res.json())
    .then(res => {

        document.getElementById('totalRiders').textContent =
            res.total_riders ?? '—';

        document.getElementById('usedRiders').textContent =
            res.used_riders ?? '—';

        document.getElementById('remainingRiders').textContent =
            res.remaining_riders ?? '—';
    })
    .catch(err => {
        console.error(err);
        alert('Failed to fetch rider data');
    });
}
</script>
@endsection
