@extends('include.app')

@php
    $pageTitle = 'Rider Allocation';
    $pageSubtitle = 'Allocated Agencies';
@endphp

@section('header')
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- ALLOCATED AGENCIES CSS -->
    <link rel="stylesheet" href="{{ asset('asset/css/rideragency/allocated-agencies.css') }}">

    <!-- IMPORTANT: BASE URL FOR JS -->
    <script>
        const domainUrl = "{{ url('/') }}/";
        const editPageUrl = "{{ url('rider-allocation-form') }}";
    </script>

    <!-- ALLOCATED AGENCIES JS -->
    <script src="{{ asset('asset/script/rideragency/allocated-agencies.js') }}" defer></script>
@endsection

@section('content')
<div class="content">

    <div class="page-header">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search Agency">
        </div>
    </div>

    <div class="table-container">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>Agency Name</th>
                    <th>Agency Type</th>
                    <th>Payment Type</th>
                    <th>Price Per Rider</th>
                    <th>Service Type</th>
                    <th>Number Of Rider Allocated</th>
                    <th>Total Amount</th>
                    <th></th>
                </tr>
            </thead>

            <tbody id="allocationTableBody">
                <!-- Dynamic rows injected by JS -->
            </tbody>
        </table>
    </div>

</div>
@endsection
