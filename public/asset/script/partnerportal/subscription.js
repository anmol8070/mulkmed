/*
=====================================================
 SUBSCRIPTION HISTORY – IMAGE STYLE PAGINATION
=====================================================
*/

console.log("subscription.js loaded");

/* ================= API ================= */

// ✅ FIXED API NAME
const HISTORY_API = domainUrl + "partner/getTransactionHistory";
const ROWS_PER_PAGE = 5;

/* ================= STATE ================= */

let currentPage = 1;
let lastPage = 1;
let totalEntries = 0;

/* ================= LOAD SUBSCRIPTION HISTORY ================= */

async function loadSubscriptionHistory(page = 1) {
    console.log("loadSubscriptionHistory() called | Page:", page);

    const tbody = document.querySelector("#historyTable tbody");
    const info = document.getElementById("paginationInfo");
    const controls = document.getElementById("paginationControls");

    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="7">Loading...</td></tr>`;

    try {
        const response = await fetch(`${HISTORY_API}?page=${page}`, {
            method: "GET",
            credentials: "include",
            headers: { "Accept": "application/json" }
        });

        const result = await response.json();

        if (!response.ok || result.status !== true) {
            throw new Error("API error");
        }

        const pagination = result.transaction_history;
        const history = pagination.data || [];

        currentPage = pagination.current_page;
        lastPage = pagination.last_page;
        totalEntries = pagination.total;

        tbody.innerHTML = "";

        if (history.length === 0) {
            tbody.innerHTML =
                `<tr><td colspan="7">No subscription history found</td></tr>`;
        }

        /* ================= TABLE ROWS ================= */

        history.slice(0, ROWS_PER_PAGE).forEach(item => {

            const dateObj = new Date(item.created_at);

            const date = dateObj.toLocaleDateString("en-GB", {
                day: "2-digit",
                month: "short",
                year: "numeric"
            });

            const time = dateObj.toLocaleTimeString("en-US", {
                hour: "2-digit",
                minute: "2-digit",
                hour12: true
            });

            /* ===== PAYMENT TYPE ===== */
            const paymentType = item.payment_type || "Prepaid";
            const badgeClass =
                paymentType === "Postpaid" ? "badge-postpaid" : "badge-prepaid";

            /* ===== RIDERS & SERVICE TYPE (FIXED) ===== */
            const inbound = Number(item.inbound_number_of_rider) || 0;
const outbound = Number(item.outbound_number_of_rider) || 0;
const totalRiders = inbound + outbound;


            let serviceHTML = "";

            if (inbound > 0) {
                serviceHTML += `
                    <div class="service-icon inbound">
                        <span>Inbound</span>
                        <i class="fas fa-arrow-down"></i>
                    </div>`;
            }

            if (outbound > 0) {
                serviceHTML += `
                    <div class="service-icon outbound">
                        <span>Outbound</span>
                        <i class="fas fa-arrow-up"></i>
                    </div>`;
            }

            tbody.insertAdjacentHTML("beforeend", `
                <tr>
                    <td>${date}</td>
                    <td>${time}</td>
                    <td>
                        <span class="badge-payment ${badgeClass}">
                            ${paymentType}
                        </span>
                    </td>
                    <td>AED ${item.amount || 0}</td>
                    <td>${totalRiders}</td>
                    <td>${serviceHTML}</td>
                    <td>
    <a href="javascript:void(0)"
       class="download"
       data-id="${item.plan_id}">
       Download
    </a>
</td>

                </tr>
            `);
        });

        /* ================= DOWNLOAD INVOICE (SAFE FINAL) ================= */

document.querySelectorAll(".download").forEach(btn => {
    btn.addEventListener("click", () => {

        const id = btn.dataset.id;

        // 🚫 Stop bad requests
        if (!id || id === "undefined" || id === "null") {
            alert("Invoice not available for this record");
            return;
        }

        // console.log("Downloading invoice for subscription ID:", id);

        const url =
            domainUrl + "downloadSubscriptionInvoice?id=" + id;

        window.open(url, "_blank");
    });
});


        /* ================= INFO TEXT ================= */

        const start = (currentPage - 1) * pagination.per_page + 1;
        const end = Math.min(start + pagination.per_page - 1, totalEntries);

        if (info) {
            info.innerText = `Showing ${start} to ${end} of ${totalEntries} entries`;
        }

        /* ================= PAGINATION ================= */

        renderPaginationControls(controls);

    } catch (error) {
        console.error("Subscription History Error:", error);
        tbody.innerHTML =
            `<tr><td colspan="7">Failed to load subscription history</td></tr>`;
    }
}

/* ================= PAGINATION CONTROLS ================= */

function renderPaginationControls(container) {
    if (!container) return;

    const nextDisabled = currentPage === lastPage;

    container.innerHTML = `
        <div class="custom-pagination d-flex align-items-center gap-4">
            <a href="javascript:void(0)" 
               class="pagination-next ${nextDisabled ? 'disabled' : ''}" 
               onclick="${!nextDisabled ? `loadSubscriptionHistory(${currentPage + 1})` : ''}">
               Next
            </a>

            <div class="pagination-pages d-flex align-items-center gap-2">
                <span class="page-box">${currentPage}</span>
                <span class="page-of">Of ${lastPage}</span>
            </div>
        </div>
    `;
}

/* ================= TOGGLE HISTORY ================= */

function toggleHistory() {
    const table = document.getElementById("historyTable");
    const text = document.getElementById("toggleText");
    const icon = document.getElementById("toggleIcon");

    const open = table.style.display === "block";

    table.style.display = open ? "none" : "block";
    text.innerText = open ? "Show" : "Hide";
    icon.className = open
        ? "fas fa-chevron-down"
        : "fas fa-chevron-up";

    if (!open) {
        loadSubscriptionHistory(1);
    }
}


/* ================= SVG ICONS ================= */

function basicSVG() {
    return `
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
        <rect width="24" height="24" rx="12" fill="#ECF6FF"/>
        <path d="M12 15.5L14.8 17.2C15.3 17.5 15.9 17 15.7 16.5
                 L15 13.3L17.5 11.2C17.9 10.8 17.7 10.1 17.1 10
                 L13.9 9.8L12.6 6.8C12.4 6.2 11.6 6.2 11.4 6.8
                 L10.1 9.8L6.9 10C6.3 10.1 6.1 10.8 6.5 11.2
                 L9 13.3L8.3 16.5C8.1 17 8.7 17.5 9.2 17.2
                 L12 15.5Z"
              fill="#607198"/>
    </svg>`;
}

function advanceSVG() {
    return `
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
        <rect width="24" height="24" rx="12" fill="#ECF6FF"/>
        <path d="M16.6 16.6H7.3C7 16.6 6.7 16.4 6.6 16.1
                 L5.3 9.4C5.3 9.1 5.4 8.9 5.6 8.7
                 C5.9 8.6 6.2 8.6 6.4 8.7
                 L9.1 10.9L11.4 7.5
                 C11.7 7.1 12.3 7.1 12.5 7.5
                 L14.8 10.9L17.5 8.7
                 C17.7 8.5 18 8.5 18.2 8.7
                 C18.5 8.9 18.6 9.1 18.6 9.4
                 L17.3 16.1C17.3 16.4 16.9 16.6 16.6 16.6Z"
              fill="#008CC3"/>
    </svg>`;
}

/* ================= HANDLE PAY NOW ================= */

async function handlePayNow(button) {
    console.log("handlePayNow triggered", button);
    
    const isPostpaid = window.subscriptionConfig?.isPostpaid === true;
    const planId = button.dataset.planId;

    console.log("Config Info:", { isPostpaid, planId });

    if (isPostpaid && planId) {
        const apiUrl = domainUrl + 'partner/getSubscriptionInfo?plan_id=' + planId;
        console.log("Postpaid: Fetching subscription info from:", apiUrl);
        
        try {
            const response = await fetch(apiUrl, {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'Accept': 'application/json'
                }
            });

            console.log("Response status:", response.status);

            if (!response.ok) {
                console.error("API Error: getSubscriptionInfo failed");
            } else {
                const result = await response.json();
                console.log("Subscription Info Result:", result);
            }
        } catch (error) {
            console.error("Fetch Error details:", error);
        }
    } else {
        console.warn("Skipping API call: isPostpaid or planId missing/false");
    }

    // Always open modal
    openUpcomingInvoiceModal();
}

window.handlePayNow = handlePayNow;

/* ================= MODAL ACTIONS ================= */

function openUpcomingInvoiceModal() {
    const modal = document.getElementById("upcomingInvoiceModal");
    if (modal) {
        modal.style.display = "flex";
        document.body.style.overflow = "hidden";

        // ✅ START COUNTDOWN IF POSTPAID
        if (window.subscriptionConfig?.isPostpaid && window.subscriptionConfig?.expiryDate) {
            startCountdown(window.subscriptionConfig.expiryDate);
        }
    }
}

function closeUpcomingInvoiceModal() {
    const modal = document.getElementById("upcomingInvoiceModal");
    if (modal) {
        modal.style.display = "none";
        document.body.style.overflow = "auto";
    }
}

// Close modal when clicking outside the modal-box
window.addEventListener("click", (event) => {
    const upcomingModal = document.getElementById("upcomingInvoiceModal");
    if (event.target === upcomingModal) {
        closeUpcomingInvoiceModal();
    }
});

/* ================= COUNTDOWN TIMER ================= */

let countdownInterval;

function startCountdown(expiryDate) {
    const countdownElement = document.getElementById("countdownTimer");
    if (!countdownElement) return;

    const daysEl = document.getElementById("days");
    const hoursEl = document.getElementById("hours");
    const minutesEl = document.getElementById("minutes");
    const secondsEl = document.getElementById("seconds");

    // Clear existing interval if any
    if (countdownInterval) clearInterval(countdownInterval);

    const targetDate = new Date(expiryDate).getTime();

    function update() {
        const now = new Date().getTime();
        const distance = targetDate - now;

        if (distance < 0) {
            clearInterval(countdownInterval);
            if (daysEl) daysEl.innerText = "00";
            if (hoursEl) hoursEl.innerText = "00";
            if (minutesEl) minutesEl.innerText = "00";
            if (secondsEl) secondsEl.innerText = "00";
            return;
        }

        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        if (daysEl) daysEl.innerText = String(days).padStart(2, '0');
        if (hoursEl) hoursEl.innerText = String(hours).padStart(2, '0');
        if (minutesEl) minutesEl.innerText = String(minutes).padStart(2, '0');
        if (secondsEl) secondsEl.innerText = String(seconds).padStart(2, '0');
    }

    update();
    countdownInterval = setInterval(update, 1000);
}
/* =====================================================
   PAY NOW – PREPAID vs POSTPAID (FRONTEND SAFE VERSION)
===================================================== */

document.addEventListener("click", async function (e) {

    const btn = e.target.closest(".btn-pay-modal");
    if (!btn || btn.disabled) return;

    btn.disabled = true;
    btn.innerText = "Processing...";

    /* ===============================
       GET SUBSCRIPTION ID
    =============================== */
    const modal = btn.closest(".modal-container");
    const subscriptionId =
        modal?.dataset.subscriptionId ||
        btn.dataset.subscriptionId;

    if (!subscriptionId) {
        resetBtn(btn);
        return;
    }

    /* ===============================
       CSRF TOKEN
    =============================== */
    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");

    if (!csrfToken) {
        resetBtn(btn);
        return;
    }

    try {

        /* =====================================================
           POSTPAID FLOW
        ===================================================== */
        if (window.subscriptionConfig?.isPostpaid === true) {

            // pehle user action ke time blank window open karo
                    // let paymentWindow = window.open('', '_blank');

                    const response = await fetch(
                        domainUrl + "partner/paymentInitiateForPostpaid",
                        {
                            method: "POST",
                            credentials: "include",
                            headers: {
                                "Accept": "application/json",
                                "Content-Type": "application/json",
                                "X-CSRF-TOKEN": csrfToken
                            },
                            body: JSON.stringify({
                                id: subscriptionId
                            })
                        }
                    );

                    const result = await response.json();

                    // console.log(result);

                  
                    

                    if (response.ok && result.status === true) {
                        // alert(result.payment_url);
                        // yahan actual payment URL load karo
                        
                          window.open(result.payment_url, "_self", "noreferrer");

                    } else {
                        // paymentWindow.close();
                        alert("Something went wrong!");
                    }

           // old code
            // const response = await fetch(
            //     domainUrl + "partner/paymentInitiateForPostpaid",
            //     {
            //         method: "POST",
            //         credentials: "include",
            //         headers: {
            //             "Accept": "application/json",
            //             "Content-Type": "application/json",
            //             "X-CSRF-TOKEN": csrfToken
            //         },
            //         body: JSON.stringify({ id: subscriptionId })
            //     }
            // );

            // const text = await response.text();
            // const contentType = response.headers.get("content-type") || "";


            // /* ===============================
            //    SESSION / LOGIN / HTML RESPONSE
            // =============================== */
            // if (response.status == 200) {
            //     alert("Payment completed successfully!");
            //     window.location.reload();
            // }else{
            //     alert("something went wrong!");
            // }

            // /* ===============================
            //    NON-JSON RESPONSE
            // =============================== */
            // if (!contentType.includes("application/json")) {
            //     resetBtn(btn);
            //     return;
            // }

            // /* ===============================
            //    SAFE JSON PARSE
            // =============================== */
            // let result;
            // try {
            //     result = JSON.parse(text);
            // } catch {
            //     resetBtn(btn);
            //     return;
            // }

            // /* ===============================
            //    API FAILURE
            // =============================== */
            // if (!response.ok || result.status !== "Success") {
            //     resetBtn(btn);
            //     return;
            // }

            // /* ===============================
            //    PAYMENT GATEWAY REDIRECT
            // =============================== */
            // if (result.data?.payment_url) {
            //     // window.location.href = result.data.payment_url;
            //     window.open(result.data.payment_url, "_blank", "noreferrer");
            //     return;
            // }

            // /* ===============================
            //    FALLBACK
            // =============================== */
            // window.location.href = domainUrl + "partner/subscription";
            // return;
        }

        // old code
        // const response = await fetch(
        //         domainUrl + "partner/addAgencySubscriptionPlan",
        //         {
        //             method: "POST",
        //             credentials: "include",
        //             headers: {
        //                 "Accept": "application/json",
        //                 "Content-Type": "application/json",
        //                 "X-CSRF-TOKEN": csrfToken
        //             },
        //             body: JSON.stringify({
        //                 subscription_id: subscriptionId
        //             })
        //         }
        //     );

        // if (response.ok) {
        //     alert("Subscription completed successfully!");
        //     window.location.href = domainUrl + "partner/subscription";
        // } else {
        //     alert("Something went wrong!");
        // }

        // old code 

        const response = await fetch(
                        domainUrl + "partner/addAgencySubscriptionPlan",
                        {
                            method: "POST",
                            credentials: "include",
                            headers: {
                                "Accept": "application/json",
                                "Content-Type": "application/json",
                                "X-CSRF-TOKEN": csrfToken
                            },
                            body: JSON.stringify({
                                subscription_id: subscriptionId
                            })
                        }
                    );

                    const result = await response.json();

                    if (response.ok && result.status === true) {
                        // payment gateway URL load karo
                        // window.location.href = result.payment_url;
                        window.open(result.payment_url, "_self", "noreferrer");

 
                    } else {
                        alert("Something went wrong!");
                    }


    } catch (err) {
        console.error("PAY NOW ERROR:", err);
        resetBtn(btn);
    }
});

/* ===============================
   RESET BUTTON
=============================== */
function resetBtn(btn) {
    btn.disabled = false;
    btn.innerText = "Pay Now";
}

/* =====================================================
   AUTO REDIRECT AFTER PAYMENT SUCCESS
===================================================== */

(function () {

    const url = window.location.href;

    if (url.includes("/v2/paymentSuccess")) {

        const orderId = new URLSearchParams(window.location.search)
            .get("order_id");

        document.body.innerHTML = `
            <div style="text-align:center;margin-top:100px;font-family:Arial">
                <h2>✅ Payment Successful</h2>
                ${orderId ? `<p>Order ID: ${orderId}</p>` : ""}
                <p>Redirecting to your subscription...</p>
            </div>
        `;

        setTimeout(() => {
            window.location.href = "/partner/subscription";
        }, 1500);
    }

})();



function subscribePlan(button) {

    const subscriptionId = button.dataset.subscriptionId;

    if (!subscriptionId) {
        alert("Subscription ID missing");
        return;
    }

    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");

    button.disabled = true;
    button.innerText = "Processing...";

    const form = document.createElement("form");
    form.method = "POST";
    form.action = domainUrl + "partner/addAgencySubscriptionPlan";

    form.innerHTML = `
        <input type="hidden" name="_token" value="${csrfToken}">
        <input type="hidden" name="subscription_id" value="${subscriptionId}">
    `;

    document.body.appendChild(form);
    form.submit(); // ✅ Laravel redirect works perfectly
}

