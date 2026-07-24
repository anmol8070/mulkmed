<!DOCTYPE html>
<html lang="en">


<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" name="viewport">
    <base href=".">
    @if (\App\Helpers\Helpers::get_role())
    <title>{{ __('MulkMed') }}</title>
    @else
    <title>{{ __('Medicare') }}</title>
    @endif
    {{-- Jquery --}}
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"
        integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <!-- include summernote css/js -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote.min.js"></script>

    @yield('header')

    <link rel="stylesheet" href="{{ asset('asset/css/app.min.css') }}">

    <link href="{{ asset('asset/css/style.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('asset/css/components.css') }}">
    <link rel="stylesheet" href="{{ asset('asset/css/custom.css') }}">

    <link rel='shortcut icon' type='image/x-icon' href='{{ asset('asset/img/favicon.ico') }}'
        style="width: 2px !important;" />

    <link rel="stylesheet" href="{{ asset('asset/bundles/codemirror/lib/codemirror.css') }}">
    <link rel="stylesheet" href=" {{ asset('asset/bundles/codemirror/theme/duotone-dark.css') }} ">
    <link rel="stylesheet" href=" {{ asset('asset/bundles/jquery-selectric/selectric.css') }}">
    <script src="{{ asset('asset/cdnjs/iziToast.min.js') }}"></script>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('asset/cdncss/iziToast.css') }}" />
    <script src="{{ asset('asset/cdnjs/sweetalert.min.js') }}"></script>
    <script src="{{ asset('asset/script/env.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('asset/style/app.css') }}?v=1.2.7">
    <style>
        /* 1. Fix Sidebar Overlap & Spacing (expanded sidebar only) */
        body:not(.sidebar-mini) .main-sidebar .sidebar-menu li.dropdown .dropdown-menu {
            position: static !important; /* Force items to stay in flow, pushing others down */
            display: none; /* JS will toggle this */
            padding: 0 0 0 15px !important; /* Indent sub-items */
            margin: 0 !important;
            box-shadow: none !important;
            width: 100% !important;
            background-color: transparent !important;
        }

        /* Open state controls visibility; overrides style.css li.active > ul.dropdown-menu { display:block } */
        body:not(.sidebar-mini) .main-sidebar .sidebar-menu li.dropdown.open > ul.dropdown-menu {
            display: block !important;
        }

        body:not(.sidebar-mini) .main-sidebar .sidebar-menu li.dropdown.active:not(.open) > ul.dropdown-menu {
            display: none !important;
        }

        body:not(.sidebar-mini) .main-sidebar .sidebar-menu li.dropdown.active.open > ul.dropdown-menu {
            display: block !important;
        }

        /* Mini collapsed: never spill labels or submenus over main content */
        body.sidebar-mini .main-sidebar .sidebar-menu li.dropdown .dropdown-menu {
            display: none !important;
        }

        body.sidebar-mini .main-sidebar:hover .sidebar-menu li.dropdown.open > ul.dropdown-menu,
        body.sidebar-mini .main-sidebar:hover .sidebar-menu li.dropdown.active.open > ul.dropdown-menu {
            display: block !important;
            position: static !important;
            padding: 0 0 0 15px !important;
            margin: 0 !important;
            box-shadow: none !important;
            width: 100% !important;
            background-color: transparent !important;
        }

        /* 2. Active Selection: Blue Background & Icon Visibility */
        .main-sidebar .sidebar-menu li.dropdown .dropdown-menu li.active a {
            background-color: #e3e7ff !important; /* Light blue background like parent headers */
            color: #6777ef !important; /* Active text color */
            font-weight: 600 !important;
            border-radius: 0 30px 30px 0 !important;
        }

        .main-sidebar .sidebar-menu li.dropdown .dropdown-menu li.active a i {
            color: #6777ef !important; /* Keep icon visible */
            display: inline-block !important; /* Force display */
            opacity: 1 !important;
            visibility: visible !important;
        }

        /* 3. Link Spacing & Hover Effects */
        .main-sidebar .sidebar-menu li.dropdown .dropdown-menu li a {
            background-color: transparent !important;
            cursor: default !important; /* Remove Hand Pointer */
            color: #868ba1 !important;
            display: flex !important;
            align-items: center !important;
            padding: 10px 15px !important;
            margin: 5px 0 !important;
            width: 95% !important;
            transition: all 0.2s ease;
        }

        .main-sidebar .sidebar-menu li.dropdown .dropdown-menu li a i {
            margin-right: 12px !important;
            font-size: 14px !important;
            width: 20px !important;
            text-align: center !important;
        }

        .main-sidebar .sidebar-menu li.dropdown .dropdown-menu li a:hover {
            color: #6777ef !important;
            background-color: transparent !important;
        }

        /* 4. Layout Polish: Remove Bullets & Ensure Parent Flow */
        .main-sidebar .sidebar-menu li.dropdown .dropdown-menu li a:before {
            display: none !important;
            content: none !important;
        }

        .main-sidebar .sidebar-menu li.dropdown {
            height: auto !important;
            position: relative !important;
            margin-bottom: 5px !important;
        }
    </style>
</head>

