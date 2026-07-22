<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Partner Portal | Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom Style -->
    <link href="{{ asset('asset/css/partnerportal/dashboard.css') }}" rel="stylesheet">
</head>

@php
$dashboardConfig = [
    'apiUrl' => url('/v2/partner/dashboard'),

    /* ===============================
    SUBSCRIPTION INFO
    =============================== */
    'hasSubscriptionPlan' => isset($has_subscription_plan) ? (int) $has_subscription_plan : 0,
    'paymentType' => isset($subscription_plan)
        ? strtolower(trim($subscription_plan->payment_type))
        : null,
    'subscriptionName' => $subscription_plan->subscription_name ?? null,
    'partnerType' => session('partner_type') ?? null,

    /* ===============================
    RIDER TOTALS
    =============================== */
    'subscribedRiders' => $subscribed_riders ?? 0,
    'allocatedRiders' => $allocated_riders ?? 0,
    'remainingRiders' => $remaining_riders ?? 0,

    /* ===============================
    INBOUND RIDERS
    =============================== */
    'inboundRiderNumber'        => $inbound_rider_number ?? 0,
    'inboundAllocatedRiders'    => $inbound_allocated_riders ?? 0,
    'inboundRemainingRiders'    => $inbound_remaining_riders ?? 0,

    /* ===============================
    OUTBOUND RIDERS
    =============================== */
    'outboundRiderNumber'       => $outbound_rider_number ?? 0,
    'outboundAllocatedRiders'   => $outbound_allocated_riders ?? 0,
    'outboundRemainingRiders'   => $outbound_remaining_riders ?? 0,

    /* POSTPAID  */
    'inboundUsedRider'          => $inbound_used_rider ?? 0,
    'inboundPricePerRider'      => $inbound_price_per_rider ?? 0,
    'inboundRiderTotalAmount'   => $inbound_rider_total_amount ?? 0,

    'outboundUsedRider'         => $outbound_used_rider ?? 0,
    'outboundPricePerRider'     => $outbound_price_per_rider ?? 0,
    'outboundRiderTotalAmount'  => $outbound_rider_total_amount ?? 0,

    'totalUsedRider'            => $total_used_rider ?? 0,
    'totalAmount'               => $total_amount ?? 0,
];

session(['dashboardConfig' => $dashboardConfig]);

 
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



