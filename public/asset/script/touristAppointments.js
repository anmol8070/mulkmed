$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".touristAppointmentsSideA").addClass("activeLi");

    $("#allAppointmentTable").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5, 6],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchAllTouristAppointmentsList`,
            data: function (data) {
                console.log(data);
            },
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#pendingAppointmentTable").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5, 6],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchPendingTouristAppointmentsList`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#acceptedAppointmentTable").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5, 6],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchAcceptedTouristAppointmentsList`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#completedAppointmentTable").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5, 6],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchCompletedTouristAppointmentsList`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#cancelledAppointmentTable").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5, 6],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchCancelledTouristAppointmentsList`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#declinedAppointmentTable").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5, 6],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchDeclinedTouristAppointmentsList`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#missedAppointmentTable").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5, 6],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchMissedTouristAppointmentsList`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });
});
