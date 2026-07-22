/*
=====================================================
AGENCY JS – FINAL (with Edit & Delete)
=====================================================
*/

document.addEventListener('DOMContentLoaded', () => {

    const agencyModal = document.getElementById('agencyModal');
    if (!agencyModal) return;

    /* ================= CSRF ================= */
    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    /* ================= CONFIG ================= */

    const AGENCY_TYPE_MAP = {
        1: 'Travel',
        2: 'Hotel',
        3: 'VISA'
    };

    /* ================= DOM ================= */

    const agencyOverlay = document.getElementById('agencyOverlay');
    const typeOverlay = document.getElementById('typeOverlay');
    const typeModal = document.getElementById('typeModal');

    const agencyName = document.getElementById('agencyName');
    const agencyType = document.getElementById('agencyType');
    const agencyEmail = document.getElementById('agencyEmail');
    const agencyPhone = document.getElementById('agencyPhone');
    const agencyAddress = document.getElementById('agencyAddress');
    const agencyTableBody = document.getElementById('agencyTableBody');

    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirmPassword');

    const uploadBox = document.getElementById('uploadBox');
    const logoInput = document.getElementById('logoInput');
    const uploadText = document.getElementById('uploadText');

    const typeUploadBox = document.getElementById('typeUploadBox');
    const typeImageInput = document.getElementById('typeImageInput');
    const typeUploadText = document.getElementById('typeUploadText');
    const newTypeName = document.getElementById('newTypeName');

    let agencyCounter = agencyTableBody.children.length;
    let editingAgencyId = null;

    /* ================= PAGINATION VARIABLES ================= */
    let allAgencies = []; // Store all agencies
    let filteredAgencies = []; // Store filtered agencies
    let currentPage = 1;
    let currentTypeFilter = ''; // Store current type filter
    let currentSearch = ''; // Store current search term
    const itemsPerPage = 10;
    
    /* ================= SEARCH INPUT ================= */
    const searchInput = document.querySelector('.search-box input[placeholder="Search Agency"]');

    /* ================= AGENCY NORMALIZER (ADDED) ================= */

    function normalizeAgency(agency) {
        if (Array.isArray(agency)) {
            return agency.length ? agency[0] : null;
        }
        return agency;
    }

    /* ================= TOASTS (ADDED) ================= */

    function showToast(message, type = 'success') {
        if (typeof iziToast !== 'undefined') {
            iziToast[type]({
                title: type === 'success' ? 'Success' : 'Error',
                message: message,
                position: 'topRight'
            });
        } else {
            alert(message);
        }
    }

    /* ================= MODALS ================= */

    window.openAgencyModal = (isEdit = false) => {
        agencyOverlay.style.display = 'block';
        agencyModal.style.display = 'block';

        const titleObj = agencyModal.querySelector('.agency-modal-header h3');
        const submitBtn = agencyModal.querySelector('.btn-primary');

        if (isEdit) {
            if (titleObj) titleObj.innerText = 'Edit Agency';
            if (submitBtn) submitBtn.innerText = 'Update Agency';
        } else {
            editingAgencyId = null;
            resetForm();
            if (titleObj) titleObj.innerText = 'Add New Agency';
            if (submitBtn) submitBtn.innerText = 'Create Agency';
        }
    };

    window.closeAgencyModal = () => {
        agencyOverlay.style.display = 'none';
        agencyModal.style.display = 'none';
        resetForm();
    };

    window.openTypeModal = () => {
        typeOverlay.style.display = 'block';
        typeModal.style.display = 'block';
        setTimeout(() => newTypeName.focus(), 100);
    };

    window.closeTypeModal = () => {
        typeOverlay.style.display = 'none';
        typeModal.style.display = 'none';
        newTypeName.value = '';
        typeUploadText.innerHTML = 'Click to upload image';
        typeImageInput.value = '';
    };

    /* ================= LOAD AGENCY TYPES ================= */

    async function loadAgencyTypes() {
        try {
            const res = await fetch(domainUrl + 'getAgencyType');
            const data = await res.json();

            agencyType.innerHTML = `<option value="">Select Agency Type</option>`;

            if (data.success && Array.isArray(data.agency_types)) {
                data.agency_types.forEach(type => {
                    const option = document.createElement('option');
                    option.value = type.id;
                    option.textContent = type.name;
                    agencyType.appendChild(option);
                });
            }
        } catch (err) {
            console.error('Load agency types failed:', err);
        }
    }

    /* ================= FETCH & BIND AGENCIES ================= */

    async function loadAgencies(typeId = '') {
        try {
            // Update current filter if typeId is provided
            if (typeId !== undefined && typeId !== null) {
                currentTypeFilter = typeId || '';
            }
            
            let url = domainUrl + 'fetchAllAgencies';
            if (currentTypeFilter) url += `?agency_type_id=${currentTypeFilter}`;

            const res = await fetch(url);
            const data = await res.json();

            // Update Header Icon if filtered
            const headerIcon = document.getElementById('typeHeaderIcon');
            if (headerIcon) {
                if (currentTypeFilter && data.status === true && data.agencies && data.agencies.length > 0) {
                    const first = data.agencies[0];
                    if (first.agency_type_image) {
                        headerIcon.src = first.agency_type_image.startsWith('http')
                            ? first.agency_type_image
                            : domainUrl + 'storage/' + first.agency_type_image;
                        headerIcon.style.display = 'inline-block';
                    } else {
                        headerIcon.style.display = 'none';
                    }
                } else {
                    headerIcon.style.display = 'none';
                }
            }

            if (data.status === true && Array.isArray(data.agencies)) {
                // Sort by ID descending (newest first) - new data on top
                allAgencies = data.agencies.sort((a, b) => {
                    return (b.id || 0) - (a.id || 0);
                });
                // Apply search filter if exists
                applyFilters();
            } else {
                allAgencies = [];
                filteredAgencies = [];
                currentPage = 1;
                agencyTableBody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align:center;">No records found</td>
                    </tr>
                `;
                renderPagination();
            }

        } catch (err) {
            console.error('Fetch agencies failed:', err);
        }
    }

    /* ================= RENDER AGENCIES (PAGINATED) ================= */

    function renderAgencies() {
        agencyTableBody.innerHTML = '';

        // Calculate pagination
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        const paginatedAgencies = filteredAgencies.slice(startIndex, endIndex);

        if (paginatedAgencies.length === 0) {
            agencyTableBody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align:center;">No records found</td>
                </tr>
            `;
            return;
        }

        paginatedAgencies.forEach(agency => {
            const typeName = agency.agency_name || AGENCY_TYPE_MAP[agency.agency_type] || 'Unknown';

            const typeIconPath = agency.agency_type_image;
            const typeIcon = typeIconPath
                ? (typeIconPath.startsWith('http') ? typeIconPath : domainUrl + 'storage/' + typeIconPath)
                : null;

            const logoPath = agency.agency_image || agency.logo;
            const logo = logoPath
                ? (logoPath.startsWith('http') ? logoPath : domainUrl + 'storage/' + logoPath)
                : `https://ui-avatars.com/api/?name=${agency.name}&background=0D8ABC&color=fff`;

            agencyTableBody.insertAdjacentHTML('beforeend', `
                <tr>
                    <td>${agency.id}</td>

                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <img src="${logo}"
                                 style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:1px solid #e5e7eb;">
                            <div>
                                <div style="font-weight:600;">${agency.name}</div>
                                <div style="font-size:12px;color:#6b7280;">
                                    ${typeName} Agency
                                </div>
                            </div>
                        </div>
                    </td>

                    <td>
                        <span class="badge" style="display:flex;align-items:center;gap:6px;">
                            ${typeIcon ? `<img src="${typeIcon}" style="width:16px;height:16px;">` : ''}
                            <span>${typeName}</span>
                        </span>
                    </td>

                    <td>${agency.email}<br>${agency.contact_number}</td>
                    <td>${agency.address || '-'}</td>

                    <td>
                        <div style="display:flex;gap:12px;align-items:center;">
                            
                            <!-- DELETE (UNCHANGED SVG) -->
                            <a href="javascript:void(0)" onclick="deleteAgency(${agency.id})"
                               style="color:#ef4444;display:flex;gap:4px;align-items:center;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path d="M19.5 5.5L18.88 15.525C18.72 17.925 18.64 19.125 17.827 19.8125C17.015 20.5 15.811 20.5 13.403 20.5H10.597C8.189 20.5 6.985 20.5 6.173 19.8125C5.36 19.125 5.28 17.925 5.12 15.525L4.5 5.5M3 5.5H21M16.056 5.5L15.373 4.091C14.92 3.146 14.693 2.674 14.285 2.458C13.876 2.242 13.351 2.242 12.301 2.242H11.699C10.649 2.242 10.124 2.242 9.715 2.458C9.307 2.674 9.08 3.146 8.627 4.091L7.944 5.5" stroke="#FF3B30" stroke-width="2"/>
                                </svg>
                                Delete
                            </a>

                            <span style="width:1px;height:16px;background:#C5E5FF;"></span>

                            <!-- EDIT (UNCHANGED SVG) -->
                            <a href="javascript:void(0)" onclick="editAgency(${agency.id})"
                               style="color:#008CC3;display:flex;gap:4px;align-items:center;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path d="M7 7H6C5.46957 7 4.96086 7.21071 4.58579 7.58579C4.21071 7.96086 4 8.46957 4 9V18C4 18.5304 4.21071 19.0391 4.58579 19.4142C4.96086 19.7893 5.46957 20 6 20H15C15.5304 20 16.0391 19.7893 16.4142 19.4142C16.7893 19.0391 17 18.5304 17 18V17M13.5 10.5L17.5 6.5M16.5 3.5L20.5 7.5L11 17H7V13L16.5 3.5Z" stroke="#008CC3" stroke-width="2"/>
                                </svg>
                                Edit
                            </a>

                        </div>
                    </td>
                </tr>
            `);
        });
    }

    /* ================= PAGINATION RENDER ================= */

    function renderPagination() {
        const totalPages = Math.ceil(filteredAgencies.length / itemsPerPage);
        const tableContainer = agencyTableBody.closest('table')?.parentElement;
        
        if (!tableContainer) return;

        let paginationContainer = document.getElementById('agencyPaginationContainer');
        
        if (!paginationContainer) {
            // Create pagination container if it doesn't exist
            paginationContainer = document.createElement('div');
            paginationContainer.id = 'agencyPaginationContainer';
            paginationContainer.className = 'd-flex justify-content-between align-items-center mt-3';
            paginationContainer.style.padding = '0 15px';
            tableContainer.appendChild(paginationContainer);
        }

        if (totalPages <= 1) {
            paginationContainer.innerHTML = `
                <div class="text-muted small">
                    Showing ${filteredAgencies.length} of ${filteredAgencies.length} entries
                </div>
            `;
            return;
        }

        const startItem = (currentPage - 1) * itemsPerPage + 1;
        const endItem = Math.min(currentPage * itemsPerPage, filteredAgencies.length);

        let paginationHTML = `
            <div class="text-muted small">
                Showing ${startItem} to ${endItem} of ${filteredAgencies.length} entries
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

        paginationContainer.innerHTML = paginationHTML;

        // Add click handlers
        paginationContainer.querySelectorAll('.page-link[data-page]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = parseInt(link.getAttribute('data-page'));
                if (page >= 1 && page <= totalPages && page !== currentPage) {
                    currentPage = page;
                    renderAgencies();
                    renderPagination();
                    // Scroll to top of table
                    agencyTableBody.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    }

    /* ================= SEARCH FUNCTION ================= */

    function applyFilters() {
        let filtered = [...allAgencies];

        // Apply type filter
        if (currentTypeFilter) {
            filtered = filtered.filter(agency => {
                return String(agency.agency_type) === String(currentTypeFilter);
            });
        }

        // Apply search filter
        if (currentSearch && currentSearch.trim()) {
            const searchTerm = currentSearch.toLowerCase().trim();
            filtered = filtered.filter(agency => {
                const name = (agency.name || '').toLowerCase();
                const email = (agency.email || '').toLowerCase();
                const contact = (agency.contact_number || '').toLowerCase();
                const address = (agency.address || '').toLowerCase();
                const typeName = (agency.agency_name || AGENCY_TYPE_MAP[agency.agency_type] || '').toLowerCase();
                const id = String(agency.id || '').toLowerCase();

                return name.includes(searchTerm) ||
                       email.includes(searchTerm) ||
                       contact.includes(searchTerm) ||
                       address.includes(searchTerm) ||
                       typeName.includes(searchTerm) ||
                       id.includes(searchTerm);
            });
        }

        filteredAgencies = filtered;
        currentPage = 1; // Reset to first page when filtering
        renderAgencies();
        renderPagination();
    }

    /* ================= TAB FILTER ================= */

    window.filterAgency = function (typeId, el) {
        document.querySelectorAll('.tabs span')
            .forEach(tab => tab.classList.remove('active'));

        el.classList.add('active');
        currentTypeFilter = typeId || ''; // Store current filter
        applyFilters(); // Apply filters (type + search)
    };

    /* ================= CREATE / UPDATE ================= */

    let isSavingAgency = false;

    window.createAgency = async function () {
        if (isSavingAgency) return;

        const name = agencyName.value.trim();
        const agency_type_id = agencyType.value;
        const email = agencyEmail.value.trim();
        const contact_number = agencyPhone.value.trim();
        const address = agencyAddress.value.trim();
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        const logo = logoInput.files[0];
        const submitBtn = agencyModal.querySelector('.btn-primary');

        /* ===== BASIC VALIDATION ===== */
        if (!name || !agency_type_id || !email || !contact_number) {
            showToast('All required fields must be filled', 'error');
            return;
        }

        // New agency → logo required
        if (!editingAgencyId && !logo) {
            showToast('The logo field is required.', 'error');
            return;
        }

        /* ===== PASSWORD VALIDATION ===== */

        // New agency → password required
        if (!editingAgencyId && !password) {
            showToast('Password is required for new agencies', 'error');
            return;
        }

        // If password entered → confirm password required
        if (password && !confirmPassword) {
            showToast('Confirm password is required', 'error');
            return;
        }

        // Password & confirm password must match
        if (password && password !== confirmPassword) {
            showToast('Password and Confirm Password do not match', 'error');
            return;
        }

        /* ===== FORM DATA ===== */
        const formData = new FormData();
        if (editingAgencyId) formData.append('id', editingAgencyId);

        formData.append('name', name);
        formData.append('agency_type', agency_type_id);
        formData.append('address', address);
        formData.append('email', email);
        formData.append('contact_number', contact_number);

        if (logo) formData.append('logo', logo);
        if (password) formData.append('password', password);

        const endpoint = editingAgencyId ? 'updateAgency' : 'addAgency';

        isSavingAgency = true;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.dataset.originalText = submitBtn.innerText;
            submitBtn.innerText = editingAgencyId ? 'Updating...' : 'Creating...';
        }

        try {
            const res = await fetch(domainUrl + endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: formData
            });

            const data = await res.json();

            if (!res.ok || data.success === false || data.status === false) {
                throw new Error(data.message || 'Agency save failed');
            }

            showToast(data.message || 'Agency saved successfully', 'success');
            closeAgencyModal();
            currentPage = 1; // Reset to first page to show new/updated agency on top
            loadAgencies(currentTypeFilter); // Reload with current filter

        } catch (err) {
            showToast(err.message, 'error');
        } finally {
            isSavingAgency = false;
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerText = submitBtn.dataset.originalText || (editingAgencyId ? 'Update Agency' : 'Create Agency');
            }
        }
    };


    /* ================= EDIT ================= */

    window.editAgency = async function (id) {
        try {
            const res = await fetch(domainUrl + 'editAgency/' + id);
            const data = await res.json();

            if (data.status === true && data.agency) {

                const agency = normalizeAgency(data.agency);
                if (!agency) {
                    showToast('Agency data not found', 'error');
                    return;
                }

                editingAgencyId = id;

                agencyName.value = agency.name || '';
                agencyType.value = agency.agency_type || '';
                agencyEmail.value = agency.email || '';
                agencyPhone.value = agency.contact_number || '';
                agencyAddress.value = agency.address || '';

                passwordInput.value = '';
                confirmPasswordInput.value = '';

                if (agency.logo) {
                    uploadText.innerHTML = `<b>${agency.logo.split('/').pop()}</b>`;
                }

                openAgencyModal(true);
            } else {
                showToast(data.message || 'Could not fetch agency details', 'error');
            }
        } catch (err) {
            console.error('Edit agency failed:', err);
            showToast('An error occurred while fetching agency data', 'error');
        }
    };

    /* ================= DELETE ================= */

    window.deleteAgency = async function (id) {
        if (!confirm("Are you sure you want to delete this agency?")) return;

        try {
            const res = await fetch(domainUrl + 'deleteAgency/' + id, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            const data = await res.json();

            if (data.status === false) {
                throw new Error(data.message || 'Delete failed');
            }

            showToast(data.message || 'Agency deleted successfully');
            currentPage = 1; // Reset to first page after deletion
            loadAgencies(currentTypeFilter); // Reload with current filter

        } catch (err) {
            showToast(err.message, 'error');
        }
    };

    /* ================= RESET ================= */

    function resetForm() {
        agencyName.value = '';
        agencyType.selectedIndex = 0;
        agencyEmail.value = '';
        agencyPhone.value = '';
        agencyAddress.value = '';
        passwordInput.value = '';
        confirmPasswordInput.value = '';
        uploadText.innerHTML = 'Drag and drop file here or <b>choose file</b>';
        logoInput.value = '';
    }

    /* ================= UPLOAD ================= */

    uploadBox.addEventListener('click', () => logoInput.click());
    logoInput.addEventListener('change', () => {
        if (logoInput.files.length) {
            uploadText.innerHTML = `<b>${logoInput.files[0].name}</b>`;
        }
    });

    /* ================= ADD AGENCY TYPE ================= */
/* ================= ADD AGENCY TYPE ================= */

    window.submitType = async function (e) {
        e.preventDefault();

        if (!newTypeName.value.trim() || !typeImageInput.files.length) {
            alert('Type name and image required');
            return;
        }

        const fd = new FormData();
        fd.append('name', newTypeName.value.trim());
        fd.append('image', typeImageInput.files[0]);

        try {
            const res = await fetch(domainUrl + 'addAgencyType', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: fd
            });

            const raw = await res.text();
            const data = JSON.parse(raw);

            if (!res.ok || data.success === false) {
                throw new Error(data.message || 'Failed to add agency type');
            }

            await loadAgencyTypes();
            closeTypeModal();

        } catch (err) {
            alert(err.message);
        }
    };
    /* ================= TYPE IMAGE UPLOAD FIX ================= */

    if (typeUploadBox && typeImageInput) {

        typeUploadBox.addEventListener('click', () => {
            typeImageInput.click();
        });

        typeImageInput.addEventListener('change', () => {
            if (typeImageInput.files.length > 0) {
                typeUploadText.innerHTML =
                    `<b>${typeImageInput.files[0].name}</b>`;
            }
        });

    }


    /* ================= SEARCH EVENT LISTENER ================= */

    if (searchInput) {
        // Debounce search to avoid too many calls
        let searchTimeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentSearch = e.target.value;
                applyFilters();
            }, 300); // Wait 300ms after user stops typing
        });

        // Also search on Enter key
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                clearTimeout(searchTimeout);
                currentSearch = e.target.value;
                applyFilters();
            }
        });
    }

    /* ================= INIT ================= */

    loadAgencyTypes();
    loadAgencies();

});
 