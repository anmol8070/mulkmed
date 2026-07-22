<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Partner Portal | Subscription</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    @php
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

    /* =====================================================
    SAFE DEFAULTS (NEVER SHOW 0 WRONGLY)
    ===================================================== */

    $inbound = $outbound = 0;
    $subscribed_riders = $remaining_riders = $used_riders = 0;
    $price_per_rider = $total_amount = 0;
    $is_postpaid = false;
    $reference_plan = null;

    if ($current_plan) {
    $reference_plan = $current_plan;
    } else {
    // FALLBACK: Fetch their assigned allocation if they have NO active subscription
    $reference_plan = \App\Models\RiderAllocation::select(
    'rider_allocations.id as subscription_id',
    'product_plan.name as subscription_name',
    'product_plan.description',
    'rider_allocations.inbound_rider_number',
    'rider_allocations.outbound_rider_number',
    'rider_allocations.inbound_amount',
    'rider_allocations.outbound_amount',
    'rider_allocations.payment_type',
    'rider_allocations.amount as total_fixed_amount',
    'rider_allocations.expiry_date as reference_expiry'
    )
    ->leftJoin('product_plan', 'product_plan.id', '=', 'rider_allocations.product_plan_id')
    ->where('rider_allocations.agency_id', $agency_id)
    ->where('rider_allocations.is_deleted', 0)
    ->orderByDesc('rider_allocations.id')
    ->first();

    // EXTREME FALLBACK: If even the allocation query returns null, create a dummy object to ensure the UI card renders
    if (!$reference_plan) {
    $reference_plan = (object)[
    'subscription_id' => null,
    'subscription_name' => 'Wellness Rider',
    'description' => 'Healthcare rider plan',
    'inbound_rider_number' => 0,
    'outbound_rider_number' => 0,
    'inbound_amount' => 5,
    'outbound_amount' => 8,
    'payment_type' => 'postpaid',
    'total_fixed_amount' => 0,
    'reference_expiry' => now()->endOfMonth()
    ];
    }
    }

    if ($reference_plan) {

    /* ================= PAYMENT TYPE ================= */

    $is_postpaid = strtolower($reference_plan->payment_type ?? '') === 'postpaid';

    /* ================= RIDERS ================= */

    $inbound = (int) ($reference_plan->inbound_rider_number ?? 0);
    $outbound = (int) ($reference_plan->outbound_rider_number ?? 0);

    $subscribed_riders = $inbound + $outbound;

    if ($current_plan) {
    // Use the sum of inbound/outbound remaining riders for accuracy
    $remaining_riders = (int) ($current_plan->inbound_remaining_riders ?? 0) + (int) ($current_plan->outbound_remaining_riders ?? 0);

    // If the sum is 0 but they HAVE a prepaid plan, fall back to remaining_riders column just in case
    if ($remaining_riders === 0 && !$is_postpaid) {
    $remaining_riders = (int) ($current_plan->remaining_riders ?? 0);
    }
    $used_riders = max(0, $subscribed_riders - $remaining_riders);
    } else {
    // No active plan? Everyone is "remaining" (prepaid) or 0 used (postpaid)
    $remaining_riders = $subscribed_riders;
    $used_riders = 0;
    }

    /* ================= PRICE PER RIDER ================= */
    $inbound_price = (int) ($reference_plan->inbound_amount ?? 0);
    $outbound_price = (int) ($reference_plan->outbound_amount ?? 0);

    /* ================= TOTAL AMOUNT ================= */

    if ($is_postpaid) {
    if ($current_plan) {
    $inbound_used = max(0, (int)$inbound - (int)($current_plan->inbound_remaining_riders ?? 0));
    $outbound_used = max(0, (int)$outbound - (int)($current_plan->outbound_remaining_riders ?? 0));
    $total_amount = ($inbound_used * $inbound_price) + ($outbound_used * $outbound_price);
    } else {
    // FALLBACK for display if no active plan: Show potential total (Allocation * Price)
    $total_amount = ($inbound * $inbound_price) + ($outbound * $outbound_price);
    }
    } else {
    $total_amount = (int) ($reference_plan->amount ?? $reference_plan->total_fixed_amount ?? 0);
    }
    }

    /* =====================================================
    BANNER VALUE FIX (NO 0 BUG)
    ===================================================== */

    if ($is_postpaid) {
    $banner_label = 'Used Riders';
    $banner_value = $used_riders;
    } else {
    $banner_label = 'Remaining Riders';
    $banner_value = $remaining_riders;
    }

    /* =====================================================
    IDS FOR PAY NOW
    ===================================================== */

    $agency_subscription_plan_id = $current_plan->id ?? null;
    $subscription_id = $reference_plan->subscription_id ?? null;

    $partnerType = session('partner_type') ?? 'Hotel';

    //MODAL CALCULATIONS (MOVED TO TOP TO PREVENT PARSE ERRORS)
    $inboundDisplayCount = 0;
    $outboundDisplayCount = 0;
    if ($reference_plan) {
    if ($is_postpaid && $current_plan) {
    $inboundDisplayCount = max(0, (int)$inbound - (int)($current_plan->inbound_remaining_riders ?? 0));
    $outboundDisplayCount = max(0, (int)$outbound - (int)($current_plan->outbound_remaining_riders ?? 0));
    } else {
    $inboundDisplayCount = $inbound;
    $outboundDisplayCount = $outbound;
    }
    $inbound_total = $inboundDisplayCount * ($inbound_price ?? 0);
    $outbound_total = $outboundDisplayCount * ($outbound_price ?? 0);
    }

    $expiryMonthYear = '—';

    $expirySource = $current_plan->expiry_date 
                    ?? $reference_plan->reference_expiry 
                    ?? null;

    if (!empty($expirySource)) {
        $expiryMonthYear = \Carbon\Carbon::parse($expirySource)->format('M Y');
    }

    @endphp

    <!-- External CSS -->
    <link rel="stylesheet" href="{{ asset('asset/css/partnerportal/subscription.css') }}">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script>
        window.domainUrl = "{{ url('/') }}/";
        window.subscriptionConfig = {
            isPostpaid: {{ $is_postpaid ? 'true' : 'false' }},
            agencyId: "{{ session('agency_id') }}",
            expiryDate: "{{ $current_plan->expiry_date ?? '' }}",
            addAgencySubscriptionPlanUrl: "{{ route('addAgencySubscriptionPlan') }}"
        };
    </script>
