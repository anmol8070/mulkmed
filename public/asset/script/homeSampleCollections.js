$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".homeSampleCollectionsSideA").addClass("activeLi");

    const csrfToken = () => $('meta[name="csrf-token"]').attr("content");

    function escapeHtml(value) {
        return $("<div>").text(value ?? "").html();
    }

    function notify(response) {
        if (response.status) {
            iziToast.success({ title: strings.success, message: response.message, position: "topRight" });
        } else {
            iziToast.error({ title: strings.error, message: response.message, position: "topRight" });
        }
    }

    function blockTester() {
        if (user_type != "1") {
            iziToast.error({ title: strings.error, message: strings.youAreTester, position: "topRight" });
            return true;
        }
        return false;
    }

    // ---- Bookings ----
    const table = $("#homeSampleCollectionsTable").DataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        serverMethod: "POST",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                orderable: false,
                targets: [1, 3, 4, 5, 9],
            },
        ],
        ajax: {
            url: `${domainUrl}homeSampleCollections/fetch`,
            data: function (d) {
                d.status_filter = $("#statusFilter").val();
            },
            error: (error) => {
                console.log(error);
            },
        },
    });

    $("#statusFilter").on("change", function () {
        table.ajax.reload();
    });

    function postBookingChange(url, data) {
        if (blockTester()) {
            table.ajax.reload(null, false);
            return;
        }
        $.post(url, Object.assign({ _token: csrfToken() }, data))
            .done(function (response) {
                notify(response);
                table.ajax.reload(null, false);
            })
            .fail(function (error) {
                console.log(error);
            });
    }

    $("#homeSampleCollectionsTable").on("change", ".change-status", function () {
        postBookingChange(`${domainUrl}homeSampleCollections/status`, { id: $(this).attr("rel"), status: $(this).val() });
    });


});
