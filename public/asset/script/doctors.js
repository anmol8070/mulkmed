// Capture-phase listener so confirm always runs (avoids dual-jQuery / DataTable issues)
document.addEventListener(
    "click",
    function (event) {
        var btn = event.target.closest(".delete-doctor");
        if (!btn) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        var deleteUrl = btn.getAttribute("data-url") || btn.getAttribute("href");
        if (!deleteUrl) {
            return;
        }

        var userTypeEl = document.getElementById("user_type");
        var currentUserType =
            (userTypeEl && userTypeEl.value) ||
            (typeof user_type !== "undefined" ? user_type : "");
        var confirmTitle =
            typeof strings !== "undefined" && strings.doYouReallyWantToContinue
                ? strings.doYouReallyWantToContinue
                : "Do you really want to continue?";
        var confirmText = "This doctor will be permanently deleted.";

        function showToast(type, title, message) {
            if (typeof iziToast === "undefined") {
                alert(message);
                return;
            }
            iziToast[type]({
                title: title,
                message: message,
                position: "topRight",
            });
        }

        function performDelete() {
            if (String(currentUserType) !== "1") {
                showToast(
                    "error",
                    (typeof strings !== "undefined" && strings.error) || "Error!",
                    (typeof strings !== "undefined" && strings.youAreTester) ||
                        "You Are Tester!"
                );
                return;
            }

            if (typeof jQuery === "undefined") {
                window.location.href = deleteUrl;
                return;
            }

            jQuery
                .getJSON(deleteUrl)
                .done(function (data) {
                    [
                        "#allDoctorsTable",
                        "#approvedDoctorsTable",
                        "#pendingDoctorsTable",
                        "#bannedDoctorsTable",
                    ].forEach(function (tableId) {
                        if (jQuery.fn.DataTable.isDataTable(tableId)) {
                            jQuery(tableId).DataTable().ajax.reload(null, false);
                        }
                    });

                    showToast(
                        "success",
                        (typeof strings !== "undefined" && strings.success) ||
                            "Success!",
                        (data && data.message) ||
                            (typeof strings !== "undefined" &&
                                strings.operationSuccessful) ||
                            "Operation Successful!"
                    );
                })
                .fail(function () {
                    showToast(
                        "error",
                        (typeof strings !== "undefined" && strings.error) ||
                            "Error!",
                        "Something went wrong. Please try again."
                    );
                });
        }

        function askConfirm(onYes) {
            if (typeof swal === "function") {
                swal({
                    title: confirmTitle,
                    text: confirmText,
                    icon: "warning",
                    buttons: true,
                    dangerMode: true,
                }).then(function (isConfirm) {
                    if (isConfirm) {
                        onYes();
                    }
                });
                return;
            }

            if (window.confirm(confirmTitle + "\n" + confirmText)) {
                onYes();
            }
        }

        askConfirm(performDelete);
    },
    true
);

$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".doctorsSideA").addClass("activeLi");

    $("#allDoctorsTable").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        stateSave: true,
        stateDuration: 0,
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
                orderable: false,
            },
            {
                targets: [2, 3],
                visible: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchAllDoctorsList${window.location.search}`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#approvedDoctorsTable").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        stateSave: true,
        stateDuration: 0,
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
                orderable: false,
            },
            {
                targets: [2, 3],
                visible: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchApprovedDoctorsList`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#pendingDoctorsTable").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        stateSave: true,
        stateDuration: 0,
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
                orderable: false,
            },
            {
                targets: [2, 3],
                visible: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchPendingDoctorsList`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#bannedDoctorsTable").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        stateSave: true,
        stateDuration: 0,
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
                orderable: false,
            },
            {
                targets: [2, 3],
                visible: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchBannedDoctorsList`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });
});
