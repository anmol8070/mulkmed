$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".homeSampleLabsSideA").addClass("activeLi");

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

    function loadLabs() {
        $.getJSON(`${domainUrl}homeSampleCollections/labs/list`).done(function (response) {
            const rows = (response.data || []).map((lab) => `
                <tr>
                    <td>${escapeHtml(lab.name)}</td>
                    <td>${escapeHtml(lab.email)}</td>
                    <td>${lab.status ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>'}</td>
                    <td>${lab.bookings}</td>
                    <td>
                        <a href="#" class="mr-2 btn btn-primary text-white edit-lab" data-lab='${escapeHtml(JSON.stringify(lab))}'>Edit</a>
                        <a href="#" class="mr-2 btn btn-danger text-white delete-lab" rel="${lab.id}">Delete</a>
                    </td>
                </tr>`);
            $("#labsTable tbody").html(rows.length ? rows.join("") : '<tr><td colspan="5" class="text-center text-muted">No labs added yet</td></tr>');
        });
    }
    loadLabs();

    function openLabModal(lab) {
        $("#labFormTitle").text(lab ? "Edit Lab" : "Add Lab");
        $("#labId").val(lab ? lab.id : "");
        $("#labName").val(lab ? lab.name : "");
        $("#labEmail").val(lab ? lab.email : "");
        $("#labStatus").val(lab ? String(lab.status) : "1");
        $("#labModal").modal("show");
    }

    $("#addLabBtn").on("click", function (event) {
        event.preventDefault();
        openLabModal(null);
    });

    $("#labsTable").on("click", ".edit-lab", function (event) {
        event.preventDefault();
        openLabModal(JSON.parse($(this).attr("data-lab")));
    });

    $("#labForm").on("submit", function (event) {
        event.preventDefault();
        if (blockTester()) {
            return;
        }
        $.post(`${domainUrl}homeSampleCollections/labs/save`, $(this).serialize() + "&_token=" + encodeURIComponent(csrfToken()))
            .done(function (response) {
                notify(response);
                if (response.status) {
                    $("#labModal").modal("hide");
                    loadLabs();
                }
            })
            .fail(function (error) {
                console.log(error);
            });
    });

    $("#labsTable").on("click", ".delete-lab", function (event) {
        event.preventDefault();
        const id = $(this).attr("rel");
        swal({
            title: strings.doYouReallyWantToContinue,
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((isConfirm) => {
            if (!isConfirm || blockTester()) {
                return;
            }
            $.getJSON(`${domainUrl}homeSampleCollections/labs/delete/${id}`).done(function (response) {
                notify(response);
                loadLabs();
            });
        });
    });
});
