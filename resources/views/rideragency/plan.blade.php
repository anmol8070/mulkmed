@extends('include.app')

@php
$pageTitle = 'Rider Allocation';
$pageSubtitle = 'Plan Allocation';
@endphp

@section('header')
<link rel="stylesheet" href="{{ asset('asset/css/rideragency/plan.css') }}">
<!-- Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script>
    const domainUrl = "{{ url('/') }}/";
</script>
<script src="{{ asset('asset/script/rideragency/plan.js') }}" defer></script>
@endsection

@section('content')
<div class="content">

    <div class="card">
        <div class="allocation-form">

            <!-- Name Of Product Plan -->
            <div class="row-gap">
                <label>Name Of Product Plan</label>
                <div class="input-group-custom">
                    <select id="productId">
                        <option value="">Select Product Plan</option>
                        <option value="1" selected>Wellness Rider</option>
                    </select>
                    <!-- <i class="fas fa-chevron-down"></i> -->
                </div>
            </div>

            <!-- Agency Type & Agency Name -->
            <div class="row row-gap">
                <div class="col-md-6">
                    <label>Agency Type</label>
                    <div class="input-group-custom">
                        <select id="agencyType">
                            <option value="">Select Agency Type</option>
                            <option value="Hotel" selected>Hotel</option>
                        </select>
                        <!-- <i class="fas fa-chevron-down"></i> -->
                    </div>
                </div>
                <div class="col-md-6">
                    <label>Agency Name</label>
                    <div class="input-group-custom">
                        <select id="agencyId">
                            <option value="">Select Agency Name</option>
                            <option value="1" selected>Mercure Hotel</option>
                        </select>
                        <!-- <i class="fas fa-chevron-down"></i> -->
                    </div>
                </div>
            </div>

            <!-- Payment Type & Expiry -->
            <div class="row row-gap">
                <div class="col-md-6">
                    <label>Select Payment Type</label>
                    <div class="input-group-custom">
                        <select id="paymentType">
                            <option value="Prepaid" selected>Prepaid</option>
                            <option value="Postpaid">Postpaid</option>
                        </select>
                        <!-- <i class="fas fa-chevron-down"></i> -->
                    </div>
                </div>
                <div class="col-md-6">
                   <label>Expiry Date Of Product Plan</label>
                   <div class="input-group-custom">
                       <input type="text" id="expiryDate" placeholder="dd-mm-yyyy" onfocus="(this.type='date')" onblur="(this.type='text')">
                    </div>
                </div>

            </div>

            <!-- ALLOCATION OPTIONS SECTION (Inbound / Outbound) -->
            <div class="allocation-options">
                
                <!-- Inbound Row -->
                <div class="option-container">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="checkInbound" onchange="toggleOption('inbound')">
                        <label for="checkInbound">Inbound</label>
                    </div>
                    
                    <div id="inboundFields" class="option-fields" style="display: none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Inbound Price Per Rider</label>
                                <input type="number" id="inboundPrice" placeholder="Enter Price" oninput="calculateTotal()">
                            </div>
                            <div class="col-md-6 mb-3 rider-qty-container">
                                <label>Number Of Rider Allocated</label>
                                <input type="number" id="inboundQty" placeholder="Enter Number" oninput="calculateTotal()">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Outbound Row -->
                <div class="option-container">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="checkOutbound" onchange="toggleOption('outbound')">
                        <label for="checkOutbound">Outbound</label>
                    </div>
                    
                    <div id="outboundFields" class="option-fields" style="display: none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Outbound Price Per Rider</label>
                                <input type="number" id="outboundPrice" placeholder="Enter Price" oninput="calculateTotal()">
                            </div>
                            <div class="col-md-6 mb-3 rider-qty-container">
                                <label>Number Of Rider Allocated</label>
                                <input type="number" id="outboundQty" placeholder="Enter Number" oninput="calculateTotal()">
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Footer: Total & Button -->
            <div class="allocation-footer">
    <div class="amount-breakdown">

        <div class="amount-box">
            <div class="label">Inbound Amount</div>
            <div class="value">AED <span id="inboundTotal">0</span></div>
        </div>

        <div class="amount-box">
            <div class="label">Outbound Amount</div>
            <div class="value">AED <span id="outboundTotal">0</span></div>
        </div>

        <div class="amount-box total">
            <div class="label">Total Amount</div>
            <div class="value">AED <span id="totalAmount">0</span></div>
        </div>

    </div>

    <button type="button" class="btn-allocate" onclick="allocatePlan()">Allocate</button>
</div>


        </div>
    </div>

</div>
@endsection
