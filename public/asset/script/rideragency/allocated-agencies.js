document.addEventListener('DOMContentLoaded', () => {

    const searchInput = document.querySelector('.search-box input');
    const tableBody = document.getElementById('allocationTableBody');

    /* ================= PAGINATION VARIABLES ================= */
    let allAllocations = []; // Store all allocations
    let filteredAllocations = []; // Store filtered allocations
    let currentPage = 1;
    const itemsPerPage = 10;

    /* ================= FETCH RIDER ALLOCATIONS ================= */

    async function loadRiderAllocations() {
        try {
            const res = await fetch(domainUrl + 'getRiderAllocation');
            const data = await res.json();

            if (data.success && Array.isArray(data.rider_allocations)) {
                // Sort by ID descending (newest first) - new data on top
                allAllocations = data.rider_allocations.sort((a, b) => {
                    return (b.id || 0) - (a.id || 0);
                });
                filteredAllocations = [...allAllocations];
                currentPage = 1;
                renderAllocations();
                renderPagination();
            } else {
                allAllocations = [];
                filteredAllocations = [];
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="8" style="text-align:center;">No records found</td>
                    </tr>
                `;
                renderPagination();
            }
        } catch (err) {
            console.error('Failed to load rider allocations:', err);
        }
    }

    /* ================= RENDER TABLE ================= */

    function renderAllocations() {
        tableBody.innerHTML = '';

        // Calculate pagination
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        const paginatedAllocations = filteredAllocations.slice(startIndex, endIndex);

        if (paginatedAllocations.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="8" style="text-align:center;">No records found</td>
                </tr>
            `;
            return;
        }

        paginatedAllocations.forEach(item => {

            /* ---------- SERVICE TYPE ---------- */
            let serviceHtml = '-';
            let serviceArr = [];



            if (item.inbound == 1) {
                serviceArr.push(`
                    <div class="service-type-item">
                        Inbound <i class="fas fa-arrow-down" style="font-size:11px; transform:rotate(45deg);"></i>
                    </div>
                `);
            }

            if (item.outbound == 1) {
                serviceArr.push(`
                    <div class="service-type-item">
                        Outbound <i class="fas fa-arrow-up" style="font-size:11px; transform:rotate(45deg);"></i>
                    </div>
                `);
            }

            if (serviceArr.length) serviceHtml = serviceArr.join('');

            /* ---------- PRICE PER RIDER (ALWAYS SHOW) ---------- */
            let priceHtml = '-';
            let priceArr = [];

           
            
            if (item.inbound == 1) {
                priceArr.push(`<div>AED ${Number(item.inbound_amount)}</div>`);
                 console.log('item.inbound_amount', item.inbound_amount);
            }
            if (item.outbound == 1) {
                priceArr.push(`<div>AED ${Number(item.outbound_amount)}</div>`);
                console.log('item.outbound_amount', item.outbound_amount);
            }

            if (priceArr.length) priceHtml = priceArr.join('');

            /* ---------- RIDER COUNT (HIDE FOR POSTPAID) ---------- */
            let riderHtml = '-';
            if (item.payment_type !== 'Postpaid') {
                let riderArr = [];
                if (item.inbound == 1) {
                    riderArr.push(`<div>${Number(item.inbound_rider_number)}</div>`);
                }
                if (item.outbound == 1) {
                    riderArr.push(`<div>${Number(item.outbound_rider_number)}</div>`);
                }
                if (riderArr.length) riderHtml = riderArr.join('');
            }

            /* ---------- TOTAL AMOUNT (HIDE FOR POSTPAID) ---------- */
            const totalAmountHtml =
                item.payment_type === 'Postpaid'
                    ? '-'
                    : `AED ${Number(item.amount)}`;

            /* ---------- PAYMENT TYPE ---------- */
            const paymentClass =
                item.payment_type === 'Postpaid' ? 'postpaid' : 'prepaid';

            /* ---------- AGENCY TYPE ICON (CORRECT PATH) ---------- */
            const agencyIcon = item.agency_type_image
                ? `<img src="${domainUrl}storage/${item.agency_type_image}" width="16" style="margin-right:6px;">`
                : '';

            /* ---------- TABLE ROW ---------- */
            tableBody.innerHTML += `
                <tr>
                    <td class="bold-text">${item.agency_name}</td>

                    <td>
                        <span class="agency-type">
                            ${agencyIcon}
                            ${item.agency_type_name}
                        </span>
                    </td>

                    <td>
                        <span class="payment-badge ${paymentClass}">
                            ${item.payment_type}
                        </span>
                    </td>

                    <td class="bold-text">
                        ${priceHtml}
                    </td>

                    <td>
                        ${serviceHtml}
                    </td>

                    <td class="bold-text">
                        ${riderHtml}
                    </td>

                    <td class="bold-text">
                        ${totalAmountHtml}
                    </td>

                    <td class="text-end px-4">
                        <a href="javascript:void(0)"
                           class="edit-link"
                           onclick="openEdit(${item.id})">
                            <i class="far fa-edit"></i> Edit
                        </a>
                    </td>
                </tr>
            `;
        });
    }

    /* ================= EDIT REDIRECT (✅ FIXED URL) ================= */

    window.openEdit = function (id) {
        window.location.href =
            domainUrl + 'rider-agency/rider-allocation/plan?edit_id=' + id;
    };

    /* ================= PAGINATION RENDER ================= */

    function renderPagination() {
        const totalPages = Math.ceil(filteredAllocations.length / itemsPerPage);
        const paginationContainer = document.getElementById('paginationContainer');

        if (!paginationContainer) {
            // Create pagination container if it doesn't exist
            const tableContainer = tableBody.closest('table')?.parentElement;
            if (tableContainer) {
                const paginationDiv = document.createElement('div');
                paginationDiv.id = 'paginationContainer';
                paginationDiv.className = 'd-flex justify-content-between align-items-center mt-3';
                paginationDiv.style.padding = '0 15px';
                tableContainer.appendChild(paginationDiv);
            } else {
                return;
            }
        }

        const paginationContainerEl = document.getElementById('paginationContainer');
        if (!paginationContainerEl) return;

        if (totalPages <= 1) {
            paginationContainerEl.innerHTML = `
                <div class="text-muted small">
                    Showing ${filteredAllocations.length} of ${filteredAllocations.length} entries
                </div>
            `;
            return;
        }

        const startItem = (currentPage - 1) * itemsPerPage + 1;
        const endItem = Math.min(currentPage * itemsPerPage, filteredAllocations.length);

        let paginationHTML = `
            <div class="text-muted small">
                Showing ${startItem} to ${endItem} of ${filteredAllocations.length} entries
            </div>
            <nav>
                <ul class="pagination pagination-sm mb-0">
        `;

        // Previous button
        paginationHTML += `
            <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="javascript:void(0)" data-page="${currentPage - 1}">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        `;

        // Page numbers
        const maxVisiblePages = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

        if (endPage - startPage < maxVisiblePages - 1) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }

        if (startPage > 1) {
            paginationHTML += `
                <li class="page-item">
                    <a class="page-link" href="javascript:void(0)" data-page="1">1</a>
                </li>
            `;
            if (startPage > 2) {
                paginationHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            paginationHTML += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="javascript:void(0)" data-page="${i}">${i}</a>
                </li>
            `;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                paginationHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            paginationHTML += `
                <li class="page-item">
                    <a class="page-link" href="javascript:void(0)" data-page="${totalPages}">${totalPages}</a>
                </li>
            `;
        }

        // Next button
        paginationHTML += `
            <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="javascript:void(0)" data-page="${currentPage + 1}">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        `;

        paginationHTML += `
                </ul>
            </nav>
        `;

        paginationContainerEl.innerHTML = paginationHTML;

        // Add click handlers
        paginationContainerEl.querySelectorAll('.page-link[data-page]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = parseInt(link.getAttribute('data-page'));
                if (page >= 1 && page <= totalPages && page !== currentPage) {
                    currentPage = page;
                    renderAllocations();
                    renderPagination();
                    // Scroll to top of table
                    tableBody.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    }

    /* ================= SEARCH ================= */

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase().trim();
            
            if (searchTerm === '') {
                // Reset to all allocations
                filteredAllocations = [...allAllocations];
            } else {
                // Filter allocations
                filteredAllocations = allAllocations.filter(item => {
                    const agencyName = (item.agency_name || '').toLowerCase();
                    const agencyType = (item.agency_type_name || '').toLowerCase();
                    const paymentType = (item.payment_type || '').toLowerCase();
                    return agencyName.includes(searchTerm) || 
                           agencyType.includes(searchTerm) || 
                           paymentType.includes(searchTerm);
                });
            }
            
            // Reset to first page when searching
            currentPage = 1;
            renderAllocations();
            renderPagination();
        });
    }

    /* ================= INIT ================= */

    loadRiderAllocations();

});
