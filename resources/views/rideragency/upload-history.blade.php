@extends('include.app')

@php
    $pageTitle = 'Upload History';
    $pageSubtitle = '';
@endphp

@section('header')
    <link rel="stylesheet" href="{{ asset('asset/css/rideragency/upload-history.css') }}">

    {{-- REQUIRED FOR API CALLS --}}
    <script>
        window.domainUrl = "{{ url('/') }}/";
    </script>

    <script src="{{ asset('asset/script/rideragency/upload-history.js') }}" defer></script>
@endsection

@section('content')
<div class="content">

    <!-- FILTER CARD -->
    <div class="card">
        <div class="filter-grid">

            <!-- Agency Name -->
            <div class="input-group-custom">
                <label>Agency Name</label>
                <div class="input-container">
                    <select id="agencyDropdown">
                        <option selected disabled>Loading agencies...</option>
                    </select>
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>

            <!-- Agency Type -->
            <div class="input-group-custom">
                <label>Agency Type</label>
                <div class="input-container">
                    <select id="agencyTypeDropdown">
                        <option selected disabled>Loading types...</option>
                    </select>
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>

            <!-- Date -->
            <div class="input-group-custom">
                <label>Date</label>
                <div class="input-container">
                    <input type="date">
                    <i class="fas fa-calendar-alt"></i>
                </div>
            </div>

            <button class="btn-primary" id="applyFilterBtn">Apply Filter</button>

        </div>
    </div>

    <!-- TABLE -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Agency Name</th>
                    <th>Agency Type</th>
                    <th>File Name</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <!-- Dynamic rows will load here -->
        </tbody>
        </table>
    </div>

</div>

<!-- MODAL -->
<div id="detailsModal" class="modal-custom">
    <div class="modal-content-custom">
        <div class="modal-header-custom">
            <h2 id="modalAgencyName"></h2>

            <span id="modalAgencyBadge" class="badge">
                <i id="modalAgencyIcon"></i>
                <span id="modalAgencyTypeText"></span>
            </span>

            <span class="close-modal">&times;</span>
        </div>

        <div class="modal-body-custom">
            <table>
                <thead>
                    <tr id="modalTableHead">
                        <!-- Dynamic headers injected by JS -->
                    </tr>
                </thead>

                <tbody id="modalTbody">
                    <tr>
                        <td class="text-center text-muted">
                            Select a record to view details
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