<body data-dashboard-config='@json($dashboardConfig)'>

    <div class="portal-layout">

        <!-- SIDEBAR -->
        @include('partnerportal.include.portalsidebar')

        <!-- MAIN -->
        <div class="portal-main">

            <!-- TOP NAVBAR -->
            @include('partnerportal.include.portalnavbar', [
            'pageTitle' => 'Dashboard',
            'pageSubtitle' => 'Welcome back! Here’s your healthcare subscription overview'
            ])

            <!-- CONTENT -->
            <div class="portal-content">
                @php
                // Determine subscription state from backend data
                $has_plan = isset($has_subscription_plan) && $has_subscription_plan == 1;
                $payment_type = trim($subscription_plan->payment_type ?? '');
                $is_prepaid = $has_plan && strcasecmp($payment_type, 'prepaid') == 0;
                $is_postpaid = $has_plan && strcasecmp($payment_type, 'postpaid') == 0;
                @endphp
                
                

                <!-- {{-- Summary Card (Top) --}}
                @if($has_plan && isset($subscription_plan) && session('partner_type'))
                <div class="card mb-3 p-3">
                    <h6 class="mb-1">Active Subscription</h6>
                    <strong class="text-primary">
                        {{ $subscription_plan->subscription_name }}
                        ({{ ucfirst($payment_type) }})
                    </strong>
                    <div class="text-muted small mt-1">
                        Partner Type: {{ ucfirst(session('partner_type')) }}
                    </div>
                </div>
                @endif -->

                {{-- Main Content Toggle --}}
                
                @if(!$has_plan)
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

                @else
                {{-- Shared Welcome Banner for All Active Plans --}}
                <div class="welcome-banner">

                    <div>
                        <h3>Welcome, Premier Health Agency</h3>
                        <p>Manage your healthcare subscriptions and rider allocations</p>
                    </div>

                    @if(isset($payment_type))
                    <span class="payment-type-badge {{ strtolower($payment_type) }}">
                        {{ ucfirst($payment_type) }}
                    </span>
                    @endif

                </div>


                @if(optional($current_plan)->payment_status == 1 && $is_prepaid)

                <!-- PREPAID ONLY -->
                @if(isset($subscribed_riders) && isset($allocated_riders) && isset($remaining_riders))
                <div class="stats-grid">
                    <!-- <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="48" height="48" rx="24" fill="#ECF6FF" />
                                    <path d="M14.9998 11C14.5198 11 14.0838 11.274 13.8748 11.706L10.1248 19.456C10.0215 19.6694 9.9811 19.9077 10.0082 20.1432C10.0353 20.3787 10.1288 20.6016 10.2778 20.786L23.0278 36.536C23.1449 36.7978 23.293 36.7978 23.4612 36.8781C23.6294 36.9584 23.8134 37.0001 23.9998 37.0001C24.1861 37.0001 24.3701 36.9584 24.5383 36.8781C24.7065 36.7978 24.8546 36.6809 24.9718 36.536L37.7218 20.786C37.8707 20.6016 37.9642 20.3787 37.9913 20.1432C38.0184 19.9077 37.978 19.6694 37.8748 19.456L34.1248 11.706C34.0225 11.4945 33.8628 11.3161 33.6638 11.1912C33.4648 11.0664 33.2347 11.0001 32.9998 11H14.9998ZM13.3638 18.5L15.7838 13.5H19.4718L17.9088 18.5H13.3638ZM13.6678 21H17.7658L20.6338 29.606L13.6678 21ZM20.4008 21H27.5988L23.9998 31.797L20.4008 21ZM30.2338 21H34.3318L27.3658 29.606L30.2338 21ZM34.6358 18.5H30.0898L28.5268 13.5H32.2148L34.6358 18.5ZM27.4718 18.5H20.5278L22.0908 13.5H25.9088L27.4718 18.5Z" fill="#607198" />
                                </svg>
                            </div>
                            <span class="badge-status active">Active</span>
                        </div>
                        <div class="stat-title">Total Subscribed Riders</div>
                        <div class="stat-value" id="stat-subscribed">{{ $subscribed_riders ?? 0 }}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="48" height="48" rx="24" fill="#ECF6FF" />
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M19.5935 17.6012C20.0488 17.0992 20.3485 16.4758 20.4563 15.8068C20.5641 15.1377 20.4754 14.4517 20.2008 13.8321C19.9263 13.2125 19.4778 12.686 18.9097 12.3164C18.3417 11.9467 17.6786 11.75 17.0009 11.75C16.3232 11.75 15.6601 11.9467 15.092 12.3164C14.524 12.686 14.0755 13.2125 13.8009 13.8321C13.5264 14.4517 13.4376 15.1377 13.5454 15.8068C13.6533 16.4758 13.953 17.0992 14.4083 17.6012C14.2682 17.6747 14.1341 17.7541 14.0058 17.8392C13.206 18.373 12.6932 19.0712 12.373 19.717C11.9913 20.4948 11.7789 21.3448 11.75 22.2107V25.7492C11.75 26.2134 11.9344 26.6585 12.2626 26.9867C12.5908 27.3149 13.0359 27.4992 13.5 27.4992H17.875C18.1813 26.8776 18.5855 26.3092 19.072 25.8157L19.0493 25.7492H13.5V22.2492C13.5 22.2492 13.5 18.7492 17 18.7492C18.7622 18.7492 19.6373 19.6365 20.0712 20.5167C20.465 20.0722 20.934 19.6942 21.459 19.4037C21.0175 18.6406 20.3713 18.0163 19.5935 17.6012ZM17 16.9992C17.4641 16.9992 17.9092 16.8149 18.2374 16.4867C18.5656 16.1585 18.75 15.7134 18.75 15.2492C18.75 14.7851 18.5656 14.34 18.2374 14.0118C17.9092 13.6836 17.4641 13.4992 17 13.4992C16.5359 13.4992 16.0908 13.6836 15.7626 14.0118C15.4344 14.34 15.25 14.7851 15.25 15.2492C15.25 15.7134 15.4344 16.1585 15.7626 16.4867C16.0908 16.8149 16.5359 16.9992 17 16.9992ZM34.5 27.4992H30.125C29.8187 26.8776 29.4145 26.3092 28.928 25.8157L28.9508 25.7492H34.5V22.2492C34.5 22.2492 34.5 18.7492 31 18.7492C29.2378 18.7492 28.3627 19.6365 27.9287 20.5167C27.5328 20.0695 27.0635 19.6931 26.541 19.4037C26.9825 18.6406 27.6287 18.0163 28.4065 17.6012C27.9512 17.0992 27.6515 16.4758 27.5437 15.8068C27.4359 15.1377 27.5246 14.4517 27.7992 13.8321C28.0737 13.2125 28.5222 12.686 29.0903 12.3164C29.6583 11.9467 30.3214 11.75 30.9991 11.75C31.6768 11.75 32.3399 11.9467 32.908 12.3164C33.476 12.686 33.9245 13.2125 34.1991 13.8321C34.4736 14.4517 34.5624 15.1377 34.4546 15.8068C34.3467 16.4758 34.047 17.0992 33.5917 17.6012C33.7318 17.6747 33.8659 17.7541 33.9943 17.8392C34.794 18.373 35.3067 19.0712 35.627 19.717C36.0031 20.4757 36.2153 21.3051 36.25 22.1512V25.7492C36.25 26.2134 36.0656 26.6585 35.7374 26.9867C35.4092 27.3149 34.9641 27.4992 34.5 27.4992ZM31 16.9992C31.4641 16.9992 31.9092 16.8149 32.2374 16.4867C32.5656 16.1585 32.75 15.7134 32.75 15.2492C32.75 14.7851 32.5656 14.34 32.2374 14.0118C31.9092 13.6836 31.4641 13.4992 31 13.4992C30.5359 13.4992 30.0908 13.6836 29.7626 14.0118C29.4344 14.34 29.25 14.7851 29.25 15.2492C29.25 15.7134 29.4344 16.1585 29.7626 16.4867C30.0908 16.8149 30.5359 16.9992 31 16.9992Z" fill="#607198" />
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M27.5 24C27.5 24.9048 27.157 25.7308 26.5935 26.352C27.4753 26.8295 28.1866 27.5695 28.6288 28.4695C29.0119 29.2455 29.2238 30.0948 29.25 30.9598V34.5C29.25 34.9641 29.0656 35.4093 28.7374 35.7375C28.4092 36.0656 27.9641 36.25 27.5 36.25H20.5C20.0359 36.25 19.5908 36.0656 19.2626 35.7375C18.9344 35.4093 18.75 34.9641 18.75 34.5V30.9615C18.7508 30.8827 18.7543 30.8039 18.7605 30.7253C18.8177 29.9404 19.0257 29.1739 19.373 28.4678C19.8147 27.568 20.5254 26.828 21.4065 26.3503C21.0249 25.9292 20.7515 25.4215 20.6101 24.871C20.4687 24.3206 20.4634 23.744 20.5948 23.1911C20.7261 22.6382 20.9901 22.1256 21.364 21.6976C21.7378 21.2695 22.2103 20.939 22.7405 20.7345C23.2707 20.53 23.8428 20.4577 24.4072 20.5238C24.9717 20.5899 25.5115 20.7925 25.9801 21.114C26.4487 21.4355 26.832 21.8663 27.0968 22.3691C27.3616 22.872 27.5 23.4317 27.5 24ZM20.5 31V34.5H27.5V31C27.5 31 27.5 27.5 24 27.5C20.5 27.5 20.5 31 20.5 31ZM25.75 24C25.75 24.4641 25.5656 24.9093 25.2374 25.2375C24.9092 25.5656 24.4641 25.75 24 25.75C23.5359 25.75 23.0908 25.5656 22.7626 25.2375C22.4344 24.9093 22.25 24.4641 22.25 24C22.25 23.5359 22.4344 23.0908 22.7626 22.7626C23.0908 22.4344 23.5359 22.25 24 22.25C24.4641 22.25 24.9092 22.4344 25.2374 22.7626C25.5656 23.0908 25.75 23.5359 25.75 24Z" fill="#607198" />
                                </svg>
                            </div>
                            <span class="badge-status inuse">In Use</span>
                        </div>
                        <div class="stat-title">Allocated Riders</div>
                        <div class="stat-value" id="stat-allocated">{{ $allocated_riders ?? 0 }}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="48" height="48" rx="24" fill="#ECF6FF" />
                                    <path d="M24.0007 35.6663C25.533 35.6682 27.0507 35.3673 28.4664 34.7809C29.8821 34.1945 31.168 33.3341 32.2502 32.2492C33.3351 31.167 34.1954 29.8811 34.7819 28.4654C35.3683 27.0497 35.6692 25.532 35.6673 23.9997C35.6692 22.4673 35.3683 20.9497 34.7819 19.534C34.1954 18.1183 33.3351 16.8324 32.2502 15.7502C31.168 14.6653 29.8821 13.8049 28.4664 13.2185C27.0507 12.632 25.533 12.3311 24.0007 12.333C22.4683 12.3311 20.9507 12.632 19.5349 13.2185C18.1192 13.8049 16.8333 14.6653 15.7512 15.7502C14.6663 16.8324 13.8059 18.1183 13.2194 19.534C12.633 20.9497 12.3321 22.4673 12.334 23.9997C12.3321 25.532 12.633 27.0497 13.2194 28.4654C13.8059 29.8811 14.6663 31.167 15.7512 32.2492C16.8333 33.3341 18.1192 34.1945 19.5349 34.7809C20.9507 35.3673 22.4683 35.6682 24.0007 35.6663Z" stroke="#607198" stroke-width="2.33333" stroke-linejoin="round" />
                                    <path d="M19.334 24L22.834 27.5L29.834 20.5" stroke="#607198" stroke-width="2.33333" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <span class="badge-status available">Available</span>
                        </div>
                        <div class="stat-title">Remaining Riders</div>
                        <div class="stat-value" id="stat-remaining">{{ $remaining_riders ?? 0 }}</div>
                    </div> -->

                    <!--INBOUND SUBSCRIBED -->
                    <div class="stat-card inbound-bg" data-stat-key="inboundRiderNumber">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="48" height="48" rx="24" fill="#ECF6FF" />
                                    <path d="M15 11L13.8 11.7C13.5 12.1 13.5 12.6 13.5 13.1L10 20C10 20.2 10 20.4 10 20.6C10 20.8 10.1 21 10.2 21.2L23 37C23.1 37.3 23.3 37.3 23.5 37.4C23.6 37.5 23.8 37.5 24 37.5C24.2 37.5 24.4 37.5 24.5 37.4C24.7 37.3 24.9 37.1 25 37L37.8 21.2C38 21 38 20.8 38 20.6C38 20.4 38 20.2 37.9 20L34.1 11.7C34 11.5 33.8 11.3 33.6 11.2C33.4 11.1 33.2 11 33 11H15Z" fill="#607198" fill-opacity="0.1" />
                                    <path d="M14.9998 11H32.9998M14.9998 11C14.5198 11 14.0838 11.274 13.8748 11.706L10.1248 19.456C10.0215 19.6694 9.9811 19.9077 10.0082 20.1432C10.0353 20.3787 10.1288 20.6016 10.2778 20.786L23.0278 36.536C23.1449 36.7978 23.293 36.7978 23.4612 36.8781C23.6294 36.9584 23.8134 37.0001 23.9998 37.0001C24.1861 37.0001 24.3701 36.9584 24.5383 36.8781C24.7065 36.7978 24.8546 36.6809 24.9718 36.536L37.7218 20.786C37.8707 20.6016 37.9642 20.3787 37.9913 20.1432C38.0184 19.9077 37.978 19.6694 37.8748 19.456L34.1248 11.706C34.0225 11.4945 33.8628 11.3161 33.6638 11.1912C33.4648 11.0664 33.2347 11.0001 32.9998 11H14.9998Z" stroke="#607198" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M24 16V28M24 28L20 24M24 28L28 24" stroke="#607198" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <span class="badge-status active">Inbound</span>
                        </div>
                        <div class="stat-title">Inbound Subscribed Riders</div>
                        <div class="stat-value">
                            {{ $inbound_rider_number ?? 0 }}
                        </div>
                    </div>
                    <!-- INBOUND ALLOCATED-->
                    @if(($inbound_allocated_riders ?? 0) > 0)
                    <div class="stat-card inbound-bg" data-stat-key="inboundAllocatedRiders">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="48" height="48" rx="24" fill="#ECF6FF" />
                                    <path d="M11.75 22.2107V25.7492C11.75 26.2134 11.9344 26.6585 12.2626 26.9867C12.5908 27.3149 13.0359 27.4992 13.5 27.4992H17.875M34.5 27.4992H30.125M33.9943 17.8392C34.794 18.373 35.3067 19.0712 35.627 19.717C36.0031 20.4757 36.2153 21.3051 36.25 22.1512V25.7492C36.25 26.2134 36.0656 26.6585 35.7374 26.9867C35.4092 27.3149 34.9641 27.4992 34.5 27.4992" stroke="#607198" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    <circle cx="24" cy="18" r="4" stroke="#607198" stroke-width="2" />
                                    <path d="M24 24V34M24 34L20 30M24 34L28 30" stroke="#607198" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <span class="badge-status inuse">In Use</span>
                        </div>
                        <div class="stat-title">Inbound Allocated Riders</div>
                        <div class="stat-value">
                            {{ $inbound_allocated_riders ?? 0 }}
                        </div>
                    </div>
                    @endif

                    <!--INBOUND REMAINING-->
                    <div class="stat-card inbound-bg" data-stat-key="inboundRemainingRiders">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="48" height="48" rx="24" fill="#ECF6FF" />
                                    <circle cx="24" cy="24" r="11" stroke="#607198" stroke-width="2" />
                                    <path d="M21 24L23 26L27 22" stroke="#607198" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M24 10V14M24 34V38M24 38L20 34M24 38L28 34" stroke="#607198" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <span class="badge-status available">Available</span>
                        </div>
                        <div class="stat-title">Inbound Remaining Riders</div>
                        <div class="stat-value">
                            {{ $inbound_remaining_riders ?? 0 }}
                        </div>
                    </div>

                    <!-- OUTBOUND SUBSCRIBED-->
                    @if(($outbound_rider_number ?? 0) > 0)
                    <div class="stat-card outbound-bg" data-stat-key="outboundRiderNumber">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="48" height="48" rx="24" fill="#ECF6FF" />
                                    <path d="M15 11L13.8 11.7C13.5 12.1 13.5 12.6 13.5 13.1L10 20C10 20.2 10 20.4 10 20.6C10 20.8 10.1 21 10.2 21.2L23 37C23.1 37.3 23.3 37.3 23.5 37.4C23.6 37.5 23.8 37.5 24 37.5C24.2 37.5 24.4 37.5 24.5 37.4C24.7 37.3 24.9 37.1 25 37L37.8 21.2C38 21 38 20.8 38 20.6C38 20.4 38 20.2 37.9 20L34.1 11.7C34 11.5 33.8 11.3 33.6 11.2C33.4 11.1 33.2 11 33 11H15Z" fill="#607198" fill-opacity="0.1" />
                                    <path d="M14.9998 11H32.9998M14.9998 11C14.5198 11 14.0838 11.274 13.8748 11.706L10.1248 19.456C10.0215 19.6694 9.9811 19.9077 10.0082 20.1432C10.0353 20.3787 10.1288 20.6016 10.2778 20.786L23.0278 36.536C23.1449 36.7978 23.293 36.7978 23.4612 36.8781C23.6294 36.9584 23.8134 37.0001 23.9998 37.0001C24.1861 37.0001 24.3701 36.9584 24.5383 36.8781C24.7065 36.7978 24.8546 36.6809 24.9718 36.536L37.7218 20.786C37.8707 20.6016 37.9642 20.3787 37.9913 20.1432C38.0184 19.9077 37.978 19.6694 37.8748 19.456L34.1248 11.706C34.0225 11.4945 33.8628 11.3161 33.6638 11.1912C33.4648 11.0664 33.2347 11.0001 32.9998 11H14.9998Z" stroke="#607198" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M24 28V16M24 16L20 20M24 16L28 20" stroke="#607198" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <span class="badge-status active">Outbound</span>
                        </div>
                        <div class="stat-title">Outbound Subscribed Riders</div>
                        <div class="stat-value">
                            {{ $outbound_rider_number ?? 0 }}
                        </div>
                    </div>
                    @endif



                    <!-- OUTBOUND ALLOCATED-->
                    @if(($outbound_allocated_riders ?? 0) > 0)
                    <div class="stat-card outbound-bg" data-stat-key="outboundAllocatedRiders">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="48" height="48" rx="24" fill="#ECF6FF" />
                                    <path d="M11.75 22.2107V25.7492C11.75 26.2134 11.9344 26.6585 12.2626 26.9867C12.5908 27.3149 13.0359 27.4992 13.5 27.4992H17.875M34.5 27.4992H30.125M33.9943 17.8392C34.794 18.373 35.3067 19.0712 35.627 19.717C36.0031 20.4757 36.2153 21.3051 36.25 22.1512V25.7492C36.25 26.2134 36.0656 26.6585 35.7374 26.9867C35.4092 27.3149 34.9641 27.4992 34.5 27.4992" stroke="#607198" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    <circle cx="24" cy="18" r="4" stroke="#607198" stroke-width="2" />
                                    <path d="M24 34V24M24 24L20 28M24 24L28 28" stroke="#607198" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <span class="badge-status inuse">In Use</span>
                        </div>
                        <div class="stat-title">Outbound Allocated Riders</div>
                        <div class="stat-value">
                            {{ $outbound_allocated_riders ?? 0 }}
                        </div>
                    </div>
                    @endif



                    <!--OUTBOUND REMAINING-->
                    @if(($outbound_remaining_riders ?? 0) > 0)
                    <div class="stat-card outbound-bg" data-stat-key="outboundRemainingRiders">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="48" height="48" rx="24" fill="#ECF6FF" />
                                    <circle cx="24" cy="24" r="11" stroke="#607198" stroke-width="2" />
                                    <path d="M21 24L23 26L27 22" stroke="#607198" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M24 38V34M24 14V10M24 10L20 14M24 10L28 14" stroke="#607198" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <span class="badge-status available">Available</span>
                        </div>
                        <div class="stat-title">Outbound Remaining Riders</div>
                        <div class="stat-value">
                            {{ $outbound_remaining_riders ?? 0 }}
                        </div>
                    </div>
                    @endif


                </div>
                @endif
                @elseif($is_postpaid)
