document.addEventListener("DOMContentLoaded", () => {
  /* =========================
       STATE
    ========================= */
  const sections = ["summary-section", "details-section", "usage-section"];
  let TRANSACTIONS = [];
  let ACTIVE_AGENCY = [];

  const badgeMap = {
    HOTEL: "hotel",
    VISA: "visa",
    TRAVEL: "travel",
  };

  const iconMap = {
    HOTEL: "fa-bed",
    VISA: "fa-file-invoice",
    TRAVEL: "fa-plane",
  };

  /* =========================
       SECTION SWITCH
    ========================= */
  function showSection(id) {
    sections.forEach((sec) => {
      const el = document.getElementById(sec);
      if (el) el.style.display = sec === id ? "block" : "none";
    });
    window.scrollTo(0, 0);
  }

  /* =========================
       LOAD TRANSACTIONS
    ========================= */
  fetch("/v2/getTransactionHistory")
    .then((r) => r.json())
    .then((res) => {
      TRANSACTIONS = res.transaction_history || [];

      TRANSACTIONS.sort(
        (a, b) => new Date(b.created_at) - new Date(a.created_at),
      );

      renderSummary();
      populateAgencyFilter();
      populateAgencyTypeFilter();
    })
    .catch((err) => console.error("Transaction API Error:", err));

  /* =========================
       SUMMARY TABLE
    ========================= */
  function renderSummary() {
    const tbody = document.getElementById("transaction-summary-body");
    if (!tbody) return;

    tbody.innerHTML = "";

    TRANSACTIONS.forEach((t) => {
      const agencyType = t.agency_type?.toUpperCase();

      // ✅ SERVICE TYPE FIX (ONLY FOR VISA)
      let service = "-";

      if (agencyType === "VISA") {
        service = "Visa Service";
      } else {
        const inbound = Number(t.inbound) || 0;
        const outbound = Number(t.outbound) || 0;

        service =
          inbound && outbound
            ? "Inbound / Outbound"
            : inbound
            ? "Inbound"
            : outbound
            ? "Outbound"
            : "-";
      }

      const displayAmount = Number(t.amount) || 0;

      tbody.innerHTML += `
        <tr>
          <td>${fmtDate(t.created_at)}</td>
          <td>${fmtTime(t.created_at)}</td>
          <td>${t.name}</td>
          <td>
            <span class="badge badge-${badgeMap[agencyType]}">
              <i class="fas ${iconMap[agencyType]}"></i>
              ${agencyType}
            </span>
          </td>
          <td>
            <span class="badge-payment badge-${t.payment_type.toLowerCase()}">
              ${t.payment_type}
            </span>
          </td>
          <td>${service}</td>
          <td class="bold-text">AED ${displayAmount}</td>
          <td>
            <button 
              class="btn btn-sm btn-outline-primary"
              onclick="downloadInvoiceByAdmin('${t.id}')">
              <i class="fas fa-download"></i> Download
            </button>
          </td>
        </tr>
      `;
    });
  }

  window.downloadInvoiceByAdmin = async function (id) {
    if (!id) {
      alert("Invoice not available");
      return;
    }

    try {
      const response = await fetch(
        domainUrl + "download-subscription-invoicebyadmin?id=" + id,
        {
          method: "GET",
          credentials: "include",
          headers: {
            "Accept":
              "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
          },
        },
      );

      if (!response.ok) {
        throw new Error("Download failed");
      }

      const blob = await response.blob();
      const blobUrl = window.URL.createObjectURL(blob);

      const a = document.createElement("a");
      a.href = blobUrl;
      a.download = `invoice_${id}.xlsx`;
      document.body.appendChild(a);
      a.click();

      a.remove();
      window.URL.revokeObjectURL(blobUrl);
    } catch (err) {
      console.error("Download error:", err);
      alert("Download failed");
    }
  }






  /* =========================
       VIEW DETAILS
       (kept unchanged)
    ========================= */
  document.body.addEventListener("click", (e) => {
    const btn = e.target.closest(".view-details-link-trigger");
    if (!btn) return;

    const agencyName = btn.dataset.agency;
    ACTIVE_AGENCY = TRANSACTIONS.filter((t) => t.name === agencyName);
    if (!ACTIVE_AGENCY.length) return;

    const head = ACTIVE_AGENCY[0];

    document.getElementById("detail-agency-name").textContent = head.name;

    const agencyBadge = document.getElementById("detail-agency-badge");
    agencyBadge.className = `badge badge-${badgeMap[head.agency_type]}`;
    agencyBadge.textContent = head.agency_type;

    const paymentBadge = document.getElementById("detail-payment-badge");
    paymentBadge.className = `badge-payment badge-${head.payment_type.toLowerCase()}`;
    paymentBadge.textContent = head.payment_type;

    renderDetailsTable();
    showSection("details-section");
  });

  /* =========================
       DETAILS TABLE (MONTHLY)
       (logic kept as-is)
    ========================= */
  function renderDetailsTable() {
    const tbody = document.getElementById("details-body");
    if (!tbody) return;

    tbody.innerHTML = "";

    const grouped = {};

    ACTIVE_AGENCY.forEach((t) => {
      const month = new Date(t.created_at).toLocaleString("default", {
        month: "short",
        year: "numeric",
      });

      if (!grouped[month]) grouped[month] = [];
      grouped[month].push(t);
    });

    Object.entries(grouped).forEach(([month, rows]) => {
      const riders = rows.reduce(
        (sum, r) => sum + (Number(r.inbound) || 0) + (Number(r.outbound) || 0),
        0,
      );

      tbody.innerHTML += `
          <tr>
            <td>${fmtDate(rows[0].created_at)}</td>
            <td>${month}</td>
            <td>${riders}</td>
            <td>-</td>
            <td>${rows[0].inbound ? "Inbound" : "Outbound"}</td>
            <td>
              <span class="status-badge status-paid">Paid</span>
            </td>
            <td>-</td>
            <td>
              <a href="javascript:void(0)"
                 class="view-usage-link-trigger"
                 data-usage='${JSON.stringify(rows)}'>
                View
              </a>
            </td>
          </tr>
        `;
    });
  }

  /* =========================
       RIDER USAGE
    ========================= */
  document.body.addEventListener("click", (e) => {
    const btn = e.target.closest(".view-usage-link-trigger");
    if (!btn) return;

    const rows = JSON.parse(btn.dataset.usage || "[]");
    const tbody = document.getElementById("usage-body");
    if (!tbody) return;

    tbody.innerHTML = "";

    rows.forEach((r) => {
      const count = (Number(r.inbound) || 0) + (Number(r.outbound) || 0);

      tbody.innerHTML += `
          <tr>
            <td>${fmtDate(r.created_at)}</td>
            <td class="text-center">${count}</td>
          </tr>
        `;
    });

    showSection("usage-section");
  });

  /* =========================
       BACK NAVIGATION
    ========================= */
  document.body.addEventListener("click", (e) => {
    const back = e.target.closest(".back-link-trigger");
    if (!back) return;
    showSection(back.dataset.section + "-section");
  });

  /* =========================
       FILTER SEARCH
    ========================= */
  document.getElementById("search-btn")?.addEventListener("click", () => {
    const name = document
      .getElementById("filter-agency-name")
      .value.toLowerCase();
    const pay = document
      .getElementById("filter-payment-type")
      .value.toLowerCase();
    const type = document
      .getElementById("filter-agency-type")
      .value.toLowerCase();

    document.querySelectorAll("#transaction-summary-body tr").forEach((r) => {
      const rName = r.cells[2].textContent.toLowerCase();
      const rType = r.cells[3].textContent.toLowerCase();
      const rPay = r.cells[4].textContent.toLowerCase();

      const visible =
        (!name || rName.includes(name)) &&
        (!pay || rPay.includes(pay)) &&
        (!type || rType.includes(type));

      r.style.display = visible ? "" : "none";
    });
  });

  /* =========================
       FILTER POPULATORS
    ========================= */
  function populateAgencyFilter() {
    const sel = document.getElementById("filter-agency-name");
    if (!sel) return;

    sel.innerHTML = `<option value="">All Agencies</option>`;

    [...new Set(TRANSACTIONS.map((t) => t.name))]
      .filter(Boolean)
      .forEach((n) => {
        sel.innerHTML += `<option value="${n.toLowerCase()}">${n}</option>`;
      });
  }

  function populateAgencyTypeFilter() {
    const sel = document.getElementById("filter-agency-type");
    if (!sel) return;

    sel.innerHTML = `<option value="">All Types</option>`;

    [...new Set(TRANSACTIONS.map((t) => t.agency_type))]
      .filter(Boolean)
      .forEach((type) => {
        sel.innerHTML += `<option value="${type.toLowerCase()}">${type}</option>`;
      });
  }

  /* =========================
       DATE HELPERS
    ========================= */
  function fmtDate(d) {
    return new Date(d).toLocaleDateString("en-GB");
  }

  function fmtTime(d) {
    return new Date(d).toLocaleTimeString("en-US", {
      hour: "2-digit",
      minute: "2-digit",
    });
  }
});
