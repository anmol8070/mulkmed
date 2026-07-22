<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Partner Portal | Excel Upload</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <!-- CSS -->
    <link rel="stylesheet" href="{{ asset('asset/css/partnerportal/excel.css') }}">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- CSRF -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- GLOBAL DOMAIN -->
    <script>
        window.domainUrl = "{{ url('/') }}/";
    </script>
</head>


@php
// Attempt to get dashboard values from session
$dashboardConfig = session('dashboardConfig');

// If session is empty or doesn't have a plan, try to fetch from database
if (!$dashboardConfig || !($dashboardConfig['hasSubscriptionPlan'] ?? 0)) {
$agency_id_local = session('agency_id');
$has_plan_db = 0;
$sub_plan_db = null;
$subscribed_riders_db = 0;
$allocated_riders_db = 0;
$remaining_riders_db = 0;

if ($agency_id_local) {
$sub_plan_db = \App\Models\AgencySubscriptionPlans::select(
'agency_subscription_plans.*',
'rider_allocations.inbound_rider_number',
'rider_allocations.outbound_rider_number',
'product_plan.name as subscription_name'
)
->join('rider_allocations', 'rider_allocations.id', 'agency_subscription_plans.subscription_id')
->join('product_plan', 'product_plan.id', 'rider_allocations.product_plan_id')
->whereMonth('agency_subscription_plans.expiry_date', \Carbon\Carbon::now()->month)
->whereYear('agency_subscription_plans.expiry_date', \Carbon\Carbon::now()->year)
->where('agency_subscription_plans.expiry_date', '>', now())
->where('agency_subscription_plans.agency_id', $agency_id_local)
->first();

if ($sub_plan_db) {
$has_plan_db = 1;
$subscribed_riders_db = intval($sub_plan_db->inbound_rider_number) + intval($sub_plan_db->outbound_rider_number);
$remaining_riders_db = intval($sub_plan_db->inbound_remaining_riders) + intval($sub_plan_db->outbound_remaining_riders);
$allocated_riders_db = $subscribed_riders_db - $remaining_riders_db;
}
}

$dashboardConfig = [
'apiUrl' => url('/v2/partner/dashboard'),
'hasSubscriptionPlan' => $has_plan_db,
'paymentType' => $sub_plan_db->payment_type ?? null,
'paymentStatus' => $sub_plan_db->payment_status ?? null,
'subscriptionName' => $sub_plan_db->subscription_name ?? null,
'partnerType' => session('partner_type') ?? ($partnerType ?? 'hotel'),
'subscribedRiders' => $subscribed_riders_db,
'allocatedRiders' => $allocated_riders_db,
'remainingRiders' => $remaining_riders_db
];

// Optionally update session for consistency if plan was found
if ($has_plan_db) {
session(['dashboardConfig' => $dashboardConfig]);
}
}

// Subscription state
$has_plan = ($dashboardConfig['hasSubscriptionPlan'] ?? 0) == 1;
$payment_type = strtolower($dashboardConfig['paymentType'] ?? '');
$subscription_name = $dashboardConfig['subscriptionName'] ?? 'Active Subscription';

$is_prepaid = $has_plan && strcasecmp($payment_type, 'prepaid') == 0;
$is_postpaid = $has_plan && strcasecmp($payment_type, 'postpaid') == 0;

// Excel specific config
$excelConfig = [
'hasSubscriptionPlan' => $has_plan ? 1 : 0,
'partnerType' => $dashboardConfig['partnerType'] ?? ($partnerType ?? 'hotel')
];

$pType = strtolower($excelConfig['partnerType']);

/* =====================================================
CURRENT PLAN – LOGGED-IN USER (FINAL JOIN VERSION)
===================================================== */

$agency_id = session('agency_id');

$current_plan = \App\Models\AgencySubscriptionPlans::select(
'agency_subscription_plans.id',
'agency_subscription_plans.subscription_id',
'agency_subscription_plans.amount',
'agency_subscription_plans.remaining_riders',
'agency_subscription_plans.inbound_remaining_riders',
'agency_subscription_plans.outbound_remaining_riders',
'agency_subscription_plans.expiry_date',
'agency_subscription_plans.payment_type',
'agency_subscription_plans.payment_status',

'product_plan.name as subscription_name',
'product_plan.description',

'rider_allocations.inbound_rider_number',
'rider_allocations.outbound_rider_number',
'rider_allocations.inbound_amount',
'rider_allocations.outbound_amount'
)
->join('rider_allocations', 'rider_allocations.id', '=', 'agency_subscription_plans.subscription_id')
->join('product_plan', 'product_plan.id', '=', 'rider_allocations.product_plan_id')
->where('agency_subscription_plans.agency_id', $agency_id)
->where('agency_subscription_plans.expiry_date', '>', now())
->orderByDesc('agency_subscription_plans.id')
->first();


