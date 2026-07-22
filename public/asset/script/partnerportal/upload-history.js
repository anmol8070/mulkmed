document.addEventListener('DOMContentLoaded', function () {

    const tableBody  = document.getElementById('historyTableBody');
    const searchInput = document.getElementById('historySearch');

    /* ================= LOAD UPLOAD HISTORY ================= */

    function loadHistory() {

        const API_URL = `${domainUrl}partner/import-logs`;

        fetch(API_URL, {
            method: 'GET',
            credentials: 'include',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(response => {

            console.log('UPLOAD HISTORY RESPONSE:', response);

            if (
                !response.status ||
                !response.data ||
                !Array.isArray(response.data.data) ||
                response.data.data.length === 0
            ) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-muted">
                            No records found
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';

            response.data.data.forEach(item => {
                html += `
                    <tr>
                        <td>${item.date ?? '-'}</td>
                        <td>${item.time ?? '-'}</td>
                        <td>
                            <i class="fas fa-file-excel text-success me-1"></i>
                            ${item.file_name ?? 'N/A'}
                        </td>
                        <td>
                            <a href="javascript:void(0)"
                               class="view-link text-primary"
                               data-url="${item.view_url}">
                               View
                            </a>
                        </td>
                    </tr>
                `;
            });

            tableBody.innerHTML = html;
        })
        .catch(err => {
            console.error(err);
            tableBody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center text-danger">
                        Failed to load data
                    </td>
                </tr>
            `;
        });
    }

    loadHistory();

    /* ================= VIEW MODAL ================= */

    document.addEventListener('click', function (e) {

        const viewBtn = e.target.closest('.view-link');
        if (!viewBtn) return;

        const url = viewBtn.dataset.url;
        if (!url) return;

        const modalEl = document.getElementById('viewDetailsModal');
        const modal   = new bootstrap.Modal(modalEl);
        const modalTableBody = document.getElementById('modalTableBody');
        const modalTableHead = document.getElementById('modalTableHead');

        modalTableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted">
                    Loading...
                </td>
            </tr>
        `;

        fetch(url, {
            method: 'GET',
            credentials: 'include',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(response => {

            

            /*  SAFE NORMALIZATION (FIX) */
            const partnerType = (window.dashboardConfig.partnerType || '')
                .toString()
                .toLowerCase()
                .trim();

            const partnerViewConfig = {
                visa: {
                    headers: ['Customer Name','Mobile Number','Start Date','Validity','Service Type'],
                    renderRow: (item) => `
                        <td>${item.customer_name ?? '-'}</td>
                        <td>${item.mobile_number ?? '-'}</td>
                        <td>${item.start_date ?? '-'}</td>
                        <td>${item.validity_days ? item.validity_days : '-'}</td>
                        <td>${item.service_type ?? '-'}</td>
                    `
                },
                travel: {
                    headers: ['Customer Name','Mobile Number','Fly In Time','Fly Out Time','Service Type'],
                    renderRow: (item) => `
                        <td>${item.customer_name ?? '-'}</td>
                        <td>${item.mobile_number ?? '-'}</td>
                        <td>${item.fly_in_time ?? '-'}</td>
                        <td>${item.fly_out_time ?? '-'}</td>
                        <td>${item.service_type ?? '-'}</td>
                    `
                },
                hotel: {
                    headers: ['Customer Name','Mobile Number','Booking ID','Check In Time','Check Out Time','Service Type'],
                    renderRow: (item) => `
                        <td>${item.customer_name ?? '-'}</td>
                        <td>${item.mobile_number ?? '-'}</td>
                        <td>${item.booking_id ?? '-'}</td>
                        <td>${item.check_in_time ?? '-'}</td>
                        <td>${item.check_out_time ?? '-'}</td>
                        <td>${item.service_type ?? '-'}</td>
                    `
                }
            };

            const config = partnerViewConfig[partnerType];

            /* 🔒 GUARD (FIX) */
            if (!config) {
                console.error('Invalid partner type:', partnerType);
                modalTableBody.innerHTML = `
                    <tr>
                        <td class="text-center text-danger">
                            Unsupported partner type
                        </td>
                    </tr>
                `;
                modal.show();
                return;
            }

            /* ===== Headers ===== */
            modalTableHead.innerHTML = '';
            config.headers.forEach(h => {
                modalTableHead.innerHTML += `<th>${h}</th>`;
            });

            if (!response.status || !Array.isArray(response.data) || response.data.length === 0) {
                modalTableBody.innerHTML = `
                    <tr>
                        <td colspan="${config.headers.length}" class="text-center text-muted">
                            No records found
                        </td>
                    </tr>
                `;
                modal.show();
                return;
            }

            /* ===== Rows ===== */
            let html = '';
            response.data.forEach(item => {
                html += `<tr>${config.renderRow(item)}</tr>`;
            });

            modalTableBody.innerHTML = html;
            modal.show();
        })
        .catch(err => {
            console.error(err);
            modalTableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-danger">
                        Failed to load data
                    </td>
                </tr>
            `;
            modal.show();
        });
    });

    /* ================= SEARCH ================= */

    searchInput.addEventListener('keyup', function () {
        const value = this.value.toLowerCase();
        document.querySelectorAll('#historyTableBody tr')
            .forEach(row => {
                row.style.display =
                    row.innerText.toLowerCase().includes(value) ? '' : 'none';
            });
    });

});
