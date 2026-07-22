@extends('include.app')

@php
$pageTitle = 'Agency Management';
$pageSubtitle = '';
@endphp

@section('header')
<link rel="stylesheet" href="{{ asset('asset/css/rideragency/agencies.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<script>
    const domainUrl = "{{ url('/') }}/";
</script>

<script src="{{ asset('asset/script/rideragency/agency.js') }}?v=1.0" defer></script>
@endsection


@section('content')
<div class="content">

    <div class="page-header">
        <div class="search-box">
            <input type="text" placeholder="Search Agency">
        </div>

        <button class="btn-primary" onclick="openAgencyModal()">
            + Add New Agency
        </button>
    </div>

    <div class="tabs">
        <span class="active" onclick="filterAgency('', this)">All</span>
        <span onclick="filterAgency(1, this)">Travel</span>
        <span onclick="filterAgency(2, this)">Hotel</span>
        <span onclick="filterAgency(3, this)">VISA</span>
    </div>

    <!-- TABLE -->
    <table>
        <thead>
            <tr>
                <th>Agency ID</th>
                <th>Agency Name</th>
                <th>Type</th>
                <th>Contact Info</th>
                <th>Location</th>
                <th></th>

            </tr>
        </thead>

        <tbody id="agencyTableBody">
            <!-- Dynamic Content -->
        </tbody>
    </table>


</div>

<!-- ================= ADD NEW AGENCY MODAL ================= -->

<div class="agency-overlay" id="agencyOverlay"></div>

<div class="agency-modal" id="agencyModal">

    <!-- HEADER -->
    <div class="agency-modal-header">
        <div>
            <h3>Add New Agency</h3>
            <p>Register a new partner agency to MulkMed partner network</p>
        </div>
        <span class="close-btn" onclick="closeAgencyModal()">×</span>
    </div>

    <!-- BODY -->
    <div class="agency-modal-body">

        <!-- ================= AGENCY INFO ================= -->
        <h4>Agency Information</h4>

        <div class="form-grid">
            <div>
                <label>Agency Name</label>
                <input id="agencyName" placeholder="e.g. Global Health Network">
            </div>

            <div>
                <label>Agency Type</label>
                <div class="type-row">
                    <select id="agencyType">
                        <option value="">Select Agency Type</option>
                    </select>
                    <button class="add-type-btn" type="button" onclick="openTypeModal()">
                        + Add New Type
                    </button>
                </div>
            </div>

            <div>
                <label>Address</label>
                <textarea id="agencyAddress" placeholder="Enter full registered address"></textarea>
            </div>

            <div>
                <label>Upload Agency Logo</label>
                <div class="upload-box" id="uploadBox">

                    <!-- SVG ICON -->
                    <svg width="27" height="27" viewBox="0 0 27 27" fill="none"
                        xmlns="http://www.w3.org/2000/svg">
                        <rect x="0.75" y="0.75" width="25.5" height="25.5" rx="12.75"
                            stroke="#607198" stroke-width="1.5" />
                        <path
                            d="M13.5 6.5V20.5M13.5 6.5L19.5 12.5M13.5 6.5L7.5 12.5"
                            stroke="#607198" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" />
                    </svg>

                    <span id="uploadText">
                        Drag and drop file here or <b>choose file</b>
                    </span>

                    <input type="file" id="logoInput" accept="image/*" hidden>
                </div>
            </div>
        </div>

        <!-- ================= ACCOUNT & SECURITY ================= -->
        <h4 style="margin-top:20px;">Account and Security</h4>

        <div class="form-grid">
            <div>
                <label>Email ID</label>
                <input id="agencyEmail" placeholder="e.g. contact123@gmail.com">
                <small style="color:#008CC3; font-size:12px;">
                    This will be used as the primary Login Email ID
                </small>
            </div>


            <div>
                <label>Contact Number</label>
                <input id="agencyPhone" placeholder="Enter Contact Number">
            </div>
        </div>

        <!-- PASSWORD ROW -->
        <div class="form-row">
            <div class="form-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <input type="password" placeholder="Enter Password" id="password">

                </div>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <div class="input-wrapper">
                    <input type="password" placeholder="Confirm Your Password" id="confirmPassword">

                </div>
            </div>
        </div>

    </div>

    <!-- FOOTER -->
    <div class="agency-modal-footer">
        <button class="btn-cancel" onclick="closeAgencyModal()">Cancel</button>
        <button class="btn-primary" onclick="createAgency()">Create Agency</button>
    </div>

</div>


<!-- ADD NEW TYPE MINI MODAL -->
<div class="type-overlay" id="typeOverlay"></div>

<div class="type-modal" id="typeModal">

    <!-- FORM START -->
    <form id="addTypeForm" onsubmit="submitType(event)">

        <!-- HEADER -->
        <div class="type-header">
            <span>Add New Type</span>
            <span class="type-close" onclick="closeTypeModal()">×</span>
        </div>

        <!-- BODY -->
        <div class="type-body">
            <input
                type="text"
                placeholder="Enter Name of Type"
                id="newTypeName"
                required>
        </div>

        <!-- IMAGE UPLOAD -->
        <div class="type-image-upload">
            <div class="type-upload-box" id="typeUploadBox">
                <span id="typeUploadText">Click to upload image</span>
                <input
                    type="file"
                    id="typeImageInput"
                    accept="image/*"
                    required
                    hidden>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="type-footer">
            <button
                type="submit"
                class="type-submit-btn">
                Submit
            </button>
        </div>

    </form>
    <!-- FORM END -->

</div>









@endsection