/**
 * Excel Upload specific JavaScript
 */
(function ($) {
    'use strict';

    const ExcelController = {
        partnerType: 'hotel',
        hasSubscription: false,

        init: function () {
            this.loadConfig();
            this.initUI();
        },

        loadConfig: function () {
            const configData = $('body').data('excel-config');

            // Default values
            this.partnerType = 'hotel';
            this.hasSubscription = false;

            if (configData) {
                // has_subscription_plan: 1 = plan exists, 0 = no plan
                this.hasSubscription = Number(configData.hasSubscriptionPlan) === 1 || configData.hasSubscriptionPlan === '1';

                if (configData.partnerType) {
                    const type = String(configData.partnerType).toLowerCase();
                    if (type.includes('travel')) {
                        this.partnerType = 'travel';
                    } else if (type.includes('visa')) {
                        this.partnerType = 'visa';
                    } else {
                        this.partnerType = 'hotel';
                    }
                }
            } else {
                // Fallback: try to get from hidden input
                const hiddenType = $('#partnerType').val();
                if (hiddenType) {
                    this.partnerType = String(hiddenType).toLowerCase();
                }
                // Don't assume subscription exists if config is missing
                this.hasSubscription = false;
            }

            console.log('Excel Controller config loaded:', {
                hasSubscription: this.hasSubscription,
                partnerType: this.partnerType
            });
        },

        initUI: function () {
            if (this.hasSubscription) {
                this.initUploadSection();
                this.initDownloadButtons();
                // Initialize TouristList when subscription exists
                setTimeout(() => {
                    if (window.TouristList && typeof window.TouristList.init === 'function') {
                        window.TouristList.init();
                    }
                }, 300);
            } else {
                this.showNoSubscriptionState();
            }
        },

        initUploadSection: function () {
            const config = {
                hotel: {
                    required: [
                        'First Name', 'Last Name', 'Mobile Number','Booking ID',
                        'Check In Time', 'Check Out Time', 'Service Type'
                    ]
                },
                travel: {
                    required: [
                        'First Name', 'Last Name', 'Mobile Number',
                        'Fly In ', 'Fly Out ','Service Type'
                    ]
                },
                visa: {
                    required: [
                        'First Name', 'Last Name', 'Mobile Number',
                        'Start Date ',
                        'Validity (30 / 60 Days)', 'Service Type'
                    ]
                }
            };

            const activeConfig = config[this.partnerType] || config.hotel;
            const $columnList = $('#requiredColumnsList');

            if ($columnList.length) {
                $columnList.empty();
                activeConfig.required.forEach(col => {
                    $columnList.append(`
                        <li class="column-item" style="align-items: flex-start; margin-bottom: 12px;">
                            <span class="check-icon" style="margin-top: -2px;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M12 22C13.3135 22.0016 14.6143 21.7437 15.8278 21.2411C17.0412 20.7384 18.1434 20.0009 19.071 19.071C20.0009 18.1434 20.7384 17.0412 21.2411 15.8278C21.7437 14.6143 22.0016 13.3135 22 12C22.0016 10.6866 21.7437 9.38572 21.2411 8.17225C20.7384 6.95878 20.0009 5.85659 19.071 4.92901C18.1434 3.99909 17.0412 3.26162 15.8278 2.75897C14.6143 2.25631 13.3135 1.99839 12 2.00001C10.6866 1.99839 9.38572 2.25631 8.17225 2.75897C6.95878 3.26162 5.85659 3.99909 4.92901 4.92901C3.99909 5.85659 3.26162 6.95878 2.75897 8.17225C2.25631 9.38572 1.99839 10.6866 2.00001 12C1.99839 13.3135 2.25631 14.6143 2.75897 15.8278C3.26162 17.0412 3.99909 18.1434 4.92901 19.071C5.85659 20.0009 6.95878 20.7384 8.17225 21.2411C9.38572 21.7437 10.6866 22.0016 12 22Z" stroke="#008CC3" stroke-width="1.5" stroke-linejoin="round"/>
<path d="M8 12L11 15L17 9" stroke="#008CC3" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
</svg>

                            </span>
                            <div class="column-content" style="width: 100%;">
                                <span class="column-name">${col}</span>
                            </div>
                        </li>
                    `);
                });
            }


            const $uploadBtn = $('#chooseExcelBtn');
            const $fileInput = $('#excelFileInput');
            const $importForm = $('#touristImportForm');
            const $dropZone = $('#excelDropZone'); // REQUIRED ID

            /* BUTTON CLICK */
            if ($uploadBtn.length) {
                $uploadBtn.on('click', function (e) {
                    console.log('Choose Excel button clicked');
                    e.preventDefault();

                    // If the real file input is visible/usable, trigger it directly
                    try {
                        if ($fileInput.length && $fileInput.is(':visible')) {
                            $fileInput[0].click(); // native behavior
                            return;
                        }
                    } catch (err) {
                        // ignore and fallback
                    }

                    // Fallback: create a temporary file input (offscreen but clickable)
                    const tempInput = document.createElement('input');
                    tempInput.type = 'file';
                    tempInput.accept = $fileInput.attr('accept') || '.xlsx,.xls,.csv';
                    tempInput.style.position = 'absolute';
                    tempInput.style.left = '-9999px';
                    document.body.appendChild(tempInput);

                    tempInput.addEventListener('change', function () {
                        if (!this.files || !this.files.length) {
                            document.body.removeChild(tempInput);
                            return;
                        }

                        // Transfer chosen file to the hidden real input so existing handlers can submit
                        try {
                            const file = this.files[0];
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(file);
                            if ($fileInput.length) {
                                $fileInput[0].files = dataTransfer.files;
                            }

                            // Submit the original form
                            submitExcelFormAjax($importForm[0]);
                        } catch (ex) {
                            console.error('Fallback file transfer failed', ex);
                        }

                        document.body.removeChild(tempInput);
                    });

                    // Programmatically open picker
                    tempInput.click();
                });
            }

            /* FILE INPUT CHANGE */
            $fileInput.on('change', function () {
                if (!this.files.length) return;

                const file = this.files[0];
                const ext = file.name.split('.').pop().toLowerCase();

                if (!['xlsx', 'xls', 'csv'].includes(ext)) {
                    alert('Invalid file type');
                    this.value = '';
                    return;
                }

                submitExcelFormAjax($importForm[0]);
            });

            /* DRAG & DROP */
            if ($dropZone.length) {

                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evt => {
                    $dropZone.on(evt, function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                    });
                });

                $dropZone.on('dragenter dragover', function () {
                    $(this).addClass('drag-active');
                });

                $dropZone.on('dragleave drop', function () {
                    $(this).removeClass('drag-active');
                });

                $dropZone.on('drop', function (e) {
                    const files = e.originalEvent.dataTransfer.files;
                    if (!files.length) return;

                    const file = files[0];
                    const ext = file.name.split('.').pop().toLowerCase();

                    if (!['xlsx', 'xls', 'csv'].includes(ext)) {
                        alert('Invalid file type');
                        return;
                    }

                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    $fileInput[0].files = dataTransfer.files;

                    submitExcelFormAjax($importForm[0]);
                });
            }
        },


        initDownloadButtons: function () {
            const excelTemplates = {
    hotel:  '/v2/asset/script/partnerportal/hotel.xlsx',
    travel: '/v2/asset/script/partnerportal/travel.xlsx',
    visa:   '/v2/asset/script/partnerportal/visa.xlsx'
};


            $('.btn-download').on('click', (e) => {
                const type = $(e.currentTarget).data('type');
                const fileUrl = excelTemplates[type] || excelTemplates.hotel;
                
                // Construct absolute URL
                const absoluteUrl = window.location.origin + fileUrl;
                console.log(absoluteUrl);

                const link = document.createElement("a");
                link.setAttribute("href", absoluteUrl);
                link.setAttribute("download", `tourist_import_sample_${type}.xlsx`);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        },

        showNoSubscriptionState: function () {
            $('.upload-card, .table-card').hide();
            $('.no-subscription-state').show();
        }
    };

    $(document).ready(function () {
        ExcelController.init();
    });

    window.ExcelController = ExcelController;

})(jQuery);
async function submitExcelFormAjax(form) {
    const formData = new FormData(form);
    const $btn = $('#chooseExcelBtn');
    const originalText = $btn.text();

    try {
        // Loading state
        $btn.prop('disabled', true).text('Uploading...');

        const response = await fetch(form.action, {
            method: "POST",
            body: formData,
            credentials: "include",
            headers: {
                "Accept": "application/json",
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content")
            }
        });

        const text = await response.text();
        let result;

        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error("Non-JSON response:", text);
            throw new Error("Invalid server response");
        }

        if (result.status === true) {
            console.log("Excel import success");

            // ✅ Toast success (NO refresh)
            showToast(result.message || "Excel imported successfully", "success");

            // ✅ Reload table data without page refresh
            // Wait a moment for server to process the data
            setTimeout(() => {
                console.log('Attempting to reload table after Excel upload...');
                
                // Try to use the reload helper function if available
                if (window.TouristList && typeof window.TouristList.reload === 'function') {
                    console.log('Using TouristList.reload()...');
                    window.TouristList.reload();
                } else if (window.TouristList && typeof window.TouristList.fetchData === 'function') {
                    console.log('Using TouristList.fetchData()...');
                    // Reset to first page and clear search
                    window.TouristList.currentPage = 1;
                    window.TouristList.currentSearch = '';
                    const $searchInput = $('#touristSearchInput');
                    if ($searchInput.length) {
                        $searchInput.val('');
                    }
                    // Fetch fresh data
                    window.TouristList.fetchData();
                } else {
                    console.warn('TouristList not available, trying to initialize...');
                    // Try to initialize if not already done
                    if (typeof TouristList !== 'undefined') {
                        TouristList.init();
                        // Try again after initialization
                        setTimeout(() => {
                            if (window.TouristList && typeof window.TouristList.reload === 'function') {
                                window.TouristList.reload();
                            }
                        }, 300);
                    }
                }
            }, 800); // Delay to ensure server processed the data

        } else {
            const errorMsg = result.error || result.message || "Excel import failed";
            showToast(errorMsg, "error");
        }

    } catch (err) {
        console.error("Excel Import Error:", err);
        showToast("Something went wrong: " + err.message, "error");
    } finally {
        // Restore button
        $btn.prop('disabled', false).text(originalText);
    }
}

