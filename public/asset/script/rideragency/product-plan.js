/*
=====================================================
PRODUCT PLAN JS – FINAL (with API Integration)
=====================================================
*/

document.addEventListener("DOMContentLoaded", () => {
    loadProductPlans();
});

let plans = [];
let editingPlanId = null;

/* ================= TOAST HELPER ================= */

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

/* ================= MODAL ================= */

function openPlanModal(title = "Add New Product Plan", btnText = "Create") {
    const overlay = document.getElementById("planOverlay");
    const modal = document.getElementById("planModal");
    const titleEl = document.getElementById("modalTitle");
    const btnEl = document.getElementById("saveBtn");

    if (titleEl) titleEl.textContent = title;
    if (btnEl) btnEl.textContent = btnText;

    if (overlay) overlay.style.display = "block";
    if (modal) modal.style.display = "block";
}

function closePlanModal() {
    const overlay = document.getElementById("planOverlay");
    const modal = document.getElementById("planModal");
    if (overlay) overlay.style.display = "none";
    if (modal) modal.style.display = "none";

    document.getElementById("planName").value = "";
    document.getElementById("planDescription").value = "";
    document.getElementById("editingPlanId").value = "";
    editingPlanId = null;

    document.getElementById("modalTitle").textContent = "Add New Product Plan";
    document.getElementById("saveBtn").textContent = "Create";
}

/* ================= FETCH PRODUCT PLANS ================= */

async function loadProductPlans() {
    try {
        const res = await fetch(domainUrl + "getProductPlan");
        const data = await res.json();

        plans = []; // reset before loading

        if (data.status === true && Array.isArray(data.product_plans)) {
            data.product_plans.forEach(p => {
                plans.push({
                    id: p.id,
                    name: p.name,
                    desc: p.description
                });
            });
        }

        renderPlans();

    } catch (err) {
        console.error("Failed to load product plans", err);
    }
}

/* ================= EDIT PLAN ================= */

async function editPlan(id) {
    try {
        // Re-fetch all plans (this WILL show in Network)
        const res = await fetch(domainUrl + "getProductPlan");
        const data = await res.json();

        if (!data.status || !Array.isArray(data.product_plans)) {
            showToast("Failed to fetch plans", "error");
            return;
        }

        const plan = data.product_plans.find(p => p.id === id);

        if (!plan) {
            showToast("Plan not found", "error");
            return;
        }

        document.getElementById("planName").value = plan.name || "";
        document.getElementById("planDescription").value = plan.description || "";

        editingPlanId = id;
        document.getElementById("editingPlanId").value = id;

        openPlanModal("Edit Product Plan", "Update");

    } catch (err) {
        showToast("Error loading plan", "error");
    }
}



/* ================= SAVE PLAN (CREATE/UPDATE) ================= */

async function savePlan() {
    const nameInput = document.getElementById("planName");
    const descInput = document.getElementById("planDescription");
    const name = nameInput.value.trim();
    const desc = descInput.value.trim();

    if (!name) {
        showToast("Plan name required", "error");
        return;
    }

    const payload = {
        name: name,
        description: desc
    };

    let url = domainUrl + "addProductPlan";

    // Check if we are in edit mode
    if (editingPlanId) {
        url = domainUrl + "updateProductPlan";
        payload.id = editingPlanId;
    }

    try {
        const res = await fetch(url, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify(payload)
        });

        const data = await res.json();

        if (!res.ok || (data.status === false && data.success === false)) {
            throw new Error(data.message || "Failed to save product plan");
        }

        showToast(data.message || (editingPlanId ? "Product plan updated successfully" : "Product plan added successfully"));
        closePlanModal();
        loadProductPlans();

    } catch (err) {
        showToast(err.message, "error");
    }
}

/* ================= DELETE PLAN ================= */

async function deletePlan(id) {
    // if (!confirm("Are you sure you want to delete this plan?")) return;

    try {
        const res = await fetch(domainUrl + "deleteProductPlan/" + id);
        const data = await res.json();

        if (!res.ok || (data.status === false && data.success === false)) {
            throw new Error(data.message || "Failed to delete plan");
        }

        showToast(data.message || "Plan deleted successfully");
        loadProductPlans();

    } catch (err) {
        showToast(err.message, "error");
    }
}

/* ================= RENDER PLANS ================= */

function renderPlans() {
    const list = document.getElementById("planList");
    const empty = document.getElementById("emptyState");

    if (!list || !empty) return;

    if (plans.length === 0) {
        empty.style.display = "block";
        list.className = ""; // remove grid layout if any
        list.innerHTML = "";
        return;
    }

    empty.style.display = "none";
    list.className = "plan-list";
    list.innerHTML = "";

    plans.forEach((p) => {
        list.innerHTML += `
            <div class="plan-card">
                <h4>${p.name}</h4>
                <p>${p.desc || "-"}</p>
                <div class="plan-actions">
                    <a style="color:#ef4444; display:flex; gap:6px; align-items:center;" onclick="deletePlan(${p.id})">
                        <svg width="20" height="22" viewBox="0 0 20 22" fill="none">
                          <path d="M17.5 4.5L16.88 14.525C16.722 17.086 16.643 18.367 16 19.288C15.6826 19.7432 15.2739 20.1273 14.8 20.416C13.843 21 12.56 21 9.994 21C7.424 21 6.139 21 5.18 20.415C4.70589 20.1257 4.29721 19.7409 3.98 19.285C3.338 18.363 3.26 17.08 3.106 14.515L2.5 4.5M1 4.5H19M14.056 4.5L13.373 3.092C12.92 2.156 12.693 1.689 12.302 1.397C12.2151 1.33232 12.1232 1.27479 12.027 1.225C11.594 1 11.074 1 10.035 1C8.969 1 8.436 1 7.995 1.234C7.89752 1.28621 7.80453 1.34642 7.717 1.414C7.322 1.717 7.101 2.202 6.659 3.171L6.053 4.5" stroke="#FF3B30" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        Delete
                    </a>

                    <a style="color:#008CC3; display:flex; gap:6px; align-items:center;" onclick="editPlan(${p.id})">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                          <path d="M7 7H6C5.46957 7 4.96086 7.21071 4.58579 7.58579C4.21071 7.96086 4 8.46957 4 9V18C4 18.5304 4.21071 19.0391 4.58579 19.4142C4.96086 19.7893 5.46957 20 6 20H15C15.5304 20 16.0391 19.7893 16.4142 19.4142C16.7893 19.0391 17 18.5304 17 18V17M13.5 10.5L17.5 6.5M16.5 3.5L20.5 7.5L11 17H7V13L16.5 3.5Z" stroke="#008CC3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Edit
                    </a>
                </div>
            </div>
        `;
    });
}
