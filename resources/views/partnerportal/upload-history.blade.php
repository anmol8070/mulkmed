<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Partner Portal | Upload History</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <!-- External CSS -->
    <link rel="stylesheet" href="{{ asset('asset/css/partnerportal/upload-history.css') }}">
</head>

@php
// ✅ SAME CONFIG AS DASHBOARD
$dashboardConfig = session('dashboardConfig') ?? [
'hasSubscriptionPlan' => isset($has_subscription_plan) ? (int)$has_subscription_plan : 0,
'paymentType' => isset($subscription_plan)
? strtolower(trim($subscription_plan->payment_type))
: null,
'subscriptionName' => $subscription_plan->subscription_name ?? null,
'partnerType' => session('partner_type') ?? null,
'subscribedRiders' => $subscribed_riders ?? 0,
'allocatedRiders' => $allocated_riders ?? 0,
'remainingRiders' => $remaining_riders ?? 0,
];
@endphp

<body>

    <div class="portal-layout">

        <!-- SIDEBAR -->
        @include('partnerportal.include.portalsidebar')

        <!-- MAIN -->
        <div class="portal-main">

            <!-- NAVBAR -->
            @include('partnerportal.include.portalnavbar', [
            'pageTitle' => 'Document Upload History',
            'pageSubtitle' => 'View a record of your previous uploads'
            ])

            <!-- CONTENT -->
            <div class="portal-content">

                <div class="history-card">

                    <!-- SEARCH -->
                    <div class="search-box mb-3">
                        <i class="fas fa-search"></i>
                        <input type="text"
                            class="form-control"
                            id="historySearch"
                            placeholder="Search file, date, time">
                    </div>

                    <!-- TABLE -->
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>File Name</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <tr>
                                    <td colspan="4" class="text-center text-muted">
                                        Loading...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>

            </div>
        </div>
    </div>

    <!-- VIEW DETAILS MODAL -->
    <div class="modal fade" id="viewDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Booking Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr id="modalTableHead"></tr>
                            </thead>

                            <tbody id="modalTableBody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted">
                                        Loading...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>

            </div>
        </div>
    </div>

    <!-- ✅ GLOBAL JS CONFIG -->
    <script>
        const domainUrl = "{{ url('/') }}/";

        // 🔥 SAME CONFIG AS DASHBOARD
        window.dashboardConfig = @json($dashboardConfig);

        console.log('Dashboard Config:', window.dashboardConfig);
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- PAGE JS -->
    <script src="{{ asset('asset/script/partnerportal/upload-history.js') }}" defer></script>

</body>

</html>