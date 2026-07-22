$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");

    var doctorId = $("#doctorId").val();
    console.log(doctorId);

    async function makeSquareImage(file) {
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = () => {
            const side = Math.min(img.width, img.height); // crop to smaller side
            const canvas = document.createElement("canvas");
            canvas.width = side;
            canvas.height = side;

            const ctx = canvas.getContext("2d");

            // TOP-CENTER crop
            const sx = (img.width - side) / 2; // center horizontally
            const sy = 0; // start crop from top

            ctx.drawImage(img, sx, sy, side, side, 0, 0, side, side);

            canvas.toBlob((blob) => {
                const squareFile = new File([blob], file.name, { type: "image/jpeg" });
                resolve(squareFile);
            }, "image/jpeg", 0.9);
        };
        img.src = URL.createObjectURL(file);
    });
}

    $("#chequePhoto").on("click", function (event) {
        event.preventDefault();
        var chequeUrl = $(this).data("cheque");

        $("#chequePhotoImg").attr("src", chequeUrl);
        $("#chequePhotoModal").modal("show");
    });
    $(".activateDoctor").on("click", function (event) {
        event.preventDefault();
        swal({
            title: strings.doYouReallyWantToContinue,
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((isTrue) => {
            if (isTrue) {
                if (user_type == "1") {
                    var url = `${domainUrl}activateDoctor` + "/" + doctorId;

                    $.getJSON(url).done(function (data) {
                        console.log(data);

                        location.reload();

                        iziToast.success({
                            title: strings.success,
                            message: strings.operationSuccessful,
                            position: "topRight",
                        });
                    });
                } else {
                    iziToast.error({
                        title: strings.error,
                        message: strings.youAreTester,
                        position: "topRight",
                    });
                }
            }
        });
    });
    $("#banDoctor").on("click", function (event) {
        event.preventDefault();
        swal({
            title: strings.doYouReallyWantToContinue,
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((isTrue) => {
            if (isTrue) {
                if (user_type == "1") {
                    var url = `${domainUrl}banDoctor` + "/" + doctorId;

                    $.getJSON(url).done(function (data) {
                        console.log(data);

                        location.reload();

                        iziToast.success({
                            title: strings.success,
                            message: strings.operationSuccessful,
                            position: "topRight",
                        });
                    });
                } else {
                    iziToast.error({
                        title: strings.error,
                        message: strings.youAreTester,
                        position: "topRight",
                    });
                }
            }
        });
    });

    $("#doctorDetailsForm").on("submit", async function (event) {
        event.preventDefault();
        if (user_type == "1") {
            var formdata = new FormData($("#doctorDetailsForm")[0]);

             var profileImage = $("#profile_image")[0].files[0];

             var signature = $("#signature")[0].files[0];

    if (profileImage) {
        // remove old entry (to avoid duplicate)
        formdata.delete("profile_image");

        // convert to square image
        const squareImage = await makeSquareImage(profileImage);

        // append squared version
        formdata.append("profile_image", squareImage, "profile_image.jpg");
    }

        if (signature) {
        // remove old entry (to avoid duplicate)
        formdata.delete("signature");

        // convert to square image
        const squareImage = await makeSquareImage(signature);

        // append squared version
        formdata.append("signature", squareImage, "signature.jpg");
    }
            
            $.ajax({
                url: `${domainUrl}updateDoctorDetails_Admin`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                
                     alert("success");
                    location.reload();
                },
                error: (error) => {
                    $(".loader").hide();
                    console.log(JSON.stringify(error));
                },
            });
        } else {
            $(".loader").hide();
            iziToast.error({
                title: strings.error,
                message: strings.youAreTester,
                position: "topRight",
            });
        }
    });

    $("#reviewsTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchDoctorReviewsList`,
            data: function (data) {
                data.doctorId = doctorId;
            },
            error: (error) => {
                console.log(error);
            },
        },
    });

    $("#doctorPayOutsTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchDoctorPayoutRequestsList`,
            data: function (data) {
                data.doctorId = doctorId;
            },
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#earningsTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchDoctorEarningsList`,
            data: function (data) {
                data.doctorId = doctorId;
            },
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#doctorPayOutsTable").on("click", ".complete", function (event) {
        event.preventDefault();
        var id = $(this).attr("rel");
        $("#completeId").val(id);

        $("#completeModal").modal("show");
    });
    $("#doctorPayOutsTable").on("click", ".reject", function (event) {
        event.preventDefault();
        var id = $(this).attr("rel");
        $("#rejectId").val(id);

        $("#rejectModal").modal("show");
    });

    $("#completeForm").on("submit", function (event) {
        event.preventDefault();
        $(".loader").show();
        if (user_type == "1") {
            var formdata = new FormData($("#completeForm")[0]);
            $.ajax({
                url: `${domainUrl}completeDoctorWithdrawal`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    $(".loader").hide();
                    $("#completeModal").modal("hide");
                    $("#completeForm").trigger("reset");
                    $("#doctorPayOutsTable")
                        .DataTable()
                        .ajax.reload(null, false);
                    iziToast.success({
                        title: strings.success,
                        message: strings.operationSuccessful,
                        position: "topRight",
                    });
                },
                error: (error) => {
                    $(".loader").hide();
                    console.log(JSON.stringify(error));
                },
            });
        } else {
            $(".loader").hide();
            iziToast.error({
                title: strings.error,
                message: strings.youAreTester,
                position: "topRight",
            });
        }
    });
    $("#rejectForm").on("submit", function (event) {
        event.preventDefault();
        $(".loader").show();
        if (user_type == "1") {
            var formdata = new FormData($("#rejectForm")[0]);
            $.ajax({
                url: `${domainUrl}rejectDoctorWithdrawal`,
                type: "POST",
                data: formdata,
                dataType: "json",
                contentType: false,
                cache: false,
                processData: false,
                success: function (response) {
                    $(".loader").hide();
                    $("#rejectModal").modal("hide");
                    $("#rejectForm").trigger("reset");
                    $("#doctorPayOutsTable")
                        .DataTable()
                        .ajax.reload(null, false);
                    iziToast.success({
                        title: strings.success,
                        message: strings.operationSuccessful,
                        position: "topRight",
                    });
                },
                error: (error) => {
                    $(".loader").hide();
                    console.log(JSON.stringify(error));
                },
            });
        } else {
            $(".loader").hide();
            iziToast.error({
                title: strings.error,
                message: strings.youAreTester,
                position: "topRight",
            });
        }
    });
    $("#reelsTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchDoctorReels_Admin`,
            data: function (data) {
                data.doctorId = doctorId;
            },
            error: (error) => {
                console.log(error);
            },
        },
    });

    $("#reelsTable").on("click", ".view-content", function (event) {
        event.preventDefault();
        var contentUrl = $(this).data("url");
        var description = $(this).data("description");

        $("#videoDesc").text(description);
        $("#video source").attr("src", contentUrl);
        $("#video")[0].load();
        $("#video_modal").modal("show");
        $("#video").trigger("play");
    });

    $("#video_modal").on("hidden.bs.modal", function () {
        $("#video").trigger("pause");
    });

    $("#reelsTable").on("click", ".delete", function (event) {
        event.preventDefault();
        swal({
            title: strings.doYouReallyWantToContinue,
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((isConfirm) => {
            if (isConfirm) {
                if (user_type == "1") {
                    var id = $(this).attr("rel");
                    var url = `${domainUrl}deleteReelAdmin` + "/" + id;

                    $.getJSON(url).done(function (data) {
                        console.log(data);
                        $("#reelsTable").DataTable().ajax.reload(null, false);
                        iziToast.success({
                            title: strings.success,
                            message: strings.operationSuccessful,
                            position: "topRight",
                        });
                    });
                } else {
                    iziToast.error({
                        title: strings.error,
                        message: strings.youAreTester,
                        position: "topRight",
                    });
                }
            }
        });
    });

    $("#walletStatementTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchDoctorWalletStatement`,
            data: function (data) {
                data.doctorId = doctorId;
            },
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#appointmentsTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchDoctorAppointmentsList`,
            data: function (data) {
                data.doctorId = doctorId;
            },
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#servicesTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchDoctorServicesList`,
            data: function (data) {
                data.doctorId = doctorId;
            },
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#expertiseTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchDoctorExpertiseList`,
            data: function (data) {
                data.doctorId = doctorId;
            },
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#serviceLocationsTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchDoctorServiceLocationList`,
            data: function (data) {
                data.doctorId = doctorId;
            },
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#experienceTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchDoctorExperienceList`,
            data: function (data) {
                data.doctorId = doctorId;
            },
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#awardsTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchDoctorAwardsList`,
            data: function (data) {
                data.doctorId = doctorId;
            },
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#holidaysTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchDoctorHolidaysList`,
            data: function (data) {
                data.doctorId = doctorId;
            },
            error: (error) => {
                console.log(error);
            },
        },
    });

    $("#holidaysTable").on("click", ".delete", function (event) {
        event.preventDefault();
        swal({
            title: strings.doYouReallyWantToContinue,
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((isConfirm) => {
            if (isConfirm) {
                if (user_type == "1") {
                    var id = $(this).attr("rel");
                    var url = `${domainUrl}deleteDoctorHoliday` + "/" + id;

                    $.getJSON(url).done(function (data) {
                        console.log(data);
                        $("#holidaysTable")
                            .DataTable()
                            .ajax.reload(null, false);
                        iziToast.success({
                            title: strings.success,
                            message: strings.operationSuccessful,
                            position: "topRight",
                        });
                    });
                } else {
                    iziToast.error({
                        title: strings.error,
                        message: strings.youAreTester,
                        position: "topRight",
                    });
                }
            }
        });
    });
    
    $("#awardsTable").on("click", ".delete", function (event) {
        event.preventDefault();
        swal({
            title: strings.doYouReallyWantToContinue,
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((isConfirm) => {
            if (isConfirm) {
                if (user_type == "1") {
                    var id = $(this).attr("rel");
                    var url = `${domainUrl}deleteAward` + "/" + id;

                    $.getJSON(url).done(function (data) {
                        console.log(data);
                        $("#awardsTable").DataTable().ajax.reload(null, false);
                        iziToast.success({
                            title: strings.success,
                            message: strings.operationSuccessful,
                            position: "topRight",
                        });
                    });
                } else {
                    iziToast.error({
                        title: strings.error,
                        message: strings.youAreTester,
                        position: "topRight",
                    });
                }
            }
        });
    });
    $("#reviewsTable").on("click", ".delete", function (event) {
        event.preventDefault();
        swal({
            title: strings.doYouReallyWantToContinue,
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((isConfirm) => {
            if (isConfirm) {
                if (user_type == "1") {
                    var id = $(this).attr("rel");
                    var url = `${domainUrl}deleteReview` + "/" + id;

                    $.getJSON(url).done(function (data) {
                        console.log(data);
                        $("#reviewsTable").DataTable().ajax.reload(null, false);
                        iziToast.success({
                            title: strings.success,
                            message: strings.operationSuccessful,
                            position: "topRight",
                        });
                    });
                } else {
                    iziToast.error({
                        title: strings.error,
                        message: strings.youAreTester,
                        position: "topRight",
                    });
                }
            }
        });
    });

    $("#expertiseTable").on("click", ".delete", function (event) {
        event.preventDefault();
        swal({
            title: strings.doYouReallyWantToContinue,
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((isConfirm) => {
            if (isConfirm) {
                if (user_type == "1") {
                    var id = $(this).attr("rel");
                    var url = `${domainUrl}deleteExpertise` + "/" + id;

                    $.getJSON(url).done(function (data) {
                        console.log(data);
                        $("#expertiseTable")
                            .DataTable()
                            .ajax.reload(null, false);
                        iziToast.success({
                            title: strings.success,
                            message: strings.operationSuccessful,
                            position: "topRight",
                        });
                    });
                } else {
                    iziToast.error({
                        title: strings.error,
                        message: strings.youAreTester,
                        position: "topRight",
                    });
                }
            }
        });
    });
    $("#servicesTable").on("click", ".delete", function (event) {
        event.preventDefault();
        swal({
            title: strings.doYouReallyWantToContinue,
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((isConfirm) => {
            if (isConfirm) {
                if (user_type == "1") {
                    var id = $(this).attr("rel");
                    var url = `${domainUrl}deleteService` + "/" + id;

                    $.getJSON(url).done(function (data) {
                        console.log(data);
                        $("#servicesTable")
                            .DataTable()
                            .ajax.reload(null, false);
                        iziToast.success({
                            title: strings.success,
                            message: strings.operationSuccessful,
                            position: "topRight",
                        });
                    });
                } else {
                    iziToast.error({
                        title: strings.error,
                        message: strings.youAreTester,
                        position: "topRight",
                    });
                }
            }
        });
    });
    $("#experienceTable").on("click", ".delete", function (event) {
        event.preventDefault();
        swal({
            title: strings.doYouReallyWantToContinue,
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((isConfirm) => {
            if (isConfirm) {
                if (user_type == "1") {
                    var id = $(this).attr("rel");
                    var url = `${domainUrl}deleteExperience` + "/" + id;

                    $.getJSON(url).done(function (data) {
                        console.log(data);
                        $("#experienceTable")
                            .DataTable()
                            .ajax.reload(null, false);
                        iziToast.success({
                            title: strings.success,
                            message: strings.operationSuccessful,
                            position: "topRight",
                        });
                    });
                } else {
                    iziToast.error({
                        title: strings.error,
                        message: strings.youAreTester,
                        position: "topRight",
                    });
                }
            }
        });
    });
    $("#awardsTable").on("click", ".delete", function (event) {
        event.preventDefault();
        swal({
            title: strings.doYouReallyWantToContinue,
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((isConfirm) => {
            if (isConfirm) {
                if (user_type == "1") {
                    var id = $(this).attr("rel");
                    var url = `${domainUrl}deleteAwards` + "/" + id;

                    $.getJSON(url).done(function (data) {
                        console.log(data);
                        $("#awardsTable").DataTable().ajax.reload(null, false);
                        iziToast.success({
                            title: strings.success,
                            message: strings.operationSuccessful,
                            position: "topRight",
                        });
                    });
                } else {
                    iziToast.error({
                        title: strings.error,
                        message: strings.youAreTester,
                        position: "topRight",
                    });
                }
            }
        });
    });
    $("#serviceLocationsTable").on("click", ".delete", function (event) {
        event.preventDefault();
        swal({
            title: strings.doYouReallyWantToContinue,
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((isConfirm) => {
            if (isConfirm) {
                if (user_type == "1") {
                    var id = $(this).attr("rel");
                    var url = `${domainUrl}deleteServiceLocation` + "/" + id;

                    $.getJSON(url).done(function (data) {
                        console.log(data);
                        $("#serviceLocationsTable")
                            .DataTable()
                            .ajax.reload(null, false);
                        iziToast.success({
                            title: strings.success,
                            message: strings.operationSuccessful,
                            position: "topRight",
                        });
                    });
                } else {
                    iziToast.error({
                        title: strings.error,
                        message: strings.youAreTester,
                        position: "topRight",
                    });
                }
            }
        });
    });

$(document).on("click", ".expertiseEdit", function (e) {
    e.preventDefault();

    let id = $(this).data("id");
    let title = $(this).data("title");

    // Fill modal fields directly
    $("#expertise_id").val(id);
    $("#expertise_title").val(title);

    // Show modal
    $("#editExpertiseModal").modal("show");
});

$("#editExpertiseForm").on("submit", function (e) {
    e.preventDefault();

    $.ajax({
        url: `${domainUrl}updateExpertise`,
        type: "POST",
        data: $(this).serialize(),
        success: function (response) {
            if (response.status) {
                $("#editExpertiseModal").modal("hide");
                 iziToast.success({
                    title: strings.success,
                    message: strings.operationSuccessful,
                    position: "topRight",
                });
                $("#expertiseTable").DataTable().ajax.reload(null, false); // reload without resetting pagination
            } else {
                alert(response.message || "Something went wrong!");
            }
        },
        error: function (xhr) {
            console.error(xhr.responseText);
        }
    });
});

$("#updatePasswordForm").on("submit", function (e) {
        e.preventDefault();

        var password = $("#password").val();
        var confirm_password = $("#confirm_password").val();

        if (password !== confirm_password) {
            iziToast.error({
                title: strings.error,
                message: "Passwords do not match!",
                position: "topRight",
            });
            return;
        }

        if (user_type == "1") {
            $.ajax({
                url: `${domainUrl}updateDoctorPassword`,
                type: "POST",
                data: $(this).serialize(),
                success: function (response) {
                    if (response.status) {
                        iziToast.success({
                            title: strings.success,
                            message: response.message,
                            position: "topRight",
                        });
                        $("#updatePasswordForm").trigger("reset");
                    } else {
                        iziToast.error({
                            title: strings.error,
                            message: response.message,
                            position: "topRight",
                        });
                    }
                },
                error: function (xhr) {
                    iziToast.error({
                        title: strings.error,
                        message: "Something went wrong!",
                        position: "topRight",
                    });
                }
            });
        } else {
            iziToast.error({
                title: strings.error,
                message: strings.youAreTester,
                position: "topRight",
            });
        }
    });

    $("#is_smo").on('change', function (e){
        fetch(`${domainUrl}api/v1/changeSmoStatus`, {
            method: 'POST',
            headers:{
                "Accept": "application/json",
                "Content-Type": "application/json",
                "APIKEY": "123"
            },
            body: JSON.stringify({
                "doctor_id" : $("#doctorId").val(),
                "is_smo" : $(this).is(":checked") ? 1 : 0
            })
        })
        .then(() => {
            iziToast.success({
                title: "success",
                message: "SMO Status Changed",
                position: "topRight",
            });
        });
    });

    $("#is_mulkmed").on('change', function (e){
        fetch(`${domainUrl}api/v1/changeMulkmedStatus`, {
            method: 'POST',
            headers:{
                "Accept": "application/json",
                "Content-Type": "application/json",
                "APIKEY": "123"
            },
            body: JSON.stringify({
                "doctor_id" : $("#doctorId").val(),
                "is_mulkmed" : $(this).is(":checked") ? 1 : 0
            })
        })
        .then(() => {
            iziToast.success({
                title: "success",
                message: "Mulkmed Status Changed",
                position: "topRight",
            });
        });
    });

    $("#is_longevity_care").on('change', function () {
        var toggleElement = $(this);
        var longevityCareStatus = toggleElement.is(":checked") ? 1 : 0;
        fetch(`${domainUrl}changeLongevityCareStatus`, {
            method: 'POST',
            headers: {
                "Accept": "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content')
            },
            body: JSON.stringify({
                "doctor_id": $("#doctorId").val(),
                "is_longevity_care": longevityCareStatus
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status) {
            iziToast.success({
                title: "success",
                message: "Longevity Care Status Changed",
                position: "topRight",
            });
            } else {
                iziToast.error({
                    title: "error",
                    message: data.message || "Failed to change status",
                    position: "topRight",
                });
                toggleElement.prop('checked', longevityCareStatus !== 1);
            }
        }).catch(() => {
            iziToast.error({
                title: "error",
                message: "Network or Server Error",
                position: "topRight",
            });
            toggleElement.prop('checked', longevityCareStatus !== 1);
        });
    });



    $("#travel_visible").on("change", function () {
        const travelVisible = this.checked ? 1 : 0;
        fetch(`${domainUrl}api/v1/changeTravelVisibleStatus`, {
            method: "POST",
            headers: {
                "Accept": "application/json",
                "Content-Type": "application/json",
                "APIKEY": "123"
            },
            body: JSON.stringify({
                doctor_id: $("#doctorId").val(),
                travel_visible: travelVisible
            })
        })
        .then(res => res.json())
        .then(() => {
            iziToast.success({
                title: "Success",
                message: "Travel Visible Status Changed",
                position: "topRight",
            });
        })
        .catch(() => {
            iziToast.error({
                title: "Error",
                message: "Something went wrong",
                position: "topRight",
            });
        });
    });

});



    document.addEventListener('DOMContentLoaded', function () {
        
        const modalEl = document.getElementById('slotModal');
        if (!modalEl) return;

        // select all buttons with class slotModal
        const btns = document.querySelectorAll('.slotModal');

        btns.forEach(btn => {
            btn.addEventListener('click', (e) => {
            e.preventDefault();

                const bsModal = new bootstrap.Modal(modalEl);
                bsModal.show();

                const doctorId = $("#doctorId").val();   // from your other hidden/input field
                document.getElementById("slot_doctor_id").value = doctorId;

                const weekday = btn.getAttribute("data-weekday");
                document.getElementById("slot_weekday").value = weekday;
            });
        });

        $(document).on('submit', '#bulkUploadSlotsForm', function (e) {
            e.preventDefault();

            let formData = new FormData(this);

            $.ajax({
                url: `${domainUrl}bulkUploadDoctorSlots`,
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    // show success message
                     iziToast.success({
                        title: strings.success,
                        message: strings.operationSuccessful,
                        position: "topRight",
                    });
                    // console.log(response);
                    sessionStorage.setItem('activateTab', '#tabSlots');
                    location.reload();
                },
                error: function (xhr) {
                    // show validation or server errors
                    console.log(xhr.responseText);
                    alert('Something went wrong!');
                }
            });
        });
    });

    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll(".delete-slot").forEach(button => {
            button.addEventListener("click", async function () {
                let slotId = this.getAttribute("data-slot-id");

                    const res = await fetch(`${domainUrl}api/v1/deleteAppointmentSlot`, {
                        method: "POST",
                        headers: {
                        "Accept": "application/json",
                        "Content-Type": "application/json",
                        "APIKEY": "123"   
                        },
                        body: JSON.stringify({ slot_id: slotId })
                    });

                    const data = await res.json();
                    console.log("API response:", data);
                    if (res.ok) {
                        iziToast.success({
                            title: strings.success,
                            message: strings.operationSuccessful,
                            position: "topRight",
                        });

                        const modalEl = document.getElementById('slotModal');
                        if (modalEl) {
                            sessionStorage.setItem('activateTab', '#tabSlots');
                            location.reload();
                        }  
                    } else {
                        alert(data.message || "Failed to delete slot");
                    }
            });
        });
    });

  document.addEventListener('DOMContentLoaded', () => {
  
  const form = document.getElementById('SlotForm');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // grab form values
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
    var doctorId = $("#doctorId").val();
    // transform time → remove colon
    if (payload.time) {
    payload.time = payload.time.replace(":", ""); // "12:30" → "1230"
    }

    try {
      const res = await fetch(`${domainUrl}api/v1/addAppointmentSlots`, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "APIKEY": "123"   
        },
        body: JSON.stringify(payload)
      });

      const data = await res.json();
      console.log("API response:", data);

      if (res.ok) {
        
        iziToast.success({
            title: strings.success,
            message: strings.operationSuccessful,
            position: "topRight",
        });
        
        // close modal if open
        const modalEl = document.getElementById('slotModal');
        if (modalEl) {
            sessionStorage.setItem('activateTab', '#tabSlots');
            location.reload();
        }      
        
      } else {
        alert("Error: " + (data.message || "failed"));
      }
    } catch (err) {
      console.error("Fetch error:", err);
      alert("Something went wrong: " + err.message);
    }
  });

  document.querySelector('.fake-backdrop-close').addEventListener('click', function () {
  $('#slotModal').modal('hide');
});

$(document).on('click', '[data-toggle="tab"]', function () {
    const target = $(this).attr('href');

    if (target === '#tabSlots') {
        // Show the div and set doctor ID
        $('#bulkUploadSlotsDiv').removeClass('d-none');
    } else {
        // Hide when any other tab is clicked
        $('#bulkUploadSlotsDiv').addClass('d-none');
    }
});
  $('#bulk_upload_doctor_id').val($("#doctorId").val());
  
});

$(function () {
  const target = sessionStorage.getItem('activateTab');
  if (target) {
    $('a[href="' + target + '"]').tab('show');
    document.getElementById('bulkUploadSlotsDiv').classList.remove('d-none');
    sessionStorage.removeItem('activateTab');
  }
});