</head>


<body>


    <div class="portal-layout">

        @include('partnerportal.include.portalsidebar')

        <div class="portal-main">

            @include('partnerportal.include.portalnavbar', [
            'pageTitle' => $current_plan ? 'Subscription Plans' : 'Our Products',
            'pageSubtitle' => 'Choose the perfect plan for your healthcare rider needs'
            ])

            <div class="portal-content">

                <!-- CURRENT PLAN -->
                @if($current_plan)
                <div class="current-plan {{ $is_postpaid ? 'postpaid-banner' : '' }}">
                    <div class="current-plan-info">
                        <div class="banner-label">Current Plan</div>
                        <h4 class="plan-name">{{ $current_plan->subscription_name ?? 'Wellness Rider' }}</h4>

                        @if($is_postpaid)
                        <div class="banner-details">
                            <div class="banner-detail-item">
                                <i class="far fa-calendar-alt"></i>
                                Expiry Date: {{ isset($current_plan->expiry_date) ? \Carbon\Carbon::parse($current_plan->expiry_date)->format('jS M Y') : '—' }}
                            </div>
                            @if(isset($current_plan->due_date))
                            <div class="banner-detail-item">
                                <i class="far fa-calendar-alt"></i>
                                Due Date: {{ \Carbon\Carbon::parse($current_plan->due_date)->format('jS M Y') }}
                            </div>
                            @endif
                        </div>
                        @else
                        <div class="banner-expiry">
                            <i class="far fa-calendar-alt"></i>
                            Expires On {{ isset($current_plan->expiry_date) ? \Carbon\Carbon::parse($current_plan->expiry_date)->format('jS M Y') : '—' }}
                        </div>
                        @endif

                        @if($current_plan && isset($current_plan->expiry_date))
                            @php
                                $expiryDate = \Carbon\Carbon::parse($current_plan->expiry_date)->startOfDay();
                                $today = now()->startOfDay();
                                $daysLeft = $today->diffInDays($expiryDate, false);
                            @endphp
                            @if($daysLeft >= 0 && $daysLeft <= 5)
                                <div class="expiry-warning-msg">
                                    @if($daysLeft == 0)
                                        Your subscription expires today. Please renew to avoid service interruption.
                                    @elseif($daysLeft == 1)
                                        Your subscription will expire in 1 day. Please renew to avoid service interruption.
                                    @else
                                        Your subscription will expire in {{ $daysLeft }} days. Please renew to continue uninterrupted service.
                                    @endif
                                </div>
                            @endif
                        @endif
                    </div>
                    <div class="remaining-riders">
                        <div class="banner-label">{{ $is_postpaid ? 'Used Riders' : 'Remaining Riders' }}</div>
                        <div class="rider-count">{{ $banner_value }}</div>
                    </div>
                </div>
                @endif

                <!-- PLANS -->
                <div class="card-grid">

                    <div class="plan-card active">
                        <div class="icon-box">
                            <i class="fas fa-star"></i>
                        </div>
                        <h6>{{ $upcoming_plan->subscription_name ?? 'Wellness Rider' }}</h6>
                        <div class="sub-title">One Rider :</div>
                        <ul class="features-list">
                            <li>1 MIDAS</li>
                            <li>1 AI Health Check</li>
                            <li>1 Doctor Consultation</li>
                        </ul>
                        <span class="offer-text">Offer Valid Till End Of The Month</span>
                    </div>

                    <div class="plan-card">
                        <div class="icon-box">
                            <i class="fas fa-crown"></i>
                        </div>
                        <h6>Mulk Traveler Insurance</h6>
                        <div class="upcoming-text">Upcoming</div>
                    </div>

      
              
 @if(isset($reference_plan) && optional($current_plan)->payment_status == 0)

                    <div class="plan-card invoice-card upcoming-invoice-view">
                        <div class="content-wrapper">
                            <div class="invoice-intro">
                                <div class="invoice-label">Your <br> Invoice of</div>
                                <div class="invoice-date">
                                   
                                  {{ $expiryMonthYear }}
                                     

                                </div>
                            </div>

                            @if($is_postpaid && !$current_plan)
                            <button
                                class="subscribe-btn"
                                data-subscription-id="{{ $upcoming_plan->id ?? $reference_plan->subscription_id ?? '' }}"
                                onclick="subscribePlan(this)">
                                Subscribe
                            </button>
                            @else
                            
                            <button
                                class="pay-now-btn"
                                data-plan-id="{{ $is_postpaid ? ($current_plan->subscription_id ?? $reference_plan->subscription_id ?? '') : '' }}"
                                onclick="handlePayNow(this)">
                                Pay now
                            </button>
                            
                            @endif
                        </div>
                    </div>
                    @endif


                </div>

                <!-- HISTORY -->
                @if($current_plan)
                <div class="history-section">

                    <div class="history-header" onclick="toggleHistory()">
                        <strong>My Subscription History</strong>
                        <div class="toggle-btn">
                            <span id="toggleText">Show</span>
                            <i class="fas fa-chevron-down" id="toggleIcon"></i>
                        </div>
                    </div>

                    <div class="history-table" id="historyTable">
                        <table class="table table-borderless align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Payment Type</th>
                                    <th>Amount Paid</th>
                                    <th>No. Of Riders</th>
                                    <th>Service Type</th>
                                    <th>Invoice</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- ✅ DATA WILL LOAD DYNAMICALLY -->
                            </tbody>
                        </table>

                        <!-- ✅ PAGINATION CONTROLS -->

                        <div class="d-flex justify-content-between align-items-center mt-3 px-3">

                            <!-- LEFT INFO -->
                            <div id="paginationInfo" class="text-muted small">
                                Showing 0 to 0 of 0 entries
                            </div>

                            <!-- RIGHT PAGINATION -->
                            <nav>
                                <ul class="pagination pagination-sm mb-0" id="paginationControls">
                                    <!-- JS will render buttons -->
                                </ul>
                            </nav>

                        </div>

                    </div>

                </div>
                @endif

            </div>
        </div>
    </div>

    <!-- MODAL -->
    <div class="modal-overlay" id="upcomingInvoiceModal">
        <div class="modal-container"
            data-subscription-id="{{ 
                 $is_postpaid 
                     ? ($agency_subscription_plan_id ?? '') 
                     : ($subscription_id ?? '') 
             }}">

            <div class="close-btn-outer" onclick="closeUpcomingInvoiceModal()">
                <i class="fas fa-times"></i>
            </div>
            <div class="modal-box">
                <div class="modal-header-container {{ $is_postpaid ? 'postpaid-header' : '' }}">
                    @if($current_plan)
                    <div class="invoice-intro">
                        <div class="invoice-date">{{ $expiryMonthYear }}</div>
                    </div>
                    <div class="current-plan-summary {{ $is_postpaid ? 'postpaid-summary' : '' }}">
                        <div class="summary-label">Current plan</div>
                        <div class="summary-main">
                            <span class="plan-name">{{ $current_plan->subscription_name ?? 'Wellness Rider' }}</span>
                            @if($is_postpaid)
                            <span class="sep">|</span>
                            <div class="riders-info">
                                <small>Used Riders</small>
                                <strong>{{ $used_riders }}</strong>
                            </div>
                            @else
                            <span class="sep">|</span>
                            <span class="riders">
                                <strong>{{ $remaining_riders }}</strong> Riders
                            </span>
                            @endif
                        </div>

                        @if($is_postpaid)
                        <div class="summary-details-row">
                            <div class="expiry-col">
                                <div class="expiry-label"><i class="far fa-calendar-alt"></i> Expiry Date:</div>
                                <div class="expiry-val">
                                    {{ isset($current_plan->expiry_date) ? \Carbon\Carbon::parse($current_plan->expiry_date)->format('jS M Y') : '—' }}
                                </div>
                            </div>
                            <div class="timer-section">
                                <div class="timer-label">Remaining Time to Pay</div>
                                <div class="timer-boxes" id="countdownTimer">
                                    <div class="timer-box"><span id="days">00</span><small>Days</small></div>
                                    <div class="timer-box"><span id="hours">00</span><small>Hours</small></div>
                                    <div class="timer-box"><span id="minutes">00</span><small>Minutes</small></div>
                                    <div class="timer-box"><span id="seconds">00</span><small>Seconds</small></div>
                                </div>
                            </div>
                        </div>
                        @else
                        <div class="summary-expiry">
                            <i class="far fa-calendar-alt"></i>
                            Expires On {{ isset($current_plan->expiry_date) ? \Carbon\Carbon::parse($current_plan->expiry_date)->format('jS M Y') : '31st Jan 2026' }}
                        </div>
                        @endif
                    </div>
                    @else
                    <div class="no-subscription-header">
                        <h3 class="modal-title-main">Upcoming Invoices of {{ \Carbon\Carbon::parse($reference_plan->reference_expiry)->format('M Y') }}</h3>
                        <p class="modal-subtitle-main">Please purchase a subscription to start allocating Tourist Cards to your customers.</p>
                    </div>
                    @endif
                </div>

                @if($reference_plan)
                <div class="modal-body-content">
                    <div class="plan-payment-card">
                        <div class="card-icon-circle-center">
                            <i class="fas fa-star"></i>
                        </div>

                        <h4 class="modal-card-title">{{ $reference_plan->subscription_name ?? 'Wellness Rider' }}</h4>

                        <div class="service-grid">
                            {{-- ================= INBOUND ================= --}}
                            @if($inbound > 0)
                            <div class="service-column">
                                <div class="service-badge inbound">
                                    Inbound <i class="fas fa-arrow-down"></i>
                                </div>
                                <div class="detail-item">
                                    <label>Allocated Plan</label>
                                    <strong>{{ $inbound }} Riders Plan</strong>
                                </div>
                                <div class="detail-item">
                                    <label>Price Per Rider</label>
                                    <strong>AED {{ $inbound_price }}</strong>
                                </div>
                            </div>
                            @endif

                            {{-- ================= OUTBOUND ================= --}}
                            @if($outbound > 0)
                            <div class="service-column">
                                <div class="service-badge outbound">
                                    Outbound <i class="fas fa-arrow-up"></i>
                                </div>
                                <div class="detail-item">
                                    <label>Allocated Plan</label>
                                    <strong>{{ $outbound }} Riders Plan</strong>
                                </div>
                                <div class="detail-item">
                                    <label>Price Per Rider</label>
                                    <strong>AED {{ $outbound_price }}</strong>
                                </div>
                            </div>
                            @endif
                        </div>

                        {{-- ================= FINAL TOTAL ================= --}}
                        <div class="to-pay-row">
                            <span class="pay-label">To Pay</span>
                            <span class="pay-currency">AED</span>
                            <span class="pay-amount">
                                {{ number_format($total_amount) }}
                            </span>
                        </div>

                        <div class="plan-features">
                            <div class="feature-title">One Rider :</div>
                            <ul class="modal-rider-features">
                                <li>1 MIDAS</li>
                                <li>1 AI Health Check</li>
                                <li>1 Doctor Consultation</li>
                            </ul>
                        </div>

                        @php
                        $expiryDateFormatted = '—';
                        // Prioritize real expiry if available, else reference
                        $expirySource = $current_plan->expiry_date ?? $reference_plan->reference_expiry ?? null;
                        if (!empty($expirySource)) {
                        $expiryDateFormatted = \Carbon\Carbon::parse($expirySource)->format('jS M Y');
                        }
                        @endphp

                        <div class="valid-till">
                            Offer Valid Till {{ $expiryDateFormatted }}
                        </div>

                        <button class="btn-pay-modal" onclick="submitSubscription()">Pay Now</button>

                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- ✅ External JS -->
    <script src="{{ asset('asset/script/partnerportal/subscription.js') }}" defer></script>


</body>

</html>