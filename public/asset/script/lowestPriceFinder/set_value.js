
$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".LowestPriceFinderPriceSideA").addClass("activeLi");

    /*  CSRF  */
    $.ajaxSetup({
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
        }
    });

    /*  LOAD HOSPITALS  */
    function loadHospitals() {
        $.get("/get-hospitals", function (data) {
            let opt = '<option value="">Select Hospital</option>';
            data.forEach(h => {
                opt += `<option value="${h.id}">${h.name}</option>`;
            });
            $("#addHospital, #editHospital").html(opt);
        });
    }
    loadHospitals();

    /*  HOSPITAL → PROCEDURES  */
    $("#addHospital, #editHospital").on("change", function () {
        let hospitalId = $(this).val();
        let target = this.id === "addHospital" ? "#addProcedure" : "#editProcedure";

        $(target).html('<option value="">Loading...</option>');

        if (!hospitalId) {
            $(target).html('<option value="">Select Procedure</option>');
            return;
        }

        $.get("/get-procedure-by-hospital/" + hospitalId, function (data) {
            let opt = '<option value="">Select Procedure</option>';
            data.forEach(p => {
                opt += `<option value="${p.id}">${p.procedure}</option>`;
            });
            $(target).html(opt);
        });
    });

   $("#THP").dataTable({
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
            url: `${domainUrl}lowestprice/fetch`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });

    /*  ADD  */
    $("#addDiseaseForm").submit(function (e) {
        e.preventDefault();

        $.post("/lowestprice/store", $(this).serialize(), function (res) {
            if (res.status) {
                iziToast.success({
                    title: "Success",
                    message: res.message,
                    position: "topRight"
                });
                $("#addDiseaseModel").modal("hide");
                   $("#THP").DataTable().ajax.reload(null, false);
            }
        });
    });

    /*  EDIT OPEN  */
    $(document).on("click", ".edit-btn", function () {
        $("#editDiseaseId").val($(this).data("id"));
        $("#editHospital").val($(this).data("hospital")).trigger("change");

        setTimeout(() => {
            $("#editProcedure").val($(this).data("procedure"));
            $("#editPriceType").val($(this).data("price-type"));
        }, 300);

        $("#editPrice").val($(this).data("price"));
        $("#editDiseaseModel").modal("show");
    });

    /*  EDIT SUBMIT  */
    $("#editDiseaseForm").submit(function (e) {
        e.preventDefault();

        $.post("/lowestprice/update", $(this).serialize(), function (res) {
            if (res.status) {
                iziToast.success({
                    title: "Success",
                    message: res.message,
                    position: "topRight"
                });
                $("#editDiseaseModel").modal("hide");
                   $("#THP").DataTable().ajax.reload(null, false);
            }
        });
    });

    /*  DELETE  */
    $(document).on("click", ".delete-btn", function () {
        let id = $(this).data("id");

        swal({
            title: "Do you really want to continue?",
            icon: "warning",
            buttons: true,
            dangerMode: true
        }).then(ok => {
            if (ok) {
                $.post("/lowestprice/delete/" + id, function (res) {
                    if (res.status) {
                        iziToast.success({
                            title: "Deleted",
                            message: res.message,
                            position: "topRight"
                        });
                        $("#THP").DataTable().ajax.reload(null, false);
                    }
                });
            }
        });
    });

});
 