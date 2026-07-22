@extends('include.app')
 
@section('header')


 <script src="{{ asset('asset/script/patient_appointment/appointment.js') }}?v=1.0.1"></script>
@endsection

<style>

/*  MAIN WRAPPER (2 COLUMN) */
.reg-wrapper {
    display: flex;
    gap: 40px;
    padding: 30px;
    align-items: flex-start;
}

.reg-illustration img {
    width: 350px;
    max-width: 100%;
}

.reg-form {
    width: 100%;
}

.form-title {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 15px;
}


/*  WEEKLY SLOT SECTION */
.week-slot-container {
    display: flex;
    justify-content: left;
    gap: 20px;
    margin-top: 10px;
    flex-wrap: wrap;
}

.day-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 90px;
}

.day-item span {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 5px;
}

.add-slot-btn {
    width: 75px;
    height: 40px;
    border: 2px solid #1E88E5;
    border-radius: 50%;
    background: transparent;
    font-size: 20px;
    color: #1E88E5;
    cursor: pointer;
    display: flex;
    justify-content: center;
    align-items: center;
}

.add-slot-btn:hover {
    background: #1E88E5;
    color: white;
}


/* MODAL*/
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.45);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.modal-box {
    background: white;
    width: 420px;
    max-width: 90%;
    border-radius: 10px;
    padding: 20px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.close-modal {
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
}

.modal-body {
    margin-top: 15px;
}

.modal-footer {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}


/*  RESPONSIVE BREAKPOINTS*/

/* Tablet */
@media (max-width: 992px) {
    .reg-wrapper {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .week-slot-container {
        justify-content: center;
    }
}

/* Mobile */
@media (max-width: 576px) {

    .reg-wrapper {
        padding: 15px;
        gap: 20px;
    }

    .day-item {
        width: 70px;
    }

    .add-slot-btn {
        width: 35px;
        height: 35px;
        font-size: 18px;
    }

    .modal-box {
        width: 95%;
    }
}
 
 .day-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 8px;
}

.add-slot-btn,
.consult-now-btn {
    padding: 6px 12px;
    border: none;
    background-color: #007bff;
    color: #fff;
    border-radius: 4px;
    cursor: pointer;
}

.consult-now-btn {
    background-color: #28a745; /* Green CTA vibes */
}

.appointment-div{
    justify-content: center;
}

.doctors-slot{
    width: 120px;
}


</style>
 
@section('content')
 
<div class="reg-wrapper">
 
    <div class="reg-form">
 
        <h2 class="form-title">Book Appointment</h2>
 
        {{-- VALIDATION ERRORS --}}
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
 
        {{-- SUCCESS MESSAGE --}}
        @if(session('success'))
            <p class="alert alert-success">{{ session('success') }}</p>
        @endif
 
        <form id="patientAppointmentForm" method="POST" enctype="multipart/form-data" action="{{ route('patientAppointment.storeAppointment') }}">
            @csrf
            <input type="hidden" name="browser_timezone" id="browser_timezone" value="">
 
            <!-- PATIENT SELECTION -->
            <div class="form-group mt-3">
                <label>Patient Selection</label>
 
                <input list="patientList" id="patientInput" name="patient_display" 
                       class="form-control"
                       placeholder="Type name or phone number..."/>
 
                <datalist id="patientList">
                    @foreach ($patients as $p)
                        <option data-id="{{ $p->id }}" data-type="{{ $p->type }}" value="{{ $p->fullname }} ({{ $p->phone_number }})"></option>
                    @endforeach
                </datalist>
 
                <input type="hidden" name="user_id" id="patientHidden" required>
            </div>

              <div id="InsuranceUI"
                style="display:none; margin-top:10px; padding:10px; font-size:14px;">
            </div>
 
            <!-- DOCUMENT UPLOAD -->
            <div class="form-group mt-3">  
                <label>Upload Document (Only 1)</label>
                <input type="file" id="documentUpload" name="document" class="form-control" required>
                <small id="docError" class="text-danger d-none">Only 1 document allowed.</small>
            </div>
 
            <!-- DOCTOR SELECT -->
            <div class="form-group mt-3">
                <label>Doctor Selection</label>
                <select id="doctorSelect" name="doctor_id" class="form-control select2">
                    <option value="">Select Doctor</option>
                    @foreach ($doctors as $doc)
                        <option value="{{ $doc->id }}">{{ $doc->name }}</option>
                    @endforeach
                </select>
            </div>

 
            <!-- WEEKLY SLOT SYSTEM -->


           <div class="form-group mt-4">
    <label><strong>Select Appointment</strong></label>

    <div class="week-slot-container">

        <div class="day-item doctors-slot">
            <span>Select Dr's. Slot</span>
            <button type="button" class="add-slot-btn" data-day="">+</button>
        </div>

        <div class="day-item appointment-div">
                <span>Or</span>
        </div>

        <!-- Consult Now -->
        <div class="day-item mt-2 appointment-div">
        
            <button type="button" id="consultNowBtn" class="consult-now-btn">Consult Now</button>
        </div>

    </div>
</div>
       

            <input type="hidden" name="admin_billing_type" id="adminBillingType">


            {{-- weekday selected --}}
            <input type="hidden" name="weekday" id="weekdaySelected">
 
            <!-- HIDDEN FIELDS FOR SLOT DATE & TIME -->
            <input type="hidden" name="date" id="slotDateField">
            <input type="hidden" name="time" id="slotTimeField">

            {{-- Other payment data --}}
            <input type="hidden" name="problem" id="" value="Problem">
            <input type="hidden" name="order_summary"
            value='{"service_amount":"0","discount_amount":"0","subtotal":"0","total_tax_amount":"0","payable_amount":"0","coupon_apply":"0","taxes":[],"couponData":null}'>

 
            <!-- HIDDEN FIELDS FOR ORDER (ALL ZERO / EMPTY) -->
            <input type="hidden" name="is_coupon_applied" id="is_coupon_applied" value="0">
            <input type="hidden" name="service_amount" id="service_amount" value="0">
            <input type="hidden" name="discount_amount" id="discount_amount" value="0">
            <input type="hidden" name="subtotal" id="subtotal" value="0">
            <input type="hidden" name="total_tax_amount" id="total_tax_amount" value="0">
            <input type="hidden" name="payable_amount" id="payable_amount" value="0">

            <div id="insuranceContainer" class="mt-2"></div>

            <div id="selectedSlotUI"
                style="display:none; margin-top:10px; padding:10px; font-size:14px;">
            </div>
 
            <button class="btn btn-primary mr-1 mt-3">Book Appointment</button>
 
        </form>
 
    </div>
</div>
 
<!-- POPUP MODAL -->
<div id="slotModal" class="modal-overlay">
    <div class="modal-box">
 
        <div class="modal-header">
            <h3>Add Slot</h3>
            <button class="close-modal" type="button">X</button>
        </div>
 
        <div class="modal-body">
 
            <!-- DATE FIELD -->
            <label>Select Date</label>
            <input type="date" id="slotDate" class="form-control mb-3">
 
            <!-- TIME FIELD -->
            <label>Select Time</label>
            <select id="slotTime" class="form-control">  
            </select>
 
        </div>
 
        <div class="modal-footer">
            <button class="btn btn-secondary close-modal" type="button">Cancel</button>
            <button class="btn btn-primary" type="button" id="saveSlotBtn">Save Slot</button>
        </div>
 
    </div>
</div>
 
 
<script>
    (function() {
        function resolveBrowserTimezone() {
            try {
                if (window.BROWSER_TIMEZONE && String(window.BROWSER_TIMEZONE).trim() !== '') {
                    return String(window.BROWSER_TIMEZONE).trim();
                }
                if (typeof Intl !== 'undefined' && Intl.DateTimeFormat) {
                    return Intl.DateTimeFormat().resolvedOptions().timeZone || '';
                }
            } catch (e) {}
            return '';
        }

        function applyTimezoneToAppointmentForm() {
            var form = document.getElementById('patientAppointmentForm');
            if (!form) return;

            var tz = resolveBrowserTimezone();
            if (!tz) return;

            var input = document.getElementById('browser_timezone');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'browser_timezone';
                input.id = 'browser_timezone';
                form.appendChild(input);
            }
            input.value = tz;
        }

        document.addEventListener('DOMContentLoaded', applyTimezoneToAppointmentForm);
        document.addEventListener('submit', function(e) {
            if (e.target && e.target.id === 'patientAppointmentForm') {
                applyTimezoneToAppointmentForm();
            }
        }, true);
    })();
</script>

@endsection