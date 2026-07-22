$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".patientAppointmentSideA").addClass("activeLi");

    const tz = (window.BROWSER_TIMEZONE && String(window.BROWSER_TIMEZONE).trim() !== '')
        ? window.BROWSER_TIMEZONE
        : ((typeof Intl !== 'undefined' && Intl.DateTimeFormat)
            ? Intl.DateTimeFormat().resolvedOptions().timeZone
            : '');
    if (tz) {
        $('#browser_timezone').val(tz);
        $('#patientAppointmentForm').on('submit', function () {
            $('#browser_timezone').val(tz);
        });
    }
// 1 FIELD PATIENT SELECTION USING DATALIST

document.getElementById("patientInput").addEventListener("input", function () {

    let val = this.value;
    let options = document.querySelectorAll("#patientList option");
    let hidden = document.getElementById("patientHidden");

    hidden.value = ""; // reset hidden field

    options.forEach(option => {
        if (option.value === val) {
            hidden.value = option.getAttribute("data-id");
        }
    });
});


// DOCUMENT VALIDATION
const docInput = document.getElementById("documentUpload");
const docError = document.getElementById("docError");

if (docInput) {
    docInput.addEventListener("change", function () {    
        if (this.files.length > 1) {
            docError.classList.remove("d-none");
            this.value = ""; // reset file
        } else {
            docError.classList.add("d-none");
        }
    });
}

$(document).on('click', '.add-slot-btn', function () {
    let day = $(this).data('day');
    $('#weekdaySelected').val(day);
});


// ADD SLOT BUTTON CLICK (OPEN POPUP)
let selectedDay = "";
const modal = document.getElementById("slotModal");

document.querySelectorAll(".add-slot-btn").forEach(btn => {
    btn.addEventListener("click", function () {

        selectedDay = this.getAttribute("data-day");

        // RESET popup inputs
        const today = new Date().toISOString().split("T")[0];
        document.getElementById("slotDate").setAttribute("min", today);
        document.getElementById("slotTime").value = "";

        modal.style.display = "flex";
    });
});


// CLOSE MODAL
document.querySelectorAll(".close-modal").forEach(btn => {
    btn.addEventListener("click", function () {
        modal.style.display = "none";
    });
});


// SAVE SLOT (DATE + TIME) INTO HIDDEN FIELDS
document.getElementById("saveSlotBtn").addEventListener("click", function () {

    let date = document.getElementById("slotDate").value;
    let time = document.getElementById("slotTime").value;

    if (!date) {
        alert("Please select a date");
        return;
    }
    if (!time) {
        alert("Please select a time");
        return;
    }

    // SAVE TO HIDDEN FIELDS
    document.getElementById("slotDateField").value = date;
    document.getElementById("slotTimeField").value = time.replace(":", "");

  
    // SHOW UI CONFIRMATION
    let ui = document.getElementById("selectedSlotUI");
    console.log(ui);
    ui.style.display = "block";
    ui.innerHTML = `
        <strong>Slot Selected:</strong><br>
        📅 Date: <b>${date}</b><br>
        ⏰ Time: <b>${time}</b>
    `;

    modal.style.display = "none"; // close modal
});

document.getElementById("consultNowBtn").addEventListener("click", function () {
    
    // Get today's date (YYYY-MM-DD)
    let today = new Date();
    let date = today.toISOString().split("T")[0];

    // Get current time in HHMM (like your slot format)
    let hours = today.getHours().toString().padStart(2, '0');
    let mins = today.getMinutes().toString().padStart(2, '0');
    let time = `${hours}:${mins}`;

    // SAVE TO HIDDEN FIELDS
    document.getElementById("slotDateField").value = date;
    document.getElementById("slotTimeField").value = time.replace(":", "");

    // Show in UI
    let ui = document.getElementById("selectedSlotUI");
    ui.style.display = "block";
    ui.innerHTML = `
        <strong>Consult Now:</strong><br>
        📅 Date: <b>${date}</b><br>
        ⏰ Time: <b>${time}</b>
    `;
});



$('#slotDate').on('change', function () {
    let date = $(this).val();
    let doctor_id = $('#doctorSelect').val(); // dynamic as per your flow
 
    const dateWeek = new Date(this.value);
     // JS default: Sunday = 0 … Saturday = 6
    let jsDay = dateWeek.getDay();

    // Convert to your format: Monday = 1 … Sunday = 7
    let weekday = jsDay === 0 ? 7 : jsDay;

    $.ajax({  
        url: domainUrl+'api/v1/user/date_wise_slot',
        method: 'POST',
        data: {
            doctor_id: doctor_id, 
            date: date,
            weekday: weekday
        },
       success: function (res) {

    // Clear old items
    $('#slotTime').empty();

    if (!res || res.length === 0) {
        $('#slotTime').append('<option>No slots available</option>');
        return;
    }

    // Filter out booked slots
    let availableSlots = res.filter(s => s.is_booked == 0);

    if (availableSlots.length === 0) {
        $('#slotTime').append('<option>No slots available</option>');
        return;
    }

    // Populate drop-down
    availableSlots.forEach(slot => {
        $('#slotTime').append(`<option value="${slot.time}">${slot.time}</option>`);
    });
}

    });  
});

document.getElementById('patientInput').addEventListener('change', function () {
    let inputValue = this.value;
    let selectedOption = Array.from(document.querySelectorAll('#patientList option'))
        .find(opt => opt.value === inputValue);

    let container = document.getElementById('InsuranceUI');
    container.innerHTML = ""; // reset
    container.style.display = "none";

    if (selectedOption) {
        let type = selectedOption.dataset.type;

        // check if patient is insurance type
        let isInsurance = (type == "Insurance");
        
        // build checkbox UI
        container.innerHTML = `
            <div class="form-check">
                <input type="checkbox" 
                       id="markInsurance" name="markInsurance"
                       class="form-check-input"
                       ${isInsurance ? 'checked disabled' : ''}>
                <label for="markInsurance" class="form-check-label">
                    Covered Under Insurance
                </label>
            </div>
            <small class="text-muted d-block mt-1">
                ${isInsurance 
                    ? 'This patient is already marked as insurance. You cannot change it.' 
                    : 'Tick this if this visit should be billed under insurance.'}
            </small>
        `;

        container.style.display = "block";
    }
});




});    

$(document).ready(function () {

    // Inject CSS
    $("head").append(`
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    `);

    // Inject JS
    $.getScript("https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js", function () {
        
        // Initialize after script loads
        $('#doctorSelect').select2({
            placeholder: "Select Doctor",
            allowClear: true,
            width: '100%'
        });

    });

});


 