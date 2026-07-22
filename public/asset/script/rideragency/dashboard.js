/* ==========================================================
   RIDER AGENCY DASHBOARD – FINAL
========================================================== */

document.addEventListener('DOMContentLoaded', function () {

    console.log('DOM fully loaded');

    if (typeof window.domainUrl === 'undefined') {
        console.error('domainUrl is undefined');
        return;
    }

    const agencyDropdown     = document.getElementById('agencyDropdown');
    const agencyTypeDropdown = document.getElementById('agencyTypeDropdown');

    /* ===============================
       LOAD AGENCY DROPDOWN
    =============================== */
    if (agencyDropdown) {

        agencyDropdown.innerHTML =
            '<option disabled selected>Loading agencies...</option>';

        fetch(window.domainUrl + 'agencies-dropdown', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(res => {

            if (!res.status || !Array.isArray(res.data)) {
                agencyDropdown.innerHTML =
                    '<option disabled>No agencies found</option>';
                return;
            }

            let options = '<option selected disabled>Select Agency</option>';

            res.data.forEach(agency => {
                options += `<option value="${agency.id}">
                                ${agency.agency_name}
                            </option>`;
            });

            agencyDropdown.innerHTML = options;
        })
        .catch(err => {
            console.error('Agency dropdown error:', err);
            agencyDropdown.innerHTML =
                '<option disabled>Error loading agencies</option>';
        });
    }

    /* ===============================
       LOAD AGENCY TYPE DROPDOWN
    =============================== */
    if (agencyTypeDropdown) {

        agencyTypeDropdown.innerHTML =
            '<option disabled selected>Loading types...</option>';

        fetch(window.domainUrl + 'getAgencyType', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(res => {

            let options = '<option selected disabled>Select Type</option>';
            let hasData = false;

            if (res.status && Array.isArray(res.data)) {
                res.data.forEach(item => {
                    if (item.id && item.name) {
                        hasData = true;
                        options += `<option value="${item.id}">
                                        ${item.name}
                                    </option>`;
                    }
                });
            }

            if (!hasData) {
                agencyTypeDropdown.innerHTML =
                    '<option disabled>No types found</option>';
                return;
            }

            agencyTypeDropdown.innerHTML = options;
        })
        .catch(err => {
            console.error('Agency type error:', err);
            agencyTypeDropdown.innerHTML =
                '<option disabled>Error loading types</option>';
        });
    }

});


/* ===============================
   SHOW RESULT – API BINDING
=============================== */
function showResult() {

    const agencyId     = document.getElementById('agencyDropdown').value;
    const agencyTypeId = document.getElementById('agencyTypeDropdown').value;

    if (!agencyId || !agencyTypeId) {
        alert('Please select Agency and Agent Type');
        return;
    }

    const resultSection = document.getElementById('resultSection');
    resultSection.style.display = 'block';

    // loading placeholders
    document.getElementById('totalRiders').textContent = '—';
    document.getElementById('usedRiders').textContent = '—';
    document.getElementById('remainingRiders').textContent = '—';

    fetch('https://pt.mulkmed.com/v2/getAgencyInfo', {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            agency_id: agencyId,
            agency_type_id: agencyTypeId
        })
    })
    .then(res => res.json())
    .then(res => {

        console.log('getAgencyInfo response:', res);

        // PREPAID
        if (res.total_riders !== undefined) {
            document.getElementById('totalRiders').textContent     = res.total_riders;
            document.getElementById('usedRiders').textContent      = res.used_riders;
            document.getElementById('remainingRiders').textContent = res.remaining_riders;
        }
        // POSTPAID
        else if (res.used_riders !== undefined) {
            document.getElementById('totalRiders').textContent     = '—';
            document.getElementById('usedRiders').textContent      = res.used_riders;
            document.getElementById('remainingRiders').textContent = '—';
        }
        else {
            alert('No active subscription plan found');
        }
    })
    .catch(err => {
        console.error('getAgencyInfo error:', err);
        alert('Failed to fetch rider details');
    });
}
