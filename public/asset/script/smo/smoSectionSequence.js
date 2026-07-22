$(document).ready(function () {

    $(".sideBarli").removeClass("activeLi");
    $(".SMOSectionSequenceSideA").addClass("activeLi");

    // Fetch Sound Categories
    var url = `${domainUrl}getFaqCats`;
    var faqCategories;
    $.getJSON(url).done(function (data) {
        faqCategories = data.data;
    });

    $("#categoriesTable").dataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
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
            url: `${domainUrl}smo/fetchSectionSequence`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });
    $("#categoriesTable").on("click", ".delete", function (event) {
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
                    var url = `${domainUrl}smo/deleteSection` + "/" + id;

                    $.getJSON(url).done(function (data) {
                        console.log(data);
                        $("#categoriesTable")
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

    $(document).on('change', '.status_toggle', function () {
        let id = $(this).data('id');      // Get the ID from data-id attribute
        let status = $(this).is(':checked') ? 1 : 0;  // Determine status (1 or 0)
        console.log('hii');

        $.ajax({
            url: '/smo/sequenceStatusUpdate',  // Your Laravel route
            type: 'POST',
            data: {
                id: id,
                status: status,
                _token: $('meta[name="csrf-token"]').attr('content') // CSRF token
            },
            success: function (response) {
                if (response.status == true) {
                    $("#categoriesTable")
                            .DataTable()
                            .ajax.reload(null, false);
                        iziToast.success({
                            title: strings.success,
                            message: strings.operationSuccessful,
                            position: "topRight",
                        });
                } else {
                    alert('Failed to update status.');
                }
            },
            error: function () {
                alert('Something went wrong.');
            }
        });
    });

    $(document).on('click', '.update_position', function (e) {
        e.preventDefault();
        let id = $(this).data('id');
        let position = $(this).data('position');

        $.ajax({
            url: '/smo/sequenceUpdate',
            type: 'POST',
            data: {
                id: id,
                position: position,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                if (response.status == true) {
                    $("#categoriesTable")
                            .DataTable()
                            .ajax.reload(null, false);
                        iziToast.success({
                            title: strings.success,
                            message: strings.operationSuccessful,
                            position: "topRight",
                        });
                } else {
                    alert('Failed to update position.');
                }
            },
            error: function () {
                alert('Something went wrong.');
            }
        });
    });
});
