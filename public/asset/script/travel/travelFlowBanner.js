$(document).ready(function () {

    console.log("TravelFlowBanner JS Ready");

    /* ===============================
       CSRF SETUP
    ================================ */
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    /* ===============================
       SIDEBAR ACTIVE
    ================================ */
    $(".sideBarli").removeClass("activeLi");
    $(".TravelFlowBannerSideA").addClass("activeLi");

    /* ===============================
       FORMAT BANNER TYPE (REMOVE _)
    ================================ */
    function formatBannerType(value) {
        if (!value) return '';
        return value
            .replace(/_/g, ' ')
            .replace(/\b\w/g, c => c.toUpperCase());
    }

    /* ===============================
       DATATABLE (ARRAY RESPONSE FIX)
    ================================ */
    let table = $("#THP").DataTable({
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        processing: true,
        serverSide: true,
        ordering: false,

        ajax: {
            url: domainUrl + "fetchTravelFlowBanner",
            type: "POST",
            error: function (xhr) {
                console.error("Datatable Error:", xhr.responseText);
            }
        },

        columns: [
            {
                data: 0,          // IMAGE (HTML FROM BACKEND)
                orderable: false
            },
            {
                data: 1,          // BANNER TYPE
                orderable: false,
                render: function (data) {
                    return formatBannerType(data); // 🔥 UNDERSCORE REMOVED
                }
            },
            {
                data: 2,          // ACTION (HTML FROM BACKEND)
                orderable: false
            }
        ]
    });

    /* ===============================
       ADD BANNER
    ================================ */
    $("#addCatForm").on("submit", function (e) {
        e.preventDefault();

        if (user_type !== "1") {
            iziToast.error({
                title: strings.error,
                message: strings.youAreTester,
                position: "topRight"
            });
            return;
        }

        let bannerType = $("#addCatForm select[name='banner_type']").val();
        if (!bannerType) {
            iziToast.error({
                title: strings.error,
                message: "Please select banner type",
                position: "topRight"
            });
            return;
        }

        let formData = new FormData(this);

        $.ajax({
            url: domainUrl + "addTravelFlowBanner",
            type: "POST",
            data: formData,
            contentType: false,
            processData: false,
            success: function (res) {
                $("#addCatModal").modal("hide");
                $("#addCatForm")[0].reset();
                $("#addPreviewImage").attr("src", "");
                table.ajax.reload(null, false);

                iziToast.success({
                    title: strings.success,
                    message: res.message || "Banner added successfully",
                    position: "topRight"
                });
            },
            error: function (xhr) {
                console.error("ADD ERROR:", xhr.responseText);
            }
        });
    });

    /* ===============================
       EDIT MODAL OPEN
    ================================ */
    $("#THP").on("click", ".edit", function (e) {
        e.preventDefault();

        $("#editCatId").val($(this).attr("rel"));
        $("#previewImage").attr("src", $(this).data("icon"));
        $("#editBannerType").val($(this).data("type"));

        $("#editCatModal").modal("show");
    });

    /* ===============================
       EDIT SUBMIT
    ================================ */
    $("#editCatForm").on("submit", function (e) {
        e.preventDefault();

        if (user_type !== "1") {
            iziToast.error({
                title: strings.error,
                message: strings.youAreTester,
                position: "topRight"
            });
            return;
        }

        let bannerType = $("#editBannerType").val();
        if (!bannerType) {
            iziToast.error({
                title: strings.error,
                message: "Please select banner type",
                position: "topRight"
            });
            return;
        }

        let formData = new FormData(this);

        $.ajax({
            url: domainUrl + "editTravelFlowBanner",
            type: "POST",
            data: formData,
            contentType: false,
            processData: false,
            success: function (res) {
                $("#editCatModal").modal("hide");
                $("#editCatForm")[0].reset();
                $("#previewImage").attr("src", "");
                table.ajax.reload(null, false);

                iziToast.success({
                    title: strings.success,
                    message: res.message || "Banner updated successfully",
                    position: "topRight"
                });
            },
            error: function (xhr) {
                console.error("EDIT ERROR:", xhr.responseText);
            }
        });
    });

    /* ===============================
       DELETE
    ================================ */
    $("#THP").on("click", ".delete", function (e) {
        e.preventDefault();

        let id = $(this).attr("rel");

        swal({
            title: strings.doYouReallyWantToContinue,
            icon: "warning",
            buttons: true,
            dangerMode: true
        }).then((isConfirm) => {
            if (isConfirm && user_type === "1") {
                $.get(domainUrl + "deleteTravelFlowBanner/" + id, function () {
                    table.ajax.reload(null, false);

                    iziToast.success({
                        title: strings.success,
                        message: strings.operationSuccessful,
                        position: "topRight"
                    });
                });
            }
        });
    });

    /* ===============================
       IMAGE PREVIEW
    ================================ */
    $("#addImage").on("change", function () {
        if (this.files && this.files[0]) {
            let reader = new FileReader();
            reader.onload = e => $("#addPreviewImage").attr("src", e.target.result);
            reader.readAsDataURL(this.files[0]);
        }
    });

    $("#editImage").on("change", function () {
        if (this.files && this.files[0]) {
            let reader = new FileReader();
            reader.onload = e => $("#previewImage").attr("src", e.target.result);
            reader.readAsDataURL(this.files[0]);
        }
    });

});