@endphp



<body
    data-excel-config='@json($excelConfig)'
    data-dashboard-config='@json($dashboardConfig)'>

    <div class="portal-layout">

        {{-- Sidebar --}}
        @include('partnerportal.include.portalsidebar')

        <div class="portal-main">

            {{-- Navbar --}}
            @include('partnerportal.include.portalnavbar', [
            'pageTitle' => 'Excel Upload & Customer Usage',
            'pageSubtitle' => 'Upload customer data and track healthcare rider usage'
            ])

            <div class="portal-content">

                <!-- <div class="card mb-3 p-3 shadow-sm border-0 bg-white" style="border-radius: 12px;">
                <h6 class="mb-1 text-muted small text-uppercase fw-bold" style="letter-spacing: 0.5px;">Active Subscription</h6>
                <strong class="text-primary h5 mb-0 fw-bold">
                    @if($has_plan)
                        {{ $subscription_name }}
                        <span class="badge bg-light text-primary border ms-2" style="font-size: 0.75rem;">
                            {{ ucfirst($payment_type) }}
                        </span>
                    @else
                        <span class="text-muted">No Subscription</span>
                    @endif
                </strong>
                <div class="text-muted small mt-1">
                    Partner Type: {{ ucfirst($excelConfig['partnerType'] ?? 'N/A') }}
                </div>
            </div> -->


                @if($has_plan && ($current_plan->payment_type === 'Postpaid' || ($current_plan->payment_type === 'Prepaid' && $current_plan->payment_status == 1)))


                {{-- Partner Type --}}
                <input type="hidden" id="partnerType" value="{{ $partnerType ?? 'hotel' }}">

                {{-- Messages --}}
                @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                @endif

                @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    {!! nl2br(e(session('error'))) !!}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                @endif
                
                
                {{-- Download Section --}}
                <div class="download-section">
                    <div class="section-title">
                        <h6>Download Excel Format</h6>
                    </div>
                    <div class="download-btns">
                        @if(str_contains($pType, 'travel'))
                        <button type="button" class="btn-download" data-type="travel">
                            <i class="fas fa-file-download me-2"></i>
                            Travel Excel Format
                        </button>
                        @endif

                        @if(str_contains($pType, 'visa'))
                        <button type="button" class="btn-download" data-type="visa">
                            <i class="fas fa-file-download me-2"></i>
                            Visa Excel Format
                        </button>
                        @endif

                        @if(str_contains($pType, 'hotel'))
                        <button type="button" class="btn-download" data-type="hotel">
                            <i class="fas fa-file-download me-2"></i>
                            Hotel Excel Format
                        </button>
                        @endif
                    </div>
                </div> 


                {{-- Upload Section --}}
                <div class="upload-card">


                    <form id="touristImportForm" action="{{ url('/tourist/import') }}" method="POST" enctype="multipart/form-data" class="d-none">
                        @csrf
                        <input type="file" name="file" id="excelFileInput" accept=".xlsx,.xls,.csv">
                    </form>

                    <div class="upload-drop" id="excelDropZone">

                        <div class="upload-icon">
                            <svg width="78" height="78" viewBox="0 0 78 78" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect width="78" height="78" rx="39" fill="#BDD3FF" />
                                <path d="M39.75 24H40.1595C45.0495 24 47.4975 24 49.1955 25.197C49.6815 25.539 50.1135 25.947 50.478 26.4045C51.75 28.0035 51.75 30.3045 51.75 34.9095V38.727C51.75 43.1715 51.75 45.3945 51.0465 47.169C49.9155 50.0235 47.5245 52.2735 44.4915 53.3385C42.606 54 40.2465 54 35.5215 54C32.8245 54 31.4745 54 30.3975 53.622C28.665 53.013 27.2985 51.7275 26.652 50.097C26.25 49.083 26.25 47.8125 26.25 45.273V39" stroke="#607198" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M51.75 39C51.75 40.3259 51.2233 41.5976 50.2857 42.5352C49.3481 43.4728 48.0764 43.9995 46.7505 43.9995C45.7515 43.9995 44.574 43.8255 43.6035 44.085C43.1796 44.199 42.7931 44.4224 42.4827 44.7327C42.1724 45.0431 41.949 45.4296 41.835 45.8535C41.5755 46.824 41.7495 48.0015 41.7495 49.0005C41.7495 49.657 41.6202 50.3072 41.3689 50.9137C41.1177 51.5203 40.7494 52.0714 40.2852 52.5357C39.8209 52.9999 39.2698 53.3682 38.6632 53.6194C38.0567 53.8707 37.4065 54 36.75 54M27.75 27.75C28.488 26.991 30.45 24 31.5 24M31.5 24C32.55 24 34.512 26.991 35.25 27.75M31.5 24V36" stroke="#607198" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>

                        </div>
                        <!-- onclick="handleExcelUploadClick()" -->
                        <button type="button" class="upload-btn" id="chooseExcelBtn">Choose Excel File</button>

                        <small class="mt-2 d-block">or drag and drop your file here</small>

                        <small>Supported format: XLSX, XLS, CSV (Max size: 10MB)</small>

                    </div>

                    <div class="required-cols">
                        <h6>Required Excel Columns</h6>
                        <ul id="requiredColumnsList"></ul>
                    </div>

                </div>

                {{-- Table --}}
                <div class="table-card">
                    <div class="table-header">
                        <div>
                            <h5>Customer Usage Tracking</h5>
                            <p>Monitor healthcare rider service utilization</p>
                        </div>
                        <input type="text" id="touristSearchInput" placeholder="Search Customer">
                    </div>

                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead>
                                <tr id="tableHead"></tr>

                            </thead>

                            <!-- ✅ FIXED -->
                            <tbody id="touristTableBody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted">
                                        Start typing to search customers
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>


                    <div class="table-footer">
                        <div class="pagination-info"></div>
                        <ul class="pagination" id="pagination"></ul>
                    </div>

                </div>
                @else
                <!-- NO SUBSCRIPTION STATE -->
                <div class="no-subscription-state">
                    <div class="no-subscription-icon">
                        <svg width="72" height="72" viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15.7494 9C14.6694 9 13.6884 9.6165 13.2182 10.5885L4.7807 28.026C4.54844 28.5061 4.45748 29.0424 4.51843 29.5722C4.57938 30.1021 4.78973 30.6037 5.12495 31.0185L33.8124 66.456C34.0761 66.7821 34.4093 67.045 34.7877 67.2257C35.1661 67.4064 35.5801 67.5001 35.9994 67.5001C36.4188 67.5001 36.8328 67.4064 37.2112 67.2257C37.5896 67.045 37.9228 66.7821 38.1864 66.456L66.8739 31.0185C67.2092 30.6037 67.4195 30.1021 67.4805 29.5722C67.5414 29.0424 67.4505 28.5061 67.2182 28.026L58.7807 10.5885C58.5507 10.1126 58.1912 9.7112 57.7435 9.43024C57.2958 9.14929 56.778 9.00018 56.2494 9H15.7494ZM12.0684 25.875L17.5134 14.625H25.8114L22.2947 25.875H12.0684ZM12.7524 31.5H21.9729L28.4259 50.8635L12.7524 31.5ZM27.9017 31.5H44.0972L35.9994 55.7933L27.9017 31.5ZM50.0259 31.5H59.2464L43.5729 50.8635L50.0259 31.5ZM59.9304 25.875H49.7019L46.1852 14.625H54.4832L59.9304 25.875ZM43.8114 25.875H28.1874L31.7042 14.625H40.2947L43.8114 25.875Z" fill="#607198" />
                        </svg>

                    </div>
                     
                    <h2 class="no-subscription-title">You don't have any active subscriptions yet.</h2>
                    <p class="no-subscription-subtitle">Please subscribe to start allocating rider to your customers.</p>
                    <button class="btn-view-plans" onclick="window.location.href='{{ url('partner/subscription') }}'">View Plans & Pricing</button>
                </div>
                @endif

            </div>
        </div>
    </div>


    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- ✅ YOUR JS -->
    <script src="{{ asset('asset/script/partnerportal/excel.js') }}"></script>

</body>

</html>