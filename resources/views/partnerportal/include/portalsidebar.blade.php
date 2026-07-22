<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Font Awesome -->
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<style>
/* ================= RESET ================= */
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Inter', sans-serif;
}

/* ================= SIDEBAR ================= */
.portal-sidebar {
    width: 260px;
    height: 100vh;
    background: #ffffff;
    padding: 24px 20px;
    position: fixed;
    top: 0;
    left: 0;
    box-shadow: 4px 0 12px rgba(0,0,0,0.06);
    transition: transform 0.3s ease;
    z-index: 1000;
}

/* ================= MAIN OFFSET ================= */
.portal-main {
    margin-left: 260px;
    /* padding: 0 20px 20px 0;  */
}


/* ================= LOGO ================= */
.sidebar-logo {
    margin-bottom: 36px;
}

.sidebar-logo img {
    width: 170px;
}

/* ================= MENU ================= */
.sidebar-menu {
    list-style: none;
}

.menu-item {
    margin-bottom: 6px;
}

.menu-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    text-decoration: none;
    font-size: 15px;
    font-weight: 500;
    color: #1f2937;
    border-radius: 6px;
}

/* ICON */
.menu-link i {
    font-size: 18px;
    color: #64748b;
}

/* ACTIVE */
.menu-item.active .menu-link {
    background: #e3f2fd;
    color: #0284c7;
}

.menu-item.active i {
    color: #0284c7;
}

/* HOVER */
.menu-link:hover {
    background: #f1f5f9;
}

/* ================= MOBILE ADDITIONS ================= */
.sidebar-toggle {
    display: none;
    position: fixed;
    top: 14px;
    left: 14px;
    z-index: 1100;
    background: #0284c7;
    color: #fff;
    border: none;
    padding: 10px 12px;
    border-radius: 6px;
    font-size: 18px;
}

.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    z-index: 900;
}

/* ================= RESPONSIVE ================= */
@media (max-width: 991px) {
    .portal-sidebar {
        transform: translateX(-100%);
    }

    .portal-sidebar.active {
        transform: translateX(0);
    }

    .portal-main {
        margin-left: 0;
        padding-top: 60px;
    }

    .sidebar-toggle {
        display: block;
    }

    .sidebar-overlay.active {
        display: block;
    }
}
</style>
</head>

<body>

<!-- MOBILE TOGGLE BUTTON -->
<button class="sidebar-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- OVERLAY -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- SIDEBAR -->
<div class="portal-sidebar" id="sidebar">

    <div class="sidebar-logo">
        <img src="{{ asset('asset/image/mulkmed.png') }}" alt="MulkMed Healthcare">
    </div>

    <ul class="sidebar-menu">

        <li class="menu-item {{ request()->routeIs('partner.dashboard') ? 'active' : '' }}">
            <a href="{{ route('partner.dashboard') }}" class="menu-link">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
        </li>

        <li class="menu-item {{ request()->routeIs('partner.subscription') ? 'active' : '' }}">
            <a href="{{ route('partner.subscription') }}" class="menu-link">
                <i class="fas fa-gem"></i>
                <span>Subscription</span>
            </a>
        </li>

        <li class="menu-item {{ request()->routeIs('partner.excel') ? 'active' : '' }}">
            <a href="{{ route('partner.excel') }}" class="menu-link">
                <i class="fas fa-file-excel"></i>
                <span>Excel</span>
            </a>
        </li>

        <li class="menu-item {{ request()->routeIs('partner.upload-history') ? 'active' : '' }}">
            <a href="{{ route('partner.upload-history') }}" class="menu-link">
                <i class="fas fa-history"></i>
                <span>Upload History</span>
            </a>
        </li>

    </ul>

</div>

<!-- SCRIPT -->
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
    document.querySelector('.sidebar-overlay').classList.toggle('active');
}
</script>

</body>
</html>
