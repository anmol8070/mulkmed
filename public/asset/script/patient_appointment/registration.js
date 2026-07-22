$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".patientRegistrationSideA").addClass("activeLi");

});

$(document).ready(function () {

    // Inject CSS
    $("head").append(`
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    `);

    // Inject JS
    $.getScript("https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js", function () {
        
        // Initialize after script loads
        $('#nubmerSelect').select2({
            placeholder: "Select Country",
            allowClear: true,
            width: '40%',
        });

    });

});