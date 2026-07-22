<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Portal Upper Navbar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* ================= NAVBAR ================= */
        .portal-navbar {
            height: 90px;
            width: 100%;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            font-family: 'Inter', sans-serif;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            position: sticky;
            top: 0;
            z-index: 900;
        }

        /* ================= LEFT ================= */
        .navbar-left h1 {
            color: #0C2133;
            margin-bottom: 4px;
            font-weight: 600;
            font-size: 24px;
        }

        .navbar-left p {
            color: #607198;
            margin: 0;
            font-weight: 500;
            font-style: Medium;
            font-size: 14px;
        }

        /* ================= RIGHT ================= */
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #0C2133;
            /* font-family: Poppins; */
            font-weight: 500;
            
            font-size: 18px;


        }

        .navbar-right img {
            width: 56px;
            height: 56px;
            border-radius: 48px;
            object-fit: cover;
            border: 1px solid #e5e7eb;
        }

        /* ================= PROFILE DROPDOWN ================= */
        .profile-wrapper {
            position: relative;
        }

        .profile-info {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 12px;
            transition: background 0.2s;
        }

        .profile-info:hover {
            background: #f8fafc;
        }

        .profile-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 12px;
            background: #fff;
            min-width: 180px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            display: none;
            flex-direction: column;
            padding: 8px;
            z-index: 1000;
        }

        .profile-dropdown.show {
            display: flex;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            color: #4B5563;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none !important;
            border-radius: 8px;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            background: transparent;
            width: 100%;
            text-align: left;
        }

        .dropdown-item:hover {
            background: #f1f5f9;
            color: #0C2133;
        }

        .dropdown-item.logout {
            color: #ef4444;
        }

        .dropdown-item.logout:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        .dropdown-divider {
            height: 1px;
            background: #f1f5f9;
            margin: 4px 0;
        }

        /* ================= MOBILE ================= */
        .navbar-toggle {
            display: none;
            background: transparent;
            border: none;
            font-size: 20px;
            color: #111827;
        }

        /* ================= RESPONSIVE ================= */
        @media (max-width: 991px) {
            .portal-navbar {
                padding: 0 20px;
            }

            .navbar-left h1 {
                font-size: 16px;
            }

            .navbar-left p {
                display: none;
            }
        }

        @media (max-width: 576px) {
            .navbar-left h1 {
                font-size: 15px;
            }

            .navbar-right span {
                display: none;
            }
        }
    </style>

   
<!-- ===== PORTAL NAVBAR ===== -->
<div class="portal-navbar">

    <!-- LEFT -->
    <div class="navbar-left">
        <h1>{{ $pageTitle ?? 'Dashboard' }}</h1>
        <p>{{ $pageSubtitle ?? "Welcome back! Here's your healthcare subscription overview" }}</p>
    </div>

    <!-- RIGHT -->
    <div class="navbar-right">
        <div class="profile-wrapper">

            @php
                // ===== STORE USER TYPE INTERNALLY (NOT DISPLAYED) =====
                session(['user_type' => 'hotel']);

                $agency_id = session('agency_id');
                $displayInfo = 'Partner Email';
                 $profile_img = asset('asset/img/profile.jpg');

                if ($agency_id) {
                    $agency = \App\Models\Agencies::find($agency_id);
                    if ($agency) {
                        $displayInfo = $agency->email;
                        $profile_img = $agency->logo ? asset('storage/' . $agency->logo): asset('asset/img/profile.jpg');
                    }
                }
            @endphp

            <div class="profile-info" onclick="toggleProfileDropdown(event)">
                <span>{{ $displayInfo }}</span>
                <img src="{{ $profile_img }}" alt="Profile">
            </div>

            <div class="profile-dropdown" id="profileDropdown">
                <!-- <a href="#" class="dropdown-item">
                    <i class="far fa-user"></i> My Profile
                </a>
                <a href="#" class="dropdown-item">
                    <i class="fas fa-cog"></i> Settings
                </a> -->

                <div class="dropdown-divider"></div>

                <button class="dropdown-item logout" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>

                <form id="logout-form" action="{{ route('partner.logout') }}" method="POST" style="display: none;">
                    @csrf
                </form>
            </div>

        </div>
    </div>

</div>

<script>
    function toggleProfileDropdown(event) {
        event.stopPropagation();
        const dropdown = document.getElementById('profileDropdown');
        dropdown.classList.toggle('show');
    }

    function handleLogout() {
        document.getElementById('logout-form').submit();
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('profileDropdown');
        const profileInfo = document.querySelector('.profile-info');

        if (dropdown && !dropdown.contains(event.target) && !profileInfo.contains(event.target)) {
            dropdown.classList.remove('show');
        }
    });
</script>

</body>
</html>