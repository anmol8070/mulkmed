document.addEventListener('DOMContentLoaded', () => {

    const agencyTypeSelect = document.getElementById('agencyType');
    const agencyIdSelect   = document.getElementById('agencyId');

    // 🔹 Amount DOMs
    const inboundTotalSpan  = document.getElementById('inboundTotal');
    const outboundTotalSpan = document.getElementById('outboundTotal');
    const totalAmountSpan   = document.getElementById('totalAmount');

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    /* ================= EXPIRY DATE FORMAT + MIN DATE ================= */

const expiryInput = document.getElementById('expiryDate');
//  for expiray date pastdate not display 
    if (expiryInput) {
        const today = new Date().toISOString().split('T')[0];

        expiryInput.addEventListener('focus', function () {
            // ❌ do NOT change type here (HTML already does)
            this.min = today;

            // Convert dd-mm-yyyy → yyyy-mm-dd for date picker
            if (/^\d{2}-\d{2}-\d{4}$/.test(this.value)) {
                const [dd, mm, yyyy] = this.value.split('-');
                this.value = `${yyyy}-${mm}-${dd}`;
            }
        });

        expiryInput.addEventListener('blur', function () {
            if (!this.value) return;

            const date = new Date(this.value);
            if (isNaN(date)) return;

            const day   = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year  = date.getFullYear();

            // Display as dd-mm-yyyy
            this.value = `${day}-${month}-${year}`;
        });
    }



    /* ================= EDIT MODE FLAG (ADDED) ================= */
    let editingAllocationId = null;

    /* ================= TOAST HELPER ================= */

    function showToast(message, type = 'success') {
        if (typeof iziToast !== 'undefined') {
            iziToast[type]({
                title: type === 'success' ? 'Success' : 'Error',
                message,
                position: 'topRight'
            });
        } else {
            alert(message);
        }
    }

    /* ================= TOGGLES ================= */

    window.toggleOption = (type) => {
        const checkbox = document.getElementById(
            `check${type.charAt(0).toUpperCase() + type.slice(1)}`
        );
        const fields = document.getElementById(`${type}Fields`);

        if (checkbox.checked) {
            fields.style.display = 'block';
        } else {
            fields.style.display = 'none';
            document.getElementById(`${type}Price`).value = '';
            document.getElementById(`${type}Qty`).value = '';
            calculateTotal();
        }
    };

    /* ================= CALCULATION ================= */

    window.calculateTotal = () => {
        let inboundTotal = 0;
        let outboundTotal = 0;

        const isPostpaid = document.getElementById('paymentType').value === 'Postpaid';

        if (document.getElementById('checkInbound').checked) {
            const price = parseFloat(document.getElementById('inboundPrice').value) || 0;
            const qty   = isPostpaid ? 0 : (parseFloat(document.getElementById('inboundQty').value) || 0);
            inboundTotal = price * qty;
        }

        if (document.getElementById('checkOutbound').checked) {
            const price = parseFloat(document.getElementById('outboundPrice').value) || 0;
            const qty   = isPostpaid ? 0 : (parseFloat(document.getElementById('outboundQty').value) || 0);
            outboundTotal = price * qty;
        }

        inboundTotalSpan.textContent  = inboundTotal.toLocaleString();
        outboundTotalSpan.textContent = outboundTotal.toLocaleString();
        totalAmountSpan.textContent   = (inboundTotal + outboundTotal).toLocaleString();
    };

    /* ================= LOAD AGENCY TYPES ================= */

    async function loadAgencyTypes() {
        try {
            const res  = await fetch(domainUrl + 'getAgencyType');
            const data = await res.json();

            agencyTypeSelect.innerHTML = `<option value="">Select Agency Type</option>`;

            if (data.success && Array.isArray(data.agency_types)) {
                data.agency_types.forEach(type => {
                    const opt = document.createElement('option');
                    opt.value = type.id;
                    opt.textContent = type.name;
                    agencyTypeSelect.appendChild(opt);
                });
            }
        } catch (err) {
            console.error('Failed to load agency types:', err);
        }
    }

    /* ================= LOAD PRODUCT PLANS ================= */

    async function loadProductPlans() {
        const productIdSelect = document.getElementById('productId');
        try {
            const res  = await fetch('https://pt.mulkmed.com/v2/getProductPlan');
            const data = await res.json();

            productIdSelect.innerHTML = `<option value="">Select Product Plan</option>`;

            if (data.status === true && Array.isArray(data.product_plans)) {
                data.product_plans.forEach(plan => {
                    const opt = document.createElement('option');
                    opt.value = plan.id;
                    opt.textContent = plan.name;
                    productIdSelect.appendChild(opt);
                });
            }
        } catch (err) {
            console.error('Failed to load product plans:', err);
        }
    }

    /* ================= LOAD AGENCIES ================= */

    agencyTypeSelect.addEventListener('change', async () => {
        const typeId = agencyTypeSelect.value;
        agencyIdSelect.innerHTML = `<option value="">Select Agency Name</option>`;
        if (!typeId) return;

        try {
            const res  = await fetch(domainUrl + 'fetchAllAgencies?agency_type_id=' + typeId);
            const data = await res.json();

            if (data.status && Array.isArray(data.agencies)) {
                data.agencies.forEach(agency => {
                    const opt = document.createElement('option');
                    opt.value = agency.id;
                    opt.textContent = agency.name;
                    agencyIdSelect.appendChild(opt);
                });
            }
        } catch (err) {
            console.error('Failed to load agencies:', err);
        }
    });

    /* ================= ADD / UPDATE RIDER ALLOCATION ================= */

    window.allocatePlan = async () => {

        const productPlanId = document.getElementById('productId').value;
        const agencyType    = agencyTypeSelect.value;
        const agencyId      = agencyIdSelect.value;
        const paymentType   = document.getElementById('paymentType').value;
        const expiryDateInput    = document.getElementById('expiryDate').value;

        let expiryDate = expiryDateInput;
        if (/^\d{2}-\d{2}-\d{4}$/.test(expiryDateInput)) {
            const [dd, mm, yyyy] = expiryDateInput.split('-');
            expiryDate = `${yyyy}-${mm}-${dd}`;
        }

        if (!productPlanId || !agencyType || !agencyId || !expiryDate) {
            showToast("Please fill all required fields", "error");
            return;
        }

        let inbound = 0, inboundQty = 0, inboundAmount = 0;
        let outbound = 0, outboundQty = 0, outboundAmount = 0;

        const isPostpaid = paymentType === 'Postpaid';

        if (document.getElementById('checkInbound').checked) {
            inbound = 1;
            inboundQty = isPostpaid ? 0 : (parseInt(document.getElementById('inboundQty').value) || 0);
            inboundAmount = parseFloat(document.getElementById('inboundPrice').value) || 0;
        }

        if (document.getElementById('checkOutbound').checked) {
            outbound = 1;
            outboundQty = isPostpaid ? 0 : (parseInt(document.getElementById('outboundQty').value) || 0);
            outboundAmount = parseFloat(document.getElementById('outboundPrice').value) || 0;
        }

        if (!inbound && !outbound) {
            showToast("Select at least Inbound or Outbound", "error");
            return;
        }

        const totalAmount =
            (inboundQty * inboundAmount) +
            (outboundQty * outboundAmount);

        const payload = {
            product_plan_id: productPlanId,
            agency_type: agencyType,
            agency_id: agencyId,
            payment_type: paymentType,
            expiry_date: expiryDate,
            inbound,
            inbound_rider_number: inboundQty,
            inbound_amount: inboundAmount,
            outbound,
            outbound_rider_number: outboundQty,
            outbound_amount: outboundAmount,
            amount: totalAmount
        };

        // 🔥 ADD vs UPDATE FIX (ONLY ADDITION)
        const apiUrl = editingAllocationId
            ? domainUrl + 'updateRiderAllocation'
            : domainUrl + 'addRiderAllocation';

        if (editingAllocationId) {
            payload.id = editingAllocationId;
        }

        try {
            const res = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();

            if (!res.ok || data.status === false) {
                throw new Error(data.message || "Allocation failed");
            }

            showToast(
    data.message ||
    (editingAllocationId
        ? "Rider allocation updated successfully"
        : "Rider allocation created successfully")
);

setTimeout(() => {
    window.location.href = "https://pt.mulkmed.com/v2/rider-agency/rider-allocation/allocated-agencies";
}, 1000);



        } catch (err) {
            showToast(err.message, "error");
        }
    };

    /* ================= PAYMENT TYPE TOGGLE ================= */

    const paymentTypeSelect = document.getElementById('paymentType');
    const totalDisplay = document.querySelector('.amount-breakdown');

    function toggleTotalDisplay() {
        const riderQtyContainers = document.querySelectorAll('.rider-qty-container');

        if (paymentTypeSelect.value === 'Postpaid') {
            totalDisplay.style.display = 'none';
            riderQtyContainers.forEach(el => el.style.display = 'none');
        } else {
            totalDisplay.style.display = 'flex';
            riderQtyContainers.forEach(el => el.style.display = 'block');
        }

        calculateTotal();
    }

    paymentTypeSelect.addEventListener('change', toggleTotalDisplay);

    /* ================= EDIT PREFILL (ADDED) ================= */

    function getQueryParam(name) {
        return new URLSearchParams(window.location.search).get(name);
    }

    async function loadAllocationForEdit(id) {
        try {
            const res  = await fetch(domainUrl + 'getRiderAllocation');
            const data = await res.json();

            if (!data.success || !Array.isArray(data.rider_allocations)) return;

            const a = data.rider_allocations.find(r => r.id == id);
            if (!a) return;

            editingAllocationId = id;

            document.getElementById('productId').value = a.product_plan_id;
            document.getElementById('paymentType').value = a.payment_type;
            document.getElementById('expiryDate').value = a.expiry_date.split(' ')[0];

            agencyTypeSelect.value = a.agency_type;
            agencyTypeSelect.dispatchEvent(new Event('change'));

            setTimeout(() => {
                agencyIdSelect.value = a.agency_id;
            }, 500);

            if (a.inbound == 1) {
                document.getElementById('checkInbound').checked = true;
                toggleOption('inbound');
                document.getElementById('inboundQty').value = a.inbound_rider_number;
                document.getElementById('inboundPrice').value = a.inbound_amount;
            }

            if (a.outbound == 1) {
                document.getElementById('checkOutbound').checked = true;
                toggleOption('outbound');
                document.getElementById('outboundQty').value = a.outbound_rider_number;
                document.getElementById('outboundPrice').value = a.outbound_amount;
            }

            toggleTotalDisplay();
            calculateTotal();

        } catch (err) {
            console.error('Edit load failed:', err);
        }
    }

    /* ================= INIT ================= */

    loadAgencyTypes();
    loadProductPlans();
    calculateTotal();
    toggleTotalDisplay();

    const editId = getQueryParam('edit_id');
    if (editId) {
        loadAllocationForEdit(editId);
    }

});
