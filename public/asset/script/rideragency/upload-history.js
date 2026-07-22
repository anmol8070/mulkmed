/* ==========================================================
   UPLOAD HISTORY PAGE JS (FINAL – FILTER + TABLE + MODAL)
========================================================== */

document.addEventListener('DOMContentLoaded', () => {

    console.log('upload-history.js loaded');
    console.log('domainUrl:', window.domainUrl);

    /* ===============================
       ELEMENT REFERENCES
    =============================== */
    const agencyDropdown = document.getElementById('agencyDropdown');
    const agencyTypeDropdown = document.getElementById('agencyTypeDropdown');
    const dateInput = document.querySelector('input[type="date"]');
    const applyFilterBtn = document.getElementById('applyFilterBtn');

    const tbody = document.querySelector('.table-container tbody');

    const modal = document.getElementById('detailsModal');
    const closeModalBtn = document.querySelector('.close-modal');

    const modalAgencyName = document.getElementById('modalAgencyName');
    const modalAgencyTypeText = document.getElementById('modalAgencyTypeText');
    const modalTbody = document.querySelector('.modal-body-custom tbody');

     /* ===============================
       PARTNER VIEW CONFIG
    =============================== */
    const partnerViewConfig = {
        visa: {
            headers: ['Customer Name','Mobile Number','Start Date','Validity','Service Type'],
            renderRow: item => `
                <td>${item.customer_name ?? '-'}</td>
                <td>${item.mobile_number ?? '-'}</td>
                <td>${item.start_date ?? '-'}</td>
                <td>${item.validity_days ? item.validity_days : '-'}</td>
                <td>${item.service_type ?? '-'}</td>
            `
        },
        travel: {
            headers: ['Customer Name','Mobile Number','Fly In','Fly Out','Service Type'],
            renderRow: item => `
                <td>${item.customer_name ?? '-'}</td>
                <td>${item.mobile_number ?? '-'}</td>
                <td>${item.fly_in_time ?? '-'}</td>
                <td>${item.fly_out_time ?? '-'}</td>
                <td>${item.service_type ?? '-'}</td>
            `
        },
        hotel: {
            headers: ['Customer Name','Mobile Number','Booking ID','Check In','Check Out','Service Type'],
            renderRow: item => `
                <td>${item.customer_name ?? '-'}</td>
                <td>${item.mobile_number ?? '-'}</td>
                <td>${item.booking_id ?? '-'}</td>
                <td>${item.check_in_time ?? '-'}</td>
                <td>${item.check_out_time ?? '-'}</td>
                <td>${item.service_type ?? '-'}</td>
            `
        }
    };

    /* ===============================
       LOAD AGENCY DROPDOWN
    =============================== */
    if (agencyDropdown) {
        agencyDropdown.innerHTML =
            '<option disabled selected>Loading agencies...</option>';

        fetch(window.domainUrl + 'agencies-dropdown', {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(res => {

            if (!res.status || !Array.isArray(res.data)) {
                agencyDropdown.innerHTML =
                    '<option disabled>No agencies found</option>';
                return;
            }

            let options = '<option value="">Select Agency</option>';

            res.data.forEach(item => {
                options += `
                    <option value="${item.id}">
                        ${item.agency_name}
                    </option>`;
            });

            agencyDropdown.innerHTML = options;
        })
        .catch(() => {
            agencyDropdown.innerHTML =
                '<option disabled>Error loading agencies</option>';
        });
    }

    /* ===============================
       LOAD AGENCY TYPE DROPDOWN (FIXED ONLY)
    =============================== */
    if (agencyTypeDropdown) {

        agencyTypeDropdown.innerHTML =
            '<option disabled selected>Loading types...</option>';

        fetch(window.domainUrl + 'getAgencyType', {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(res => {

            console.log('Agency type API response:', res);

            let options = '<option value="">Select Type</option>';
            let hasData = false;

            // Case 1: { status: true, data: [] }
            if (res?.status === true && Array.isArray(res.data)) {
                res.data.forEach(item => {
                    const value = item.id ?? item.name;
                    const label = item.name || item.agency_type || item.type;
                    if (label) {
                        hasData = true;
                        options += `<option value="${value}">${label}</option>`;
                    }
                });
            }
            // Case 2: { success: true, agency_types: [] }
            else if (res?.success === true && Array.isArray(res.agency_types)) {
                res.agency_types.forEach(item => {
                    hasData = true;
                    options += `<option value="${item.id}">${item.name}</option>`;
                });
            }
            // Case 3: { data: { data: [] } }
            else if (Array.isArray(res?.data?.data)) {
                res.data.data.forEach(item => {
                    hasData = true;
                    options += `<option value="${item.id}">${item.name}</option>`;
                });
            }
            // Case 4: { types: [] }
            else if (Array.isArray(res?.types)) {
                res.types.forEach(type => {
                    hasData = true;
                    options += `<option value="${type}">${type}</option>`;
                });
            }

            if (!hasData) {
                agencyTypeDropdown.innerHTML =
                    '<option disabled>No types found</option>';
                return;
            }

            agencyTypeDropdown.innerHTML = options;
        })
        .catch(() => {
            agencyTypeDropdown.innerHTML =
                '<option disabled>Error loading types</option>';
        });
    }

    /* ===============================
       LOAD IMPORT LOGS (FILTERED)
    =============================== */
    async function loadImportLogs(agencyId = '', agencyType = '', date = '') {
        try {
            let url = `${window.domainUrl}admin/import-logs?`;
            if (agencyId) url += `agency_id=${agencyId}&`;
            if (agencyType) url += `agency_type=${agencyType}&`;
            if (date) url += `date=${date}&`;

            const res = await fetch(url, {
                headers: { 'Accept': 'application/json' }
            });

            const json = await res.json();

            if (!json.status || !Array.isArray(json.data.data)) {
                tbody.innerHTML =
                    '<tr><td colspan="6">No records found</td></tr>';
                return;
            }

            tbody.innerHTML = '';

            json.data.data.forEach(log => {

                const imageUrl = log.agency_image
                    ? `${window.domainUrl}storage/uploads/${log.agency_image.split('/').pop()}`
                    : '';

                tbody.insertAdjacentHTML('beforeend', `
                    <tr>
                        <td>${log.date || '-'}</td>
                        <td>${log.time || '-'}</td>
                        <td>${log.agency_name || '-'}</td>
                        <td>
                            <span class="badge">
                                <img src="${imageUrl}"
                                     style="width:18px;height:18px;border-radius:50%;margin-right:6px;">
                                ${log.agency_type || '-'}
                            </span>
                        </td>
                        <td>${log.file_name || '-'}</td>
                        <td>
                            <div class="action-links">
                                <a href="javascript:void(0)"
                                   class="action-link view-details"
                                   data-agency="${log.agency_name}"
                                   data-type="${log.agency_type}"
                                   data-view-url="${log.view_url}">
                                   View
                                </a>
                                <span class="link-sep">|</span>
                                <a href="javascript:void(0)"
                                   class="action-link download-excel"
                                   data-log-id="${log.log_id}">
                                   Download
                                </a>
                            </div>
                        </td>
                    </tr>
                `);
            });

            bindViewEvents();

        } catch (err) {
            console.error('Load import logs failed:', err);
        }
    }

    /* ===============================
       APPLY FILTER BUTTON
    =============================== */
    if (applyFilterBtn) {
        applyFilterBtn.addEventListener('click', () => {
            loadImportLogs(
                agencyDropdown.value || '',
                agencyTypeDropdown.value || '',
                dateInput.value || ''
            );
        });
    }

  /* ===============================
       VIEW MODAL (FINAL FIX)
    =============================== */
    document.addEventListener('click', async e => {

        const btn = e.target.closest('.view-details');
        if (!btn) return;

        e.preventDefault();

        const partnerType = (btn.dataset.type || 'hotel').toLowerCase();
        const config = partnerViewConfig[partnerType] || partnerViewConfig.hotel;

        modalAgencyName.textContent = btn.dataset.agency;
        modalAgencyTypeText.textContent = partnerType.toUpperCase();

        modalTableHead.innerHTML = '';
        config.headers.forEach(h =>
            modalTableHead.insertAdjacentHTML('beforeend', `<th>${h}</th>`)
        );

        modalTbody.innerHTML =
            `<tr><td colspan="${config.headers.length}">Loading...</td></tr>`;

        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';

        try {
            const res = await fetch(btn.dataset.viewUrl, {
                credentials: 'include',
                headers: { 'Accept': 'application/json' }
            });

            const json = await res.json();
            modalTbody.innerHTML = '';

            if (!json.status || !Array.isArray(json.data)) {
                modalTbody.innerHTML =
                    `<tr><td colspan="${config.headers.length}">No data found</td></tr>`;
                return;
            }

            json.data.forEach(item => {
                modalTbody.insertAdjacentHTML('beforeend', `
                    <tr>${config.renderRow(item)}</tr>
                `);
            });

        } catch (err) {
            modalTbody.innerHTML =
                `<tr><td colspan="${config.headers.length}">Error loading data</td></tr>`;
        }
    });


    /* ===============================
   DOWNLOAD EXCEL (FINAL FIX)
=============================== */
document.addEventListener('click', e => {

    const btn = e.target.closest('.download-excel');
    if (!btn) return;

    e.preventDefault();
    e.stopPropagation(); // ✅ IMPORTANT

    const logId = btn.dataset.logId;
    if (!logId) {
        alert('Invalid log id');
        return;
    }

    const url = `${window.domainUrl}admin/downloadTouristExcel?id=${encodeURIComponent(logId)}`;

    // ✅ Simple, reliable download
    window.location.href = url;
});


    /* ===============================
       CLOSE MODAL
    =============================== */
    closeModalBtn.onclick = () => {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    };

    window.onclick = e => {
        if (e.target === modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    /* ===============================
       INITIAL LOAD
    =============================== */
    loadImportLogs();
});