<body>
    {{-- <div class="loader"></div> --}}

    <div id="app">
        <div class="main-wrapper main-wrapper-1">
            <div class="navbar-bg"></div>
            <nav class="navbar navbar-expand-lg main-navbar sticky">
                <div class="form-inline mr-auto">
                    <ul class="navbar-nav mr-3">
                        <li><a href="#" data-toggle="sidebar" class="nav-link nav-link-lg
									collapse-btn">
                                <i data-feather="align-justify"></i></a></li>
                    </ul>
                </div>
                <ul class="navbar-nav navbar-right">

                    <li class="dropdown"><a href="#" data-toggle="dropdown"
                            class="nav-link dropdown-toggle nav-link-lg nav-link-user"> <span
                                class="d-sm-none d-lg-inline-block btn btn-light"> {{ __('Log Out') }} </span></a>
                        <div class="dropdown-menu dropdown-menu-right pullDown">

                            <a href="{{ route('logout') }}" class="dropdown-item has-icon text-danger"> <i
                                    class="fas fa-sign-out-alt"></i>
                                {{ __('Log Out') }}
                            </a>
                        </div>
                    </li>
                </ul>
            </nav>
            <div class="main-sidebar sidebar-style-2">
                <aside id="sidebar-wrapper">
                    <div class="sidebar-brand mb-4">
                        <a href="{{ route('index') }}"> <span class="logo-name">
                                {{-- {{ __('App Name') }} --}}
                                @if (\App\Helpers\Helpers::get_role())
                                <img src="{{ asset('/storage/uploads/Frame 1116609936 1.jpg') }}" alt="App Logo">
                                @else
                                <img src="{{ asset('/storage/uploads/Group%20514645951.svg') }}" alt="App Logo">
                                @endif
                            </span>
                        </a>
                    </div>
                    <ul class="sidebar-menu">
                        @php
                            $can = fn($key) => \App\Helpers\Helpers::module_permission_check($key);
                            $section = fn($key) => \App\Helpers\Helpers::section_visible($key);
                        @endphp
                        @if (\App\Helpers\Helpers::hasLimitedDoctorAccess())
                        <li class="dropdown sideBarli {{ request()->routeIs('doctors', 'viewBulkUploadDoctors', 'viewBulkUploadDoctorSlots', 'smo.hospitals', 'smo.viewBulkUploadHospitals') ? 'active' : '' }}">
                            <a href="#" class="nav-link has-dropdown"><i class="fas fa-plus-square"></i><span>{{ __('Doctors Management') }}</span></a>
                            <ul class="dropdown-menu">
                                @if ($can('doctor_limited.doctors'))
                                <li class="doctorsSideA {{ request()->routeIs('doctors') ? 'active' : '' }}">
                                    <a href="{{ route('doctors') }}" class="nav-link"><i class="fas fa-plus-square"></i> {{ __('Doctors') }}</a>
                                </li>
                                @endif
                                @if ($can('doctor_limited.bulk_upload_doctors'))
                                <li class="bulkUploadDoctorsSideA {{ request()->routeIs('viewBulkUploadDoctors') ? 'active' : '' }}">
                                    <a href="{{ route('viewBulkUploadDoctors') }}" class="nav-link"><i class="fas fa-bullhorn"></i> {{ __('Bulk Upload of Doctors') }}</a>
                                </li>
                                @endif
                                @if ($can('doctor_limited.bulk_upload_doctor_slots'))
                                <li class="bulkUploadDoctorSlotsSideA {{ request()->routeIs('viewBulkUploadDoctorSlots') ? 'active' : '' }}">
                                    <a href="{{ route('viewBulkUploadDoctorSlots') }}" class="nav-link"><i class="fas fa-bullhorn"></i> {{ __('Bulk Upload Doctors Slots') }}</a>
                                </li>
                                @endif
                                @if ($can('doctor_limited.smo_hospitals'))
                                <li class="HospitalsSideA {{ request()->routeIs('smo.hospitals') ? 'active' : '' }}">
                                    <a href="{{ route('smo.hospitals') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Add Service Providers') }}</a>
                                </li>
                                @endif
                                @if ($can('doctor_limited.bulk_upload_hospitals'))
                                <li class="bulkUploadHospitalsSideA {{ request()->routeIs('smo.viewBulkUploadHospitals') ? 'active' : '' }}">
                                    <a href="{{ route('smo.viewBulkUploadHospitals') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Bulk Upload Hospitals') }}</a>
                        </li>
                                @endif
                            </ul>
                        </li>
                        @else
                            @if (\App\Helpers\Helpers::isUserRole())
                        {{-- Restricted sidebar for "user" role --}}
                        <li class="dropdown sideBarli {{ request()->routeIs('patientAppointment.createRegistration', 'patientAppointment.createAppointment', 'appointments') ? 'active' : '' }}">
                            <a href="#" class="nav-link has-dropdown"><i class="fas fa-calendar-alt"></i><span>{{ __('Patient Appointments & Registration') }}</span></a>
                            <ul class="dropdown-menu">
                                <li class="patientRegistrationSideA {{ request()->routeIs('patientAppointment.createRegistration') ? 'active' : '' }}">
                                    <a href="{{ route('patientAppointment.createRegistration') }}" class="nav-link"><i class="fas fa-cog"></i> {{ __('Patient Registration') }}</a>
                                </li>
                                <li class="patientAppointmentSideA {{ request()->routeIs('patientAppointment.createAppointment') ? 'active' : '' }}">
                                    <a href="{{ route('patientAppointment.createAppointment') }}" class="nav-link"><i class="fas fa-cog"></i> {{ __('Book Appointment') }}</a>
                                </li>
                                <li class="appointmentsSideA {{ request()->routeIs('appointments') ? 'active' : '' }}">
                                    <a href="{{ route('appointments') }}" class="nav-link"><i class="fas fa-calendar-check"></i> {{ __('Appointments') }}</a>
                                </li>
                            </ul>
                        </li>

                        <li class="dropdown sideBarli {{ request()->routeIs('rideragency.*') ? 'active' : '' }}">
                            <a href="#" class="nav-link has-dropdown"><i class="fas fa-plane-departure"></i><span>{{ __('Mulk Travel Cover Management') }}</span></a>
                            <ul class="dropdown-menu">
                                <li class="{{ request()->routeIs('rideragency.agencies') ? 'active' : '' }}">
                                    <a href="{{ route('rideragency.agencies') }}" class="nav-link"><i class="fas fa-address-card"></i> Agencies</a>
                                </li>
                                <li class="dropdown {{ request()->routeIs('rideragency.plan', 'rideragency.allocation.list') ? 'active' : '' }}">
                                    <a href="#" class="nav-link has-dropdown"><span>Rider Allocation</span></a>
                                    <ul class="dropdown-menu">
                                        <li class="{{ request()->routeIs('rideragency.plan') ? 'active' : '' }}">
                                            <a class="nav-link" href="{{ route('rideragency.plan') }}">Plan Allocation</a>
                                        </li>
                                        <li class="{{ request()->routeIs('rideragency.allocation.list') ? 'active' : '' }}">
                                            <a class="nav-link" href="{{ route('rideragency.allocation.list') }}">Allocated Agencies</a>
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </li>
                        @else
                        @if ($section('doctor') && !\App\Helpers\Helpers::hasLimitedDoctorAccess())
                        <li class="dropdown sideBarli menu-doctors {{ request()->routeIs('doctors', 'viewBulkUploadDoctors', 'viewBulkUploadDoctorSlots', 'viewBulkUploadDoctorCategories', 'viewBulkUpdateDoctorMobile') ? 'active' : '' }}">
                            <a href="#" class="nav-link has-dropdown"><i class="fas fa-user-md"></i><span>{{ __('Doctors Management') }}</span></a>
                            <ul class="dropdown-menu">
                                @if ($can('doctor.doctors'))
                                <li class="doctorsSideA {{ request()->routeIs('doctors') ? 'active' : '' }}">
                                    <a href="{{ route('doctors') }}" class="nav-link"><i class="fas fa-plus-square"></i> {{ __('Doctors') }}</a>
                                </li>
                                @endif
                                @if ($can('doctor.bulk_upload_doctors'))
                                <li class="bulkUploadDoctorsSideA {{ request()->routeIs('viewBulkUploadDoctors') ? 'active' : '' }}">
                                    <a href="{{ route('viewBulkUploadDoctors') }}" class="nav-link"><i class="fas fa-bullhorn"></i> {{ __('Bulk Upload of Doctors') }}</a>
                                </li>
                                @endif
                                @if ($can('doctor.bulk_upload_doctor_slots'))
                                <li class="bulkUploadDoctorSlotsSideA {{ request()->routeIs('viewBulkUploadDoctorSlots') ? 'active' : '' }}">
                                    <a href="{{ route('viewBulkUploadDoctorSlots') }}" class="nav-link"><i class="fas fa-bullhorn"></i> {{ __('Bulk Upload Doctors Slots') }}</a>
                                </li>
                                @endif
                                @if ($can('doctor.bulk_upload_specialities'))
                                <li class="bulkUploadDoctorCategoriesSideA {{ request()->routeIs('viewBulkUploadDoctorCategories') ? 'active' : '' }}">
                                    <a href="{{ route('viewBulkUploadDoctorCategories') }}" class="nav-link"><i class="fas fa-bullhorn"></i> {{ __('Bulk Upload Specialities') }}</a>
                                </li>
                                @endif
                                @if ($can('doctor.bulk_upload_doctor_mobile'))
                                <li class="bulkUpdateDoctorMobileSideA {{ request()->routeIs('viewBulkUpdateDoctorMobile') ? 'active' : '' }}">
                                    <a href="{{ route('viewBulkUpdateDoctorMobile') }}" class="nav-link"><i class="fas fa-bullhorn"></i> {{ __('Bulk Upload of Doctors Mobile number') }}</a>
                                </li>
                                @endif
                                @if ($can('doctor.bulk_upload_doctors_speciality'))
                                <li class="bulkUpdateDoctorCategoriesSideA {{ request()->routeIs('viewBulkUpdateDoctorCategories') ? 'active' : '' }}">
                                    <a href="{{ route('viewBulkUpdateDoctorCategories') }}" class="nav-link"><i class="fas fa-bullhorn"></i> {{ __('Bulk Upload Doctors and Speciality') }}</a>
                                </li>
                                @endif
                                @if ($can('doctor.bulk_upload_dha'))
                                <li class="bulkUploadDHARegistrationAndSignatureSideA {{ request()->routeIs('viewBulkUploadDHARegistrationAndSignature') ? 'active' : '' }}">
                                    <a href="{{ route('viewBulkUploadDHARegistrationAndSignature') }}" class="nav-link"><i class="fas fa-bullhorn"></i> {{ __('Bulk Upload of DHA Number and Signature') }}</a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif
                       {{--@endif--}}

                        {{-- @endif --}}
                        @if ($section('main'))
                        <li class="dropdown sideBarli menu-dashboard {{ request()->routeIs('index', 'users', 'doctors', 'reviews', 'coupons', 'reels', 'reports', 'faqs', 'notifications') ? 'active' : '' }}">
                            <a href="#" class="nav-link has-dropdown"><i class="fas fa-tachometer-alt"></i><span>{{ __('Main') }}</span></a>
                            <ul class="dropdown-menu">
                                @if ($can('main.dashboard'))
                                <li class="indexSideA {{ request()->routeIs('index') ? 'active' : '' }}">
                                    <a href="{{ route('index') }}" class="nav-link"><i class="fas fa-tachometer-alt"></i> {{ __('Dashboard') }}</a>
                                </li>
                                @endif
                                @if ($can('main.users'))
                                <li class="usersSideA {{ request()->routeIs('users') ? 'active' : '' }}">
                                    <a href="{{ route('users') }}" class="nav-link"><i class="fa fa-users"></i> {{ __('Users') }}</a>
                                </li>
                                @endif
                                @if ($can('main.doctors'))
                                <li class="doctorsSideA {{ request()->routeIs('doctors') ? 'active' : '' }}">
                                    <a href="{{ route('doctors') }}" class="nav-link"><i class="fas fa-plus-square"></i> {{ __('Doctors') }}</a>
                                </li>
                                @endif
                                @if ($can('main.reviews'))
                                <li class="reviewsSideA {{ request()->routeIs('reviews') ? 'active' : '' }}">
                                    <a href="{{ route('reviews') }}" class="nav-link"><i class="fas fa-star"></i> {{ __('Reviews') }}</a>
                                </li>
                                @endif
                                @if ($can('main.coupons'))
                                <li class="couponsSideA {{ request()->routeIs('coupons') ? 'active' : '' }}">
                                    <a href="{{ route('coupons') }}" class="nav-link"><i class="fas fa-tag"></i> {{ __('Coupons') }}</a>
                                </li>
                                @endif
                                @if ($can('main.reels'))
                                <li class="reelsSideA {{ request()->routeIs('reels') ? 'active' : '' }}">
                                    <a href="{{ route('reels') }}" class="nav-link"><i class="fas fa-play"></i> {{ __('Reels') }}</a>
                                </li>
                                @endif
                                @if ($can('main.reports'))
                                <li class="reportsSideA {{ request()->routeIs('reports') ? 'active' : '' }}">
                                    <a href="{{ route('reports') }}" class="nav-link"><i class="fas fa-info-circle"></i> {{ __('Reel Reports') }}</a>
                                </li>
                                @endif
                                @if ($can('main.faqs'))
                                <li class="faqsSideA {{ request()->routeIs('faqs') ? 'active' : '' }}">
                                    <a href="{{ route('faqs') }}" class="nav-link"><i class="fas fa-question-circle"></i> {{ __('FAQs') }}</a>
                                </li>
                                @endif
                                @if ($can('main.notifications'))
                                <li class="notificationsSideA {{ request()->routeIs('notifications') ? 'active' : '' }}">
                                    <a href="{{ route('notifications') }}" class="nav-link"><i class="fa fa-bell"></i> {{ __('Notifications') }}</a>
                                </li>
                                @endif
                            </ul>
                        </li>

                        @endif

                        @if ($section('home_page'))
                        <li class="dropdown sideBarli menu-home {{ request()->routeIs('dashboardBanners', 'partnerNetwork', 'doctorCategories', 'doctorsBySymptoms') ? 'active' : '' }}">
                            <a href="#" class="nav-link has-dropdown"><i class="fas fa-desktop"></i><span>{{ __('Home Page') }}</span></a>
                            <ul class="dropdown-menu">
                                @if ($can('home_page.dashboard_banners'))
                                <li class="dashboardBannersSideA {{ request()->routeIs('dashboardBanners') ? 'active' : '' }}">
                                    <a href="{{ route('dashboardBanners') }}" class="nav-link"><i class="fas fa-bullhorn"></i> {{ __('Dashboard Banners') }}</a>
                                </li>
                                @endif
                                @if ($can('home_page.partner_network'))
                                <li class="PartnersNetworkSideA {{ request()->routeIs('partnerNetwork') ? 'active' : '' }}">
                                    <a href="{{ route('partnerNetwork') }}" class="nav-link"><i class="fas fa-network-wired"></i> {{ __('Partners Network') }}</a>
                                </li>
                                @endif
                                @if ($can('home_page.doctor_speciality'))
                                <li class="doctorCategoriesSideA {{ request()->routeIs('doctorCategories') ? 'active' : '' }}">
                                    <a href="{{ route('doctorCategories') }}" class="nav-link"><i class="fas fa-grip-horizontal"></i> {{ __('Doctor By Speciality') }}</a>
                                </li>
                                @endif
                                @if ($can('home_page.doctors_by_symptoms'))
                                <li class="doctorsBySymptomsSideA {{ request()->routeIs('doctorsBySymptoms') ? 'active' : '' }}">
                                    <a href="{{ route('doctorsBySymptoms') }}" class="nav-link"><i class="fas fa-grip-horizontal"></i> {{ __('Doctors By Symptoms') }}</a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif

                        {{-- @if (\App\Helpers\Helpers::module_permission_check('other_data')) --}}
                        @if ($section('patient_appointment'))
                        <li class="dropdown sideBarli menu-patient {{ request()->routeIs('patientAppointment.createRegistration', 'patientAppointment.createAppointment', 'appointments') ? 'active' : '' }}">
                            <a href="#" class="nav-link has-dropdown"><i class="fas fa-book-medical"></i><span>{{ __('Patient Appointment') }}</span></a>
                            <ul class="dropdown-menu">
                                @if ($can('patient_appointment.registration'))
                                <li class="patientRegistrationSideA {{ request()->routeIs('patientAppointment.createRegistration') ? 'active' : '' }}">
                                    <a href="{{ route('patientAppointment.createRegistration') }}" class="nav-link"><i class="fas fa-cog"></i> {{ __('Patient Registration') }}</a>
                                </li>
                                @endif
                                @if ($can('patient_appointment.book_appointment'))
                                <li class="patientAppointmentSideA {{ request()->routeIs('patientAppointment.createAppointment') ? 'active' : '' }}">
                                    <a href="{{ route('patientAppointment.createAppointment') }}" class="nav-link"><i class="fas fa-cog"></i> {{ __('Book Appointment') }}</a>
                                </li>
                                @endif
                                @if ($can('patient_appointment.appointments'))
                                <li class="appointmentsSideA {{ request()->routeIs('appointments') ? 'active' : '' }}">
                                    <a href="{{ route('appointments') }}" class="nav-link"><i class="fas fa-calendar-check"></i> {{ __('Appointments') }}</a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif

                        {{-- @endif --}}

                        {{-- @if (\App\Helpers\Helpers::module_permission_check('SMO')) --}}
                        @if ($section('smo'))
                        <li class="dropdown sideBarli menu-smo {{ request()->routeIs('smo.*', 'lowestprice', 'viewBulkUpdateDoctorCategories') ? 'active' : '' }}">
                            <a href="#" class="nav-link has-dropdown"><i class="fas fa-hand-holding-heart"></i><span>{{ __('World Best Treatment Finder') }}</span></a>
                            <ul class="dropdown-menu">
                                @if ($can('smo.top_hospitals'))
                                <li class="TopHospitalsSideA {{ request()->routeIs('smo.topHospitals') ? 'active' : '' }}">
                                    <a href="{{ route('smo.topHospitals') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Top Hospitals') }}</a>
                                </li>
                                @endif
                                @if ($can('smo.hospitals'))
                                <li class="HospitalsSideA {{ request()->routeIs('smo.hospitals') ? 'active' : '' }}">
                                    <a href="{{ route('smo.hospitals') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Add Service Providers') }}</a>
                                </li>
                                @endif
                                @if ($can('smo.why_second_opinion'))
                                <li class="WhySecondOpinionMattersSideA {{ request()->routeIs('smo.whySecondOpinionMatters') ? 'active' : '' }}">
                                    <a href="{{ route('smo.whySecondOpinionMatters') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Why Second Opinion Matters') }}</a>
                                </li>
                                @endif
                                @if ($can('smo.trusted_healthcare'))
                                <li class="TrustedHealthcareProvidersSideA {{ request()->routeIs('smo.trustedHealthcarePartners') ? 'active' : '' }}">
                                    <a href="{{ route('smo.trustedHealthcarePartners') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Explore Our Trusted Healthcare Providers') }}</a>
                                </li>
                                @endif
                                @if ($can('smo.hospital_categories'))
                                <li class="HospitalCategoriesSideA {{ request()->routeIs('smo.hospitalCategories') ? 'active' : '' }}">
                                    <a href="{{ route('smo.hospitalCategories') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Add Category') }}</a>
                                </li>
                                @endif
                                @if ($can('smo.hospital_procedures'))
                                <li class="HospitalProceduresSideA {{ request()->routeIs('smo.hospitalProcedures') ? 'active' : '' }}">
                                    <a href="{{ route('smo.hospitalProcedures') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Hospital Procedures') }}</a>
                                </li>
                                @endif
                                @if ($can('smo.top_procedures'))
                                <li class="TopProceduresSideA {{ request()->routeIs('smo.topProcedures') ? 'active' : '' }}">
                                    <a href="{{ route('smo.topProcedures') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Top Procedures') }}</a>
                                </li>
                                @endif
                                @if ($can('smo.mulkmed_choice_doctors'))
                                <li class="MulkmedChoiceOfDoctorsSideA {{ request()->routeIs('smo.mulkmedChoiceOfDoctors') ? 'active' : '' }}">
                                    <a href="{{ route('smo.mulkmedChoiceOfDoctors') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Mulkmed Choice Of Doctors') }}</a>
                                </li>
                                @endif
                                @if ($can('smo.unlock_benefits_card'))
                                <li class="UnlockMoreBenefitsCardSideA {{ request()->routeIs('smo.unlockMoreBenefitsCard') ? 'active' : '' }}">
                                    <a href="{{ route('smo.unlockMoreBenefitsCard') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Unlock More Benefits Card Banner') }}</a>
                                </li>
                                @endif
                                @if ($can('smo.submit_query_banner'))
                                <li class="SubmitYourQueryideA {{ request()->routeIs('smo.submitYourQuery') ? 'active' : '' }}">
                                    <a href="{{ route('smo.submitYourQuery') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Submit Your Query Banner') }}</a>
                                </li>
                                @endif
                                @if ($can('smo.submitted_queries'))
                                <li class="SMOQueryA {{ request()->routeIs('smo.SMOQueries') ? 'active' : '' }}">
                                    <a href="{{ route('smo.SMOQueries') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Submitted SMO Queries') }}</a>
                                </li>
                                @endif
                                @if ($can('smo.query_procedures'))
                                <li class="QueryProceduresSideA {{ request()->routeIs('smo.queryProcedures') ? 'active' : '' }}">
                                    <a href="{{ route('smo.queryProcedures') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Query Procedures') }}</a>
                                </li>
                                @endif
                                @if ($can('smo.bulk_upload_hospitals'))
                                <li class="bulkUploadHospitalsSideA {{ request()->routeIs('smo.viewBulkUploadHospitals') ? 'active' : '' }}">
                                    <a href="{{ route('smo.viewBulkUploadHospitals') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Bulk Upload Service Providers') }}</a>
                                </li>
                                @endif
                                @if ($can('smo.bulk_upload_hospital_procedures'))
                                <li class="bulkUploadHospitalProceduresSideA {{ request()->routeIs('smo.viewBulkUploadHospitalProcedures') ? 'active' : '' }}">
                                    <a href="{{ route('smo.viewBulkUploadHospitalProcedures') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Bulk Upload of Hospital Procedures') }}</a>
                                </li>
                                @endif
                                @if ($can('smo.bulk_upload_query_procedures'))
                                <li class="bulkUploadQueryProceduresSideA {{ request()->routeIs('smo.viewBulkUploadQueryProcedures') ? 'active' : '' }}">
                                    <a href="{{ route('smo.viewBulkUploadQueryProcedures') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Bulk Upload of Query Procedures') }}</a>
                                </li>
                                @endif
                                @if ($can('smo.bulk_upload_doctors_speciality'))
                                <li class="bulkUpdateDoctorCategoriesSideA {{ request()->routeIs('viewBulkUpdateDoctorCategories') ? 'active' : '' }}">
                                    <a href="{{ route('viewBulkUpdateDoctorCategories') }}" class="nav-link"><i class="fas fa-bullhorn"></i> {{ __('Bulk Upload Doctors and Speciality') }}</a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif

                        {{-- @if (\App\Helpers\Helpers::module_permission_check('bulkUpdateHospitalProcedures'))
                                    <li class="sideBarli  bulkUpdateHospitalProceduresSideA">
                                        <a href="{{ route('viewBulkUpdateHospitalProcedures') }}" class="nav-link"><i class="fas fa-bullhorn"></i><span>
                            {{ __('Bulk Update Hospital Procedures') }} </span></a>
                        </li>
                        @endif --}}
                        {{-- @endif --}}

                        @if ($section('Tourist Management'))

                        <li class="dropdown sideBarli menu-tourist {{ request()->routeIs('travelFlowBanner', 'touristList', 'touristAppointments', 'rideragency.*') ? 'active' : '' }}">
                            <a href="#" class="nav-link has-dropdown"><i class="fas fa-plane-departure"></i><span>{{ __('Mulk Travel Cover Management') }}</span></a>
                            <ul class="dropdown-menu">
                                @if ($can('Tourist Management.travel_banner'))
                                <li class="TravelFlowBannerSideA {{ request()->routeIs('travelFlowBanner') ? 'active' : '' }}">
                                    <a href="{{ route('travelFlowBanner') }}" class="nav-link"><i class="fas fa-image"></i> Tourist banner</a>
                                </li>
                                @endif
                                @if ($can('Tourist Management.travelers_list'))
                                <li class="TouristListSideA {{ request()->routeIs('touristList') ? 'active' : '' }}">
                                    <a href="{{ route('touristList') }}" class="nav-link"><i class="fas fa-users"></i> Travelers List</a>
                                </li>
                                @endif
                                @if ($can('Tourist Management.home'))
                                <li class="{{ request()->routeIs('rideragency.dashboard') ? 'active' : '' }}">
                                    <a href="{{ route('rideragency.dashboard') }}" class="nav-link"><i class="fas fa-home"></i> Home</a>
                                </li>
                                @endif
                                @if ($can('Tourist Management.travelers_appointments'))
                                <li class="touristAppointmentsSideA {{ request()->routeIs('touristAppointments') ? 'active' : '' }}">
                                    <a href="{{ route('touristAppointments') }}" class="nav-link"><i class="fas fa-calendar-check"></i> {{ __('Travelers Appointments') }}</a>
                                </li>
                                @endif
                                @if ($can('Tourist Management.agencies'))
                                <li class="{{ request()->routeIs('rideragency.agencies') ? 'active' : '' }}">
                                    <a href="{{ route('rideragency.agencies') }}" class="nav-link"><i class="fas fa-address-card"></i> Agencies</a>
                                </li>
                                @endif
                                @if ($can('Tourist Management.product_plan'))
                                <li class="{{ request()->routeIs('rideragency.product.plan') ? 'active' : '' }}">
                                    <a href="{{ route('rideragency.product.plan') }}" class="nav-link"><i class="fas fa-box"></i> My Product Plan</a>
                                </li>
                                @endif
                                @if ($can('Tourist Management.plan_allocation') || $can('Tourist Management.allocated_agencies'))
                                <li class="dropdown {{ request()->routeIs('rideragency.plan', 'rideragency.allocation.list') ? 'active' : '' }}">
                                    <a href="#" class="nav-link has-dropdown"> Rider Allocation</a>
                                    <ul class="dropdown-menu">
                                        @if ($can('Tourist Management.plan_allocation'))
                                        <li class="{{ request()->routeIs('rideragency.plan') ? 'active' : '' }}">
                                            <a class="nav-link" href="{{ route('rideragency.plan') }}">Plan Allocation</a>
                                        </li>
                                        @endif
                                        @if ($can('Tourist Management.allocated_agencies'))
                                        <li class="{{ request()->routeIs('rideragency.allocation.list') ? 'active' : '' }}">
                                            <a class="nav-link" href="{{ route('rideragency.allocation.list') }}">Allocated Agencies</a>
                                        </li>
                                        @endif
                                    </ul>
                                </li>
                                @endif
                                @if ($can('Tourist Management.upload_history'))
                                <li class="{{ request()->routeIs('rideragency.upload-history') ? 'active' : '' }}">
                                    <a href="{{ route('rideragency.upload-history') }}" class="nav-link"><i class="fas fa-history"></i> Excel Upload History</a>
                                </li>
                                @endif
                                @if ($can('Tourist Management.transaction_summary'))
                                <li class="{{ request()->routeIs('rideragency.transaction.summary') ? 'active' : '' }}">
                                    <a href="{{ route('rideragency.transaction.summary') }}" class="nav-link"><i class="fas fa-exchange-alt"></i> Transaction Summary</a>
                                </li>
                                @endif
                            </ul>
                        </li>

                        @endif
                        {{-- Keep other sections inside the same list --}}

                        {{-- @if (\App\Helpers\Helpers::module_permission_check('SMO')) --}}
                        @if ($section('bidding'))
                        <li class="dropdown sideBarli menu-bidding {{ request()->routeIs('bidding.*') ? 'active' : '' }}">
                            <a href="#" class="nav-link has-dropdown"><i class="fas fa-gavel"></i><span>{{ __('Lowest Price Finder (Bidding)') }}</span></a>
                            <ul class="dropdown-menu">
                                @if ($can('bidding.submitted_bid'))
                                <li class="SubmittedBidSideA {{ request()->routeIs('bidding.bidSubmitted') ? 'active' : '' }}">
                                    <a href="{{ route('bidding.bidSubmitted') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Submitted Bid') }}</a>
                                </li>
                                @endif
                                @if ($can('bidding.bidding_services'))
                                <li class="BiddingDataSideA {{ request()->routeIs('bidding.biddingServices') ? 'active' : '' }}">
                                    <a href="{{ route('bidding.biddingServices') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Bidding Services') }}</a>
                                </li>
                                @endif
                                @if ($can('bidding.bidding_banner'))
                                <li class="BidSubmitBannerSideA {{ request()->routeIs('bidding.biddingSubmitBanners') ? 'active' : '' }}">
                                    <a href="{{ route('bidding.biddingSubmitBanners') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Bidding Bottom Banner') }}</a>
                                </li>
                                @endif
                                @if ($can('bidding.bulk_upload_services'))
                                <li class="BulkUploadBiddingServicesSideA {{ request()->routeIs('bidding.viewBulkUploadBiddingServices') ? 'active' : '' }}">
                                    <a href="{{ route('bidding.viewBulkUploadBiddingServices') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Bulk Upload of Bidding services') }}</a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif

                        {{-- @endif --}}

                        @if ($section('best_offers'))
                        <li class="dropdown sideBarli menu-best-offers {{ request()->routeIs('bestOffers.*') ? 'active' : '' }}">
                            <a href="#" class="nav-link has-dropdown"><i class="fas fa-percentage"></i><span>{{ __('Best Offers') }}</span></a>
                            <ul class="dropdown-menu">
                                @if ($can('best_offers.plans'))
                                <li class="BestOffersPlansSideA {{ request()->routeIs('bestOffers.viewBestOffersPlans') ? 'active' : '' }}">
                                    <a href="{{ route('bestOffers.viewBestOffersPlans') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Best Offers Plans') }}</a>
                                </li>
                                @endif
                                @if ($can('best_offers.plan_users'))
                                <li class="BestOffersPlanUsersSideA {{ request()->routeIs('bestOffers.viewBestOffersPlanUsers') ? 'active' : '' }}">
                                    <a href="{{ route('bestOffers.viewBestOffersPlanUsers') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Plan Purchased By Users') }}</a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif

                        {{-- @if (\App\Helpers\Helpers::module_permission_check('SMO')) --}}
                        @if ($section('mulk_cards'))
                        <li class="dropdown sideBarli menu-mulk-cards {{ request()->routeIs('HNH.*', 'senior.*', 'tourist.*') ? 'active' : '' }}">
                            <a href="#" class="nav-link has-dropdown"><i class="fas fa-address-card"></i><span>{{ __('Mulk Cards') }}</span></a>
                            <ul class="dropdown-menu">
                                @if ($can('mulk_cards.hnh_card'))
                                <li class="HNHCardsSideA {{ request()->routeIs('HNH.HnHCards') ? 'active' : '' }}">
                                    <a href="{{ route('HNH.HnHCards') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Mulk HnH Card') }}</a>
                                </li>
                                @endif
                                @if ($can('mulk_cards.senior_card'))
                                <li class="SeniorCardsSideA {{ request()->routeIs('senior.seniorCards') ? 'active' : '' }}">
                                    <a href="{{ route('senior.seniorCards') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Mulk Senior Card') }}</a>
                                </li>
                                @endif
                                @if ($can('mulk_cards.tourist_card'))
                                <li class="TouristCardsSideA {{ request()->routeIs('tourist.touristCards') ? 'active' : '' }}">
                                    <a href="{{ route('tourist.touristCards') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Mulk Tourist Gold Card') }}</a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif


                        {{-- @endif --}}

                        @if ($section('bulk_upload_smo'))
                        <li class="dropdown sideBarli menu-bulk-upload-smo {{ request()->routeIs('smo.viewBulkUploadTopHospital', 'smo.viewBulkUploadWhySecondOpinionMatters', 'smo.viewBulkUploadTrustedHealthcarePartners', 'smo.viewBulkUploadHospitalCategories', 'smo.viewBulkUploadTopProcedures', 'viewBulkUploadDHARegistrationAndSignature') ? 'active' : '' }}">
                            <a href="#" class="nav-link has-dropdown"><i class="fas fa-file-medical"></i><span>{{ __('Bulk Upload of Service Providers and Procedures (New Heading)') }}</span></a>
                            <ul class="dropdown-menu">
                                @if ($can('bulk_upload_smo.top_hospitals'))
                                <li class="TopHospitalsBulkUploadSideA {{ request()->routeIs('smo.viewBulkUploadTopHospital') ? 'active' : '' }}">
                                    <a href="{{ route('smo.viewBulkUploadTopHospital') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Bulk Upload of Top Hospitals') }}</a>
                                </li>
                                @endif
                                @if ($can('bulk_upload_smo.why_second_opinion'))
                                <li class="WhySecondOpinionMattersBulkUploadSideA {{ request()->routeIs('smo.viewBulkUploadWhySecondOpinionMatters') ? 'active' : '' }}">
                                    <a href="{{ route('smo.viewBulkUploadWhySecondOpinionMatters') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Why Second Opinion Matters Banner') }}</a>
                                </li>
                                @endif
                                @if ($can('bulk_upload_smo.trusted_partners'))
                                <li class="TrustedHealthcarePartnersBulkUploadSideA {{ request()->routeIs('smo.viewBulkUploadTrustedHealthcarePartners') ? 'active' : '' }}">
                                    <a href="{{ route('smo.viewBulkUploadTrustedHealthcarePartners') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Trusted Healthcare Partners Bulk Upload') }}</a>
                                </li>
                                @endif
                                @if ($can('bulk_upload_smo.categories'))
                                <li class="HospitalCategoriesBulkUploadSideA {{ request()->routeIs('smo.viewBulkUploadHospitalCategories') ? 'active' : '' }}">
                                    <a href="{{ route('smo.viewBulkUploadHospitalCategories') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Bulk Upload of Categories') }}</a>
                                </li>
                                @endif
                                @if ($can('bulk_upload_smo.top_procedures'))
                                <li class="TopProceduresBulkUploadSideA {{ request()->routeIs('smo.viewBulkUploadTopProcedures') ? 'active' : '' }}">
                                    <a href="{{ route('smo.viewBulkUploadTopProcedures') }}" class="nav-link"><i class="fas fa-hospital"></i> {{ __('Bulk Upload of Top Procedures') }}</a>
                                </li>
                                @endif
                                @if ($can('bulk_upload_smo.dha_signature'))
                                <li class="bulkUploadDHARegistrationAndSignatureSideA {{ request()->routeIs('viewBulkUploadDHARegistrationAndSignature') ? 'active' : '' }}">
                                    <a href="{{ route('viewBulkUploadDHARegistrationAndSignature') }}" class="nav-link"><i class="fas fa-bullhorn"></i> {{ __('Bulk Upload of DHA Number and Signature') }}</a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif



                        @if ($section('business'))
                        <li class="dropdown sideBarli menu-business {{ request()->routeIs('userWithdraws', 'doctorWithdraws', 'platformEarnings', 'bookingAndPayment', 'userWalletRecharge') ? 'active' : '' }}">
                            <a href="#" class="nav-link has-dropdown"><i class="fas fa-briefcase"></i><span>{{ __('Business') }}</span></a>
                            <ul class="dropdown-menu">
                                @if ($can('business.user_withdraws'))
                                <li class="userWithdrawsSideA {{ request()->routeIs('userWithdraws') ? 'active' : '' }}">
                                    <a href="{{ route('userWithdraws') }}" class="nav-link"><i class="fas fa-money-bill"></i> {{ __('User Withdraws') }}</a>
                                </li>
                                @endif
                                @if ($can('business.doctor_withdraws'))
                                <li class="doctorWithdrawsSideA {{ request()->routeIs('doctorWithdraws') ? 'active' : '' }}">
                                    <a href="{{ route('doctorWithdraws') }}" class="nav-link"><i class="fas fa-money-bill"></i> {{ __('Doctor Withdraws') }}</a>
                                </li>
                                @endif
                                @if ($can('business.platform_earnings'))
                                <li class="platformEarningsSideA {{ request()->routeIs('platformEarnings') ? 'active' : '' }}">
                                    <a href="{{ route('platformEarnings') }}" class="nav-link"><i class="fas fa-percentage"></i> {{ __('Platform Earnings') }}</a>
                                </li>
                                @endif
                                @if ($can('business.booking_payment'))
                                <li class="BookingAndPaymentSideA {{ request()->routeIs('bookingAndPayment') ? 'active' : '' }}">
                                    <a href="{{ route('bookingAndPayment') }}" class="nav-link"><i class="fas fa-calendar-check"></i> {{ __('Booking & Payment') }}</a>
                                </li>
                                @endif
                                @if ($can('business.wallet_recharge'))
                                <li class="userWalletRechargeSideA {{ request()->routeIs('userWalletRecharge') ? 'active' : '' }}">
                                    <a href="{{ route('userWalletRecharge') }}" class="nav-link"><i class="fas fa-wallet"></i> {{ __('Recharge Logs (User)') }}</a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif

                        {{-- @if (\App\Helpers\Helpers::get_role()) --}}
                        <!-- <li class="menu-header">{{ __('Online Consultation') }}</li>

                        <li class="sideBarli  commonHealthProblemsSideA">
                            <a href="{{ route('commonHealthProblems') }}" class="nav-link"><i
                                    class="fas fa-thermometer-half"></i><span>
                                    {{ __('Common Health Problems') }} </span></a>
                        </li>
                        <li class="sideBarli  bannersSideA">
                            <a href="{{ route('banners') }}" class="nav-link"><i
                                    class="fas fa-bullhorn"></i><span>
                                    {{ __('Banners') }} </span></a>
                        </li> -->
                        {{-- @endif --}}
                        @if ($section('mulk_longevity'))
                        <li class="dropdown sideBarli menu-mulk-longevity {{ request()->routeIs('majorOrganTests.index', 'longevityPlans.index') ? 'active' : '' }}">
                            <a href="#" class="nav-link has-dropdown"><i class="fas fa-heartbeat"></i><span>{{ __('Mulk Longevity') }}</span></a>
                            <ul class="dropdown-menu">
                                @if ($can('mulk_longevity.major_organ_tests'))
                                <li class="majorOrganTestsSideA {{ request()->routeIs('majorOrganTests.index') ? 'active' : '' }}">
                                    <a href="{{ route('majorOrganTests.index') }}" class="nav-link"><i class="fas fa-heartbeat"></i> {{ __('Major Organ Tests') }}</a>
                                </li>
                                @endif
                                @if ($can('mulk_longevity.longevity_plans'))
                                <li class="longevityPlansSideA {{ request()->routeIs('longevityPlans.index') ? 'active' : '' }}">
                                    <a href="{{ route('longevityPlans.index') }}" class="nav-link"><i class="fas fa-spa"></i> {{ __('Longevity Plans') }}</a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif

                        @if ($section('online_consultation'))
                        <li class="dropdown sideBarli menu-online-consultation {{ request()->routeIs('commonHealthProblems', 'SpecialityWiseDisease', 'doctorPlans', 'banners') ? 'active' : '' }}">
                            <a href="#" class="nav-link has-dropdown"><i class="fas fa-stethoscope"></i><span>{{ __('Online Consultation') }}</span></a>
                            <ul class="dropdown-menu">
                                @if ($can('online_consultation.common_health'))
                                <li class="commonHealthProblemsSideA {{ request()->routeIs('commonHealthProblems') ? 'active' : '' }}">
                                    <a href="{{ route('commonHealthProblems') }}" class="nav-link"><i class="fas fa-thermometer-half"></i> {{ __('Common Health Problems') }}</a>
                                </li>
                                @endif
                                @if ($can('online_consultation.speciality_disease'))
                                <li class="specialityWiseDiseasesSideA {{ request()->routeIs('SpecialityWiseDisease') ? 'active' : '' }}">
                                    <a href="{{ route('SpecialityWiseDisease') }}" class="nav-link"><i class="fas fa-microscope"></i> {{ __('Speciality Wise Disease') }}</a>
                                </li>
                                @endif
                                @if ($can('online_consultation.doctor_plans'))
                                <li class="doctorPlansSideA {{ request()->routeIs('doctorPlans') ? 'active' : '' }}">
                                    <a href="{{ route('doctorPlans') }}" class="nav-link"><i class="fas fa-notes-medical"></i> {{ __('Doctors Plan') }}</a>
                                </li>
                                @endif
                                @if ($can('online_consultation.banners'))
                                <li class="bannersSideA {{ request()->routeIs('banners') ? 'active' : '' }}">
                                    <a href="{{ route('banners') }}" class="nav-link"><i class="fas fa-bullhorn"></i> {{ __('Banners') }}</a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif

                        {{-- @if (\App\Helpers\Helpers::module_permission_check('online_consultation')) --}}
                        @if ($section('order_medicine'))
                        <li class="dropdown sideBarli menu-order-medicine {{ request()->routeIs('orderMedicineCategories') ? 'active' : '' }}">
                            <a href="#" class="nav-link has-dropdown"><i class="fas fa-capsules"></i><span>{{ __('Order Medicine') }}</span></a>
                            <ul class="dropdown-menu">
                                @if ($can('order_medicine.categories'))
                                <li class="orderMedicineCategoriesSideA {{ request()->routeIs('orderMedicineCategories') ? 'active' : '' }}">
                                    <a href="{{ route('orderMedicineCategories') }}" class="nav-link"><i class="fas fa-book"></i> {{ __('Categories') }}</a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif




                        {{-- @endif --}}

                        @if ($section('other_data'))
                        <li class="dropdown sideBarli menu-other-data {{ request()->routeIs('doctorCategories', 'settings', 'adminManagement', 'adminRole.create', 'adminRole.edit', 'sectionSequence', 'viewBulkUploadDoctors', 'viewBulkUploadDoctorSlots', 'viewBulkUploadDoctorCategories', 'testClassification', 'majorOrganTests.index') || (!\App\Helpers\Helpers::get_role() && request()->routeIs('emrMasterData')) ? 'active' : '' }}">
                            <a href="#" class="nav-link has-dropdown"><i class="fas fa-database"></i><span>{{ __('Other Data') }}</span></a>
                            <ul class="dropdown-menu">
                                @if ($can('other_data.doctor_speciality'))
                                <li class="doctorCategoriesSideA {{ request()->routeIs('doctorCategories') ? 'active' : '' }}">
                                    <a href="{{ route('doctorCategories') }}" class="nav-link"><i class="fas fa-grip-horizontal"></i> {{ __('Doctors By Speciality') }}</a>
                                </li>
                                @endif
                                @if ($can('other_data.settings'))
                                <li class="settingsSideA {{ request()->routeIs('settings') ? 'active' : '' }}">
                                    <a href="{{ route('settings') }}" class="nav-link"><i class="fas fa-cog"></i> {{ __('Settings') }}</a>
                                </li>
                                @endif
                                @if (!\App\Helpers\Helpers::get_role() && $can('other_data.admin_management'))
                                <li class="adminManagementSideA {{ request()->routeIs('adminManagement', 'adminRole.create', 'adminRole.edit') ? 'active' : '' }}">
                                    <a href="{{ route('adminManagement') }}" class="nav-link"><i class="fas fa-user-shield"></i> {{ __('Admin Management') }}</a>
                                </li>
                                @endif
                                @if ($can('other_data.emr_master_data'))
                                <li class="emrMasterDataSideA {{ request()->routeIs('emrMasterData') ? 'active' : '' }}">
                                    <a href="{{ route('emrMasterData') }}" class="nav-link"><i class="fas fa-notes-medical"></i> {{ __('EMR Master Data') }}</a>
                                </li>
                                @endif
                                @if ($can('other_data.test_classification'))
                                <li class="testClassificationSideA {{ request()->routeIs('testClassification') ? 'active' : '' }}">
                                    <a href="{{ route('testClassification') }}" class="nav-link"><i class="fas fa-brain"></i> {{ __('Test Classification') }}</a>
                                </li>
                                @endif
                                @if ($can('other_data.homepage_sections'))
                                <li class="homepageSectionsSideA {{ request()->routeIs('sectionSequence') ? 'active' : '' }}">
                                    <a href="{{ route('sectionSequence') }}" class="nav-link"><i class="fas fa-bullhorn"></i> {{ __('Homepage Sections') }}</a>
                                </li>
                                @endif
                                @if ($can('other_data.bulk_upload_doctors'))
                                <li class="bulkUploadDoctorsSideA {{ request()->routeIs('viewBulkUploadDoctors') ? 'active' : '' }}">
                                    <a href="{{ route('viewBulkUploadDoctors') }}" class="nav-link"><i class="fas fa-bullhorn"></i> {{ __('Bulk Upload of Doctors') }}</a>
                                </li>
                                @endif
                                @if ($can('other_data.bulk_upload_doctor_slots'))
                                <li class="bulkUploadDoctorSlotsSideA {{ request()->routeIs('viewBulkUploadDoctorSlots') ? 'active' : '' }}">
                                    <a href="{{ route('viewBulkUploadDoctorSlots') }}" class="nav-link"><i class="fas fa-bullhorn"></i> {{ __('Bulk Upload Doctors Slots') }}</a>
                                </li>
                                @endif
                                @if ($can('other_data.bulk_upload_specialities'))
                                <li class="bulkUploadDoctorCategoriesSideA {{ request()->routeIs('viewBulkUploadDoctorCategories') ? 'active' : '' }}">
                                    <a href="{{ route('viewBulkUploadDoctorCategories') }}" class="nav-link"><i class="fas fa-bullhorn"></i> {{ __('Bulk Upload Specialities') }}</a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif

                        @if ($section('pages'))
                        <li class="dropdown sideBarli menu-pages {{ request()->routeIs('viewPrivacy', 'viewTerms', 'viewHelpCenter', 'viewMidasDescription', 'viewHealthcheckDescription') ? 'active' : '' }}">
                            <a href="#" class="nav-link has-dropdown"><i class="fas fa-copy"></i><span>{{ __('Pages') }}</span></a>
                            <ul class="dropdown-menu">
                                @if ($can('pages.privacy'))
                                <li class="privacySideA {{ request()->routeIs('viewPrivacy') ? 'active' : '' }}">
                                    <a href="{{ route('viewPrivacy') }}" class="nav-link"><i class="fas fa-info"></i> {{ __('Privacy Policy') }}</a>
                                </li>
                                @endif
                                @if ($can('pages.terms'))
                                <li class="termsSideA {{ request()->routeIs('viewTerms') ? 'active' : '' }}">
                                    <a href="{{ route('viewTerms') }}" class="nav-link"><i class="fas fa-info"></i> {{ __('Terms Of Use') }}</a>
                                </li>
                                @endif
                                @if ($can('pages.help_center'))
                                <li class="helpCenterSideA {{ request()->routeIs('viewHelpCenter') ? 'active' : '' }}">
                                    <a href="{{ route('viewHelpCenter') }}" class="nav-link"><i class="fas fa-info"></i> {{ __('Help Center') }}</a>
                                </li>
                                @endif
                                @if ($can('pages.midas_description'))
                                <li class="MidasDescriptionSideA {{ request()->routeIs('viewMidasDescription') ? 'active' : '' }}">
                                    <a href="{{ route('viewMidasDescription') }}" class="nav-link"><i class="fas fa-info"></i> {{ __('MIDAS Description') }}</a>
                                </li>
                                @endif
                                @if ($can('pages.healthcheck_description'))
                                <li class="HealthcheckDescriptionSideA {{ request()->routeIs('viewHealthcheckDescription') ? 'active' : '' }}">
                                    <a href="{{ route('viewHealthcheckDescription') }}" class="nav-link"><i class="fas fa-info"></i> {{ __('Mulk AI Healthcheck Description') }}</a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif
                        @if (\App\Helpers\Helpers::get_role() && $can('other_data.emr_master_data'))
                        <li class="sideBarli emrMasterDataStandaloneSideA {{ request()->routeIs('emrMasterData') ? 'active' : '' }}">
                            <a href="{{ route('emrMasterData') }}" class="nav-link"><i class="fas fa-notes-medical"></i><span>{{ __('EMR Master Data') }}</span></a>
                        </li>
                        @endif
                        @if (\App\Helpers\Helpers::get_role() && $can('other_data.test_classification'))
                        <li class="sideBarli testClassificationSideA {{ request()->routeIs('testClassification') ? 'active' : '' }}">
                            <a href="{{ route('testClassification') }}" class="nav-link"><i class="fas fa-brain"></i><span>{{ __('Test Classification') }}</span></a>
                        </li>
                        @endif
                        @endif
                        @endif
                    </ul>
                </aside>
            </div>


            <!-- Main Content -->
            <div class="main-content">

                @yield('content')
                <form action="">
                    <input type="hidden" id="user_type" value="{{ session('user_type') }}">
                </form>

            </div>

        </div>
    </div>



    <script src="{{ asset('asset/js/app.min.js ') }}"></script>


    <script src="{{ asset('asset/bundles/datatables/datatables.min.js ') }}"></script>
    {{-- <script src=" {{ asset('asset/bundles/datatables/DataTables-1.10.16/js/dataTables.bootstrap4.min.js') }}"></script> --}}
    <script src="{{ asset('asset/bundles/jquery-ui/jquery-ui.min.js ') }}"></script>

    <script src=" {{ asset('asset/js/page/datatables.js') }}"></script>

    <script src="{{ asset('asset/js/scripts.js') }}"></script>
    <script src="{{ asset('asset/script/app.js') }}?v=1.0.2"></script>

    <!-- Custom JS File -->
    <script src="{{ asset('asset/bundles/summernote/summernote-bs4.js ') }}"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/printThis/1.15.0/printThis.js"
        integrity="sha512-Fd3EQng6gZYBGzHbKd52pV76dXZZravPY7lxfg01nPx5mdekqS8kX4o1NfTtWiHqQyKhEGaReSf4BrtfKc+D5w=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <script>
        (function () {
            if (typeof iziToast === 'undefined') {
                return;
            }
            // Skip global toast on pages that show their own (avoids duplicate toasts)
            if (document.querySelector('[data-page-toast="1"]')) {
                return;
            }
        @if(Session::has('success'))
            iziToast.success({
                title: 'Success',
                message: @json(Session::get('success')),
                position: 'topRight'
            });
        @endif
        @if(Session::has('error'))
            iziToast.error({
                title: 'Error',
                message: @json(Session::get('error')),
                position: 'topRight'
            });
        @endif
        @if($errors->any())
            iziToast.error({
                title: 'Error',
                message: @json($errors->first()),
                position: 'topRight'
            });
        @endif
        })();
    </script>

</body>


</html>