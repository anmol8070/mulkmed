@extends('include.app')

@section('header')
<link rel="stylesheet" href="{{ asset('asset/css/rideragency/transaction-summary.css') }}">
<script src="{{ asset('asset/script/rideragency/transaction-summary.js') }}" defer></script>
@endsection

@section('content')
<div class="content">

    <!-- ===============================
        1. TRANSACTION SUMMARY
    =============================== -->
    <div id="summary-section" class="transaction-section">
        <h1 class="page-title">Transaction Summary</h1>

        <div class="card">
            <div class="filter-grid">

                <div class="input-group-custom">
                    <label>Agency Type</label>
                    <div class="input-container">
                        <select id="filter-agency-type">
                            <option value="">All Types</option>
                        </select>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>

                <div class="input-group-custom">
                    <label>Agency Name</label>
                    <div class="input-container">
                        <select id="filter-agency-name">
                            <option value="">All Agencies</option>
                        </select>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>

                <div class="input-group-custom">
                    <label>Payment Type</label>
                    <div class="input-container">
                        <select id="filter-payment-type">
                            <option value="">All</option>
                            <option value="prepaid">Prepaid</option>
                            <option value="postpaid">Postpaid</option>
                        </select>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>

                <button class="btn-primary" id="search-btn">Search</button>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Agency Name</th>
                        <th>Agency Type</th>
                        <th>Payment Type</th>
                        <th>Service Type</th>
                        <th>Amount Paid</th>
                        <th>Invoice</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="transaction-summary-body"></tbody>
            </table>
        </div>
    </div>

    <!-- ===============================
        2. AGENCY TRANSACTION DETAILS
    =============================== -->
    <div id="details-section" class="transaction-section" style="display:none;">

        <div class="header-with-back">
            <a class="back-link-trigger" data-section="summary">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="page-title">Agency Transaction Details</h1>
        </div>

        <div class="card agency-profile-card">
            <div class="agency-info">
                <div class="agency-image">
                    <img src="https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=300&q=80">
                </div>

                <div class="agency-details">
                    <h2 id="detail-agency-name"></h2>
                    <span class="agency-id">Agency</span>

                    <div class="profile-badges">
                        <span class="badge" id="detail-agency-badge"></span>
                        <span class="badge-payment" id="detail-payment-badge"></span>
                    </div>
                </div>

                <div class="available-riders-col">
                    <span class="label">Available No. Of Riders</span>
                    <span class="value" id="available-riders">—</span>
                </div>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Payment Month</th>
                        <th>No. of Rider Used</th>
                        <th>Price Per Rider</th>
                        <th>Service Type</th>
                        <th>Payment Status</th>
                        <th>Amount</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="details-body"></tbody>
            </table>
        </div>
    </div>

    <!-- ===============================
        3. RIDER USAGE
    =============================== -->
    <div id="usage-section" class="transaction-section" style="display:none;">

        <div class="header-with-back">
            <a class="back-link-trigger" data-section="details">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="page-title">Rider Usage</h1>
        </div>

        <div class="usage-table-wrapper">
            <div class="table-container usage-table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-center">Number Of Rider Used</th>
                        </tr>
                    </thead>
                    <tbody id="usage-body"></tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