function showToast(message, type = "success", duration = 3000) {
    let toast = document.getElementById("appToast");

    if (!toast) {
        toast = document.createElement("div");
        toast.id = "appToast";
        toast.style.position = "fixed";
        toast.style.top = "20px";
        toast.style.right = "20px";
        toast.style.zIndex = "9999";
        toast.style.padding = "12px 18px";
        toast.style.borderRadius = "8px";
        toast.style.fontSize = "14px";
        toast.style.color = "#fff";
        toast.style.boxShadow = "0 6px 20px rgba(0,0,0,0.15)";
        toast.style.transition = "opacity 0.3s ease";
        document.body.appendChild(toast);
    }

    toast.style.background =
        type === "success" ? "#16a34a" :
        type === "error" ? "#dc2626" :
        "#2563eb";

    toast.textContent = message;
    toast.style.opacity = "1";

    setTimeout(() => {
        toast.style.opacity = "0";
    }, duration);
}
/* ==========================================================
   TOURISTS LIST (SEARCH + TABLE + PAGINATION)
========================================================== */

const TouristList = {
    initialized: false,
    columns: {
    visa: [
        'Customer Name',
        'Mobile Number',
        'Start Date ',
        'Validity (30 / 60 Days)',
        'Service Type'
        
    ],
    travel: [
        'Customer Name',
        'Mobile Number',
        'Fly In Date',
        'Fly Out Date',
        'Service Type',
    ],
    hotel: [
        'Customer Name',
        'Mobile Number',
        'Booking ID',
        'Check In Date',
        'Check Out Date',
        'Service Type'
    ]
},


renderTableHead: function () {
    const tableHeadEl = document.getElementById('tableHead');
    if (!tableHeadEl) {
        console.warn('tableHead element not found');
        return;
    }
    
    const partnerType = window.ExcelController?.partnerType || 'hotel';
    const headers = this.columns[partnerType] || this.columns.hotel;

    let html = '';
    headers.forEach(h => {
        html += `<th>${h}</th>`;
    });

    tableHeadEl.innerHTML = html;
},



    apiUrl: (window.domainUrl || '') + 'partner/import-log/tourists-list',
    debounceTimer: null,
    currentPage: 1,
    currentSearch: '',

   init: function () {
    // Prevent multiple initializations
    if (this.initialized) {
        console.log('TouristList already initialized, skipping...');
        return;
    }
    
    console.log('✅ TouristList init called');
    
    // Check if required DOM elements exist
    const $tbody = $('#touristTableBody');
    const $tableHead = $('#tableHead');
    
    if (!$tbody.length || !$tableHead.length) {
        console.warn('Table elements not found, retrying in 200ms...');
        setTimeout(() => this.init(), 200);
        return;
    }
    
    // Ensure ExcelController is loaded first
    if (!window.ExcelController) {
        console.warn('ExcelController not found, retrying in 100ms...');
        setTimeout(() => this.init(), 100);
        return;
    }
    
    // Check if user has subscription (table should be visible)
    if (!window.ExcelController.hasSubscription) {
        console.log('No subscription, skipping table initialization');
        return;
    }
    
    console.log('TouristList initializing...');
    this.initialized = true;
    this.bindSearch();
    this.renderTableHead();
    this.fetchData();
},



    bindSearch: function () {
        const $input = $('#touristSearchInput');

        if (!$input.length) return;

        $input.on('keyup', (e) => {
            clearTimeout(this.debounceTimer);
            this.currentSearch = e.target.value.trim();
            this.currentPage = 1;

            this.debounceTimer = setTimeout(() => {
                this.fetchData();
            }, 400);
        });
    },

    fetchData: function () {
        const $tbody = $('#touristTableBody');
        
        if (!$tbody.length) {
            console.error('touristTableBody not found');
            return;
        }
        
        const partnerType = window.ExcelController?.partnerType || 'hotel';
        const headers = this.columns[partnerType] || this.columns.hotel;
        const colCount = headers.length;

        $tbody.html(`
            <tr>
                <td colspan="${colCount}" class="text-center text-muted">Loading...</td>
            </tr>
        `);

        const url =
            `${this.apiUrl}?search=${encodeURIComponent(this.currentSearch)}&page=${this.currentPage}`;
        
        console.log('Fetching data from:', url);

        fetch(url, {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            return res.json();
        })
        .then(res => {
            console.log('API Response:', res);

            if (!res.status || !res.data || !res.data.data || !res.data.data.length) {
                $tbody.html(`
                    <tr>
                        <td colspan="${colCount}" class="text-center text-muted">
                            No records found
                        </td>
                    </tr>
                `);
                this.renderPagination(null);
                return;
            }

            this.renderTable(res.data.data);
            this.renderPagination(res.data);
        })
        .catch(err => {
            console.error(' Tourist API Error:', err);
            $tbody.html(`
                <tr>
                    <td colspan="${colCount}" class="text-center text-danger">
                        Error loading data: ${err.message}
                    </td>
                </tr>
            `);
        });
    },

    // Helper function to reload data (can be called externally)
    reload: function() {
        if (!this.initialized) {
            console.log('TouristList not initialized, initializing now...');
            this.init();
            return;
        }
        this.currentPage = 1;
        this.currentSearch = '';
        $('#touristSearchInput').val('');
        this.fetchData();
    },

    renderTable: function (rows) {
    const $tbody = $('#touristTableBody');
    const partnerType = window.ExcelController?.partnerType || 'hotel';
    let html = '';

    rows.forEach(item => {

        // Inbound / Outbound handling (same logic as before)
        const serviceType = item.service_type ?? '-';
        const isInbound = serviceType.toLowerCase() === 'inbound';

        const serviceClass = isInbound ? 'inbound' : 'outbound';
        const arrow = isInbound ? '↙' : '↗';

        const serviceHtml = `
            <span class="service ${serviceClass}">
                ${serviceType}
                <span class="arrow">${arrow}</span>
            </span>
        `;

        if (partnerType === 'hotel') {
            html += `
                <tr>
                    <td>${item.customer_name ?? '-'}</td>
                    <td>${item.mobile_number ?? '-'}</td>
                    <td>${item.booking_id ?? '-'}</td>
                    <td>${item.check_in_time ?? '-'}</td>
                    <td>${item.check_out_time ?? '-'}</td>
                    <td class="text-center-column">${serviceHtml}</td>
                </tr>
            `;
        }

        else if (partnerType === 'travel') {
            html += `
                <tr>
                    <td>${item.customer_name ?? '-'}</td>
                    <td>${item.mobile_number ?? '-'}</td>
                    <td>${item.fly_in_time ?? '-'}</td>
                    <td>${item.fly_out_time ?? '-'}</td>
                    <td class="text-center-column">${serviceHtml}</td>
                </tr>
            `;
        }

        else if (partnerType === 'visa') {
            html += `
                <tr>
                    <td>${item.customer_name ?? '-'}</td>
                    <td>${item.mobile_number ?? '-'}</td>
                    <td>${item.start_date ?? '-'}</td>
                    <td>${item.validity ?? '-'}</td>
                    <td class="text-center-column">${serviceHtml}</td>
                </tr>
            `;
        }

    });

    $tbody.html(html);
},


    renderPagination: function (p) {
        const $pagination = $('#pagination');
        const $info = $('.pagination-info');

        if (!p) {
            $pagination.html('');
            $info.text('');
            return;
        }

        $info.text(`Showing ${p.from} to ${p.to} of ${p.total} entries`);

        let html = '';

        // Prev
        html += `
            <li class="page-item ${p.current_page === 1 ? 'disabled' : ''}">
                <a class="page-link" data-page="${p.current_page - 1}">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        `;

        // Pages
        for (let i = 1; i <= p.last_page; i++) {
            html += `
                <li class="page-item ${i === p.current_page ? 'active' : ''}">
                    <a class="page-link" data-page="${i}">${i}</a>
                </li>
            `;
        }

        // Next
        html += `
            <li class="page-item ${p.current_page === p.last_page ? 'disabled' : ''}">
                <a class="page-link" data-page="${p.current_page + 1}">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        `;

        $pagination.html(html);

        // Click handler
        $('#pagination .page-link').on('click', (e) => {
            e.preventDefault();
            const page = $(e.currentTarget).data('page');
            if (!page || page === this.currentPage) return;

            this.currentPage = page;
            this.fetchData();
        });
    }
};

// Initialize when DOM is ready
// Expose TouristList globally
window.TouristList = TouristList;

$(document).ready(function () {
    // Wait a bit to ensure all scripts are loaded
    setTimeout(function() {
        TouristList.init();
    }, 100);
});

// Also try to initialize when window loads (fallback)
$(window).on('load', function() {
    // Only init if not already initialized
    if (!window.TouristListInitialized) {
        window.TouristListInitialized = true;
        setTimeout(function() {
            TouristList.init();
        }, 200);
    }
});
