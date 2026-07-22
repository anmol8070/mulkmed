@extends('include.app')

@php
$pageTitle = 'My Product Plan';
$pageSubtitle = '';
@endphp

@section('header')
<link rel="stylesheet" href="{{ asset('asset/css/rideragency/product-plan.css') }}">
<script>
    const domainUrl = "{{ url('/') }}/";
</script>
<script src="{{ asset('asset/script/rideragency/product-plan.js') }}" defer></script>
@endsection

@section('content')

<div class="product-plan-wrapper">

    <!-- HEADER -->
    <div class="product-plan-header">
        <h3>My Product plan</h3>
        <button class="btn-primary" onclick="openPlanModal()">+ Add New Product Plan</button>
    </div>

    <!-- EMPTY STATE -->
    <div class="empty-state" id="emptyState">
        <div class="empty-icon"><svg width="72" height="72" viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M33.75 4.21875L63 18.8438V55.4062L33.75 69.9961L4.5 55.4062V18.8438L33.75 4.21875ZM55.7227 20.25L33.75 9.28125L25.2773 13.5L47.1094 24.5391L55.7227 20.25ZM33.75 31.2188L42.1172 27.0703L20.25 16.0312L11.7773 20.25L33.75 31.2188ZM9 23.9062V52.5938L31.5 63.8438V35.1562L9 23.9062ZM36 63.8438L58.5 52.5938V23.9062L36 35.1562V63.8438Z" fill="#607198" />
            </svg>
        </div>
        <h4>No any product plan have been added yet!</h4>
        <p>Click below to add your product plan</p>
        <button class="btn-primary" onclick="openPlanModal()">Add New Product Plan</button>
    </div>

    <!-- PLAN LIST -->
    <div class="plan-list" id="planList"></div>

</div>

<!-- ================= MODAL ================= -->
<div class="modal-overlay" id="planOverlay"></div>

<div class="plan-modal" id="planModal">
    <div class="plan-modal-header">
        <div>
            <h4 id="modalTitle">Add New Product Plan</h4>
            <p>Manage your subscription</p>
        </div>
        <span class="close-btn" onclick="closePlanModal()">×</span>
    </div>

    <div class="plan-modal-body">
        <input type="hidden" id="editingPlanId">
        <div class="form-group">
            <label>Plan Name</label>
            <input id="planName" placeholder="Enter Name Of Product Plan">
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea id="planDescription"
                placeholder="Provide a short summary of what this plan includes"></textarea>
        </div>
    </div>

    <div class="plan-modal-footer">
        <button class="btn-cancel" onclick="closePlanModal()">Cancel</button>
        <button class="btn-primary" id="saveBtn" onclick="savePlan()">Create</button>
    </div>
</div>


@endsection