<!-- POSTPAID ONLY -->
<div class="usage-grid mb-4">

    <!-- CURRENT MONTH USAGE (KEEP AS IT IS) -->
    <div class="usage-card">
        <div class="usage-badge current">Current Month</div>

        <div class="usage-label">Number Of Riders Used</div>
        <div class="usage-value-big">{{ $total_used_rider ?? 0 }}</div>

        <div class="usage-details">
            <div class="detail-item">
                <label>Price Per Rider</label>
                <span>
                    AED {{ $inbound_price_per_rider ?? 0 }}
                    @if(isset($outbound_price_per_rider) && $outbound_price_per_rider != $inbound_price_per_rider)
                        - {{ $outbound_price_per_rider }}
                    @endif
                </span>
            </div>

            <div class="detail-item">
                <label>Total Amount</label>
                <span>AED {{ number_format($total_amount ?? 0, 2) }}</span>
            </div>
        </div>
    </div>

    <!-- INBOUND USAGE CARD -->
    <div class="usage-card inbound-bg">
        <div class="usage-badge current">Inbound</div>

        <div class="usage-label">Inbound Riders Used</div>
        <div class="usage-value-big">
            {{ $inbound_used_rider ?? 0 }}
        </div>

        <div class="usage-details">
            <div class="detail-item">
                <label>Price Per Rider</label>
                <span>AED {{ $inbound_price_per_rider ?? 0 }}</span>
            </div>

            <div class="detail-item">
                <label>Total Amount</label>
                <span>
                    AED {{ number_format($inbound_rider_total_amount ?? 0, 2) }}
                </span>
            </div>
        </div>
    </div>

    <!-- OUTBOUND USAGE CARD -->
    <div class="usage-card outbound-bg">
        <div class="usage-badge current">Outbound</div>

        <div class="usage-label">Outbound Riders Used</div>
        <div class="usage-value-big">
            {{ $outbound_used_rider ?? 0 }}
        </div>

        <div class="usage-details">
            <div class="detail-item">
                <label>Price Per Rider</label>
                <span>AED {{ $outbound_price_per_rider ?? 0 }}</span>
            </div>

            <div class="detail-item">
                <label>Total Amount</label>
                <span>
                    AED {{ number_format($outbound_rider_total_amount ?? 0, 2) }}
                </span>
            </div>
        </div>
    </div>

</div>
@endif


                @endif

            </div>

        </div>
    </div>
    </div>

    <!-- Custom Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('asset/js/partnerportal/dashboard.js') }}"></script>
</body>

</html